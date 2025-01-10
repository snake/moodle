<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace core_ltix\local\ltiopenid;

use core_ltix\local\lticore\exception\lti_exception;
use core_ltix\local\lticore\message\lti_message;
use core_ltix\local\lticore\message\lti_message_base;
use core_ltix\local\lticore\message\payload\custom\custom_param_parser;
use core_ltix\local\lticore\message\payload\custom\factory\custom_param_parser_factory;
use core_ltix\local\lticore\message\payload\lti_1p3_payload_formatter;
use core_ltix\local\lticore\repository\tool_registration_repository;
use core_ltix\local\lticore\token\lti_token;

/**
 * Class validating LTI 1.3 3rd party login requests, providing the lti message that can be posted to the tool's redirect URI.
 *
 * @package    core_ltix
 * @copyright  2024 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lti_oidc_authenticator {

    /**
     * Constructor.
     *
     * @param lti_user_authenticator_interface $userauthenticator user authenticator which performs user auth.
     * @param tool_registration_repository $registrationrepository a repository used to fetch the tool registration.
     * @param lti_1p3_payload_formatter $payloadformatter a formatter instance to turn unformatted payload data into 1p3 JWT claims.
     * @param custom_param_parser_factory $customparamparserfactory factory to get a custom param parser instance.
     * @param array $jwks array representation of the JWKS JSON, used to decode the JWT in the auth request.
     */
    public function __construct(
        protected lti_user_authenticator_interface $userauthenticator,
        protected tool_registration_repository $registrationrepository,
        protected lti_1p3_payload_formatter $payloadformatter,
        protected custom_param_parser_factory $customparamparserfactory,
        protected array $jwks // TODO needs to be a key object that contains jwks + private key information.
    ) {
    }

    /**
     * Validate the OIDC login and return the lti_message_base instance to post to the tool.
     *
     * @return lti_message_base the lti message instance which can then be posted to the tool's launch redirect endpoint.
     */
    public function authenticate(array $authrequestdata): lti_message_base {

        // Retrieve the JWT from the lti_message_hint and parse into an lti_token.
        try {
            $launchjwt = $authrequestdata['lti_message_hint'] ?? '';
            $launchtoken = lti_token::from_jwt_with_keyset($launchjwt, $this->jwks);
        } catch (\Exception $e) {
            throw new lti_exception(
                'Invalid lti_message_hint. lti_message_hint: '.$launchjwt.', exception message: '.$e->getTraceAsString()
            );
        }

        $toolregistrationid = $launchtoken->get_claim('tool_registration_id');
        $toolconfig = $this->registrationrepository->get_by_id($toolregistrationid);
        if ($toolconfig === null) {
            throw new lti_exception('Cannot find registration id: '.$toolregistrationid);
        }

        $this->validate_auth_request($authrequestdata, $toolconfig);

        $authresult = $this->userauthenticator->authenticate($toolconfig, $authrequestdata['login_hint']);
        if (!$authresult->successful()) {
            // Todo: probably best to let the user authenticator throw an lti_auth_exception or smth instead of this:
            throw new lti_exception("Error authenticating user: {$authrequestdata['login_hint']}");
        }
        $ltiuser = $authresult->get_lti_user();

        // Perform final substitution of custom claim properties using the data present in the auth'd user.
        // User data isn't present at launch initiation time, so this final substitution of claims needs to be done here,
        // in case any custom param property uses a user-centric substitution param.
        $launchtoken = $this->resolve_substitution(
            $launchtoken,
            $this->customparamparserfactory->get_parser_from_auth_request($toolconfig, $launchtoken, $ltiuser),
            $ltiuser
        );

        // Format + add the new user claims to the token.
        $launchtoken = $this->format_user_claims($launchtoken, $ltiuser);

        // Nonce is opaque to the platform and must be returned as it was sent in the auth request.
        $launchtoken->add_claim('nonce', $authrequestdata['nonce']);

        return new lti_message(
            $authrequestdata['redirect_uri'],
            [
                ...(isset($authrequestdata['state']) ? ['state' => $authrequestdata['state']] : []),
                'id_token' => $launchtoken->to_jwt(
                    privatekey: jwks_helper::get_private_key()['key'], // TODO once passed in, use the key object to sign.
                    kid: jwks_helper::get_private_key()['kid']
                )
            ]
        );
    }

    /**
     * Format the user claims as JWT claims and add them to the launch token.
     *
     * @param lti_token $launchtoken the token to add to.
     * @param lti_user $ltiuser the LTI authenticated user.
     * @return lti_token the updated launch token.
     */
    private function format_user_claims(lti_token $launchtoken, lti_user $ltiuser): lti_token {

        foreach ($this->payloadformatter->format($ltiuser->get_unformatted_userdata()) as $name => $value) {
            if (!is_null($value)) {
                $launchtoken->add_claim($name, $value);
            }
        }
        return $launchtoken;
    }

    /**
     * Perform substitution of custom params which may include user-centric substitution variables.
     *
     * @param lti_token $launchtoken the launch token in which the custom claims reside.
     * @param custom_param_parser $parser a custom parameter parser.
     * @param lti_user $ltiuser the LTI authenticated user.
     * @return lti_token the update launch token.
     */
    private function resolve_substitution(lti_token $launchtoken, custom_param_parser $parser, lti_user $ltiuser): lti_token {

        $unformatteduserdata = $ltiuser->get_unformatted_userdata();

        $claims = array_map(
            fn($claimval) => $parser->parse($claimval, $unformatteduserdata),
            $launchtoken->get_claim(\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/custom') ?? []
        );

        $launchtoken->add_claim(\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/custom', $claims);

        return $launchtoken;
    }

    /**
     * Validation of the authentication request.
     *
     * @param array $requestdata the request payload data.
     * @param \stdClass $toolconfig the tool registration
     * @return void
     * @throws lti_exception in the case of any validation errors.
     */
    private function validate_auth_request(array $requestdata, \stdClass $toolconfig): void {
        // Validation of the following, per the spec (https://www.imsglobal.org/spec/security/v1p1#step-2-authentication-request):
        // - scope - must be 'openid'
        // - response_type - must be 'id_token'
        // - response_mode - must be 'form_post'
        // - prompt - must be 'none'
        // - nonce - is required
        // - client_id must match the client id associated with the given registration.
        // - NO: login_hint - must match user who started the launch - this is done in the user_authenticator.
        // - redirect_uri - must match valid, stored redirect URI (from tool config).

        if (($scope = $requestdata['scope'] ?? '') !== 'openid') {
            throw new lti_exception("Invalid scope. scope: $scope. Must be 'openid'.");
        }
        if (($responsetype = $requestdata['response_type'] ?? '') !== 'id_token') {
            throw new lti_exception("Invalid response_type. response_type: $responsetype. Must be 'id_token'.");
        }
        if (($responsemode = $requestdata['response_mode'] ?? '') !== 'form_post') {
            throw new lti_exception("Invalid response_mode. response_mode: $responsemode. Must be 'form_post'.");
        }
        if (($prompt = $requestdata['prompt'] ?? '') !== 'none') {
            throw new lti_exception("Invalid prompt. prompt: $prompt. Must be 'none'.");
        }
        if (empty($requestdata['nonce'])) {
            throw new lti_exception("Invalid nonce. nonce is a required field and cannot be empty.");
        }

        $clientid = $requestdata['client_id'] ?? '';
        if (empty($clientid) || $toolconfig->lti_clientid !== $clientid) {
            throw new lti_exception("Invalid client_id. client_id: $clientid. Does not match the tool registration value.");
        }

        $redirecturi = $requestdata['redirect_uri'] ?? '';
        $uris = array_map('trim', explode("\n", $toolconfig->lti_redirectionuris));
        if (!in_array($redirecturi, $uris)) {
            throw new lti_exception("Invalid redirect_uri. redirect_uri: $redirecturi. "
                ."Must match a redirect URI on the tool registration.");
        }
    }
}
