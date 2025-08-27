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

namespace core_ltix\local\lticore\message\request\builder\v1p3;

use core_ltix\constants;
use core_ltix\local\lticore\message\lti_message;
use core_ltix\local\lticore\message\request\builder\lti_request_builder;
use core_ltix\local\lticore\token\lti_token;
use core_ltix\local\ltiopenid\jwks_helper;

/**
 * Base class supporting creation of the initiate launch request for LTI 1p3 messages.
 *
 * Subclass this to create specific message type builders.
 *
 * {@link https://www.imsglobal.org/spec/security/v1p1#step-1-third-party-initiated-login.}
 *
 * @package    core_ltix
 * @copyright  2025 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class v1p3_launch_request_builder implements lti_request_builder {

    /**
     * Constructor.
     *
     * Note: For the value of $toolconfig, callers currently MUST pass in the result of \core_ltix\helper::get_type_type_config().
     * TODO fix the above dependency on get_type_type_config-formatted tool config in this class.
     *
     * @param \stdClass $toolconfig the tool configuration.
     * @param string $messagetype the string message type.
     * @param string $issuer the issuer
     * @param string $targetlinkuri
     * @param string $loginhint
     * @param array $roles any LTI roles the user should have
     * @param array $extraclaims any optional parameters, which will vary depending on the message type being implemented.
     */
    protected function __construct(
        protected \stdClass $toolconfig,
        protected string $messagetype,
        protected string $issuer,
        protected string $targetlinkuri,
        protected string $loginhint,
        protected array $roles = [],
        protected array $extraclaims = []
    ) {
    }

    public function build_message(): lti_message {
        // Standard claims trump extra claims.
        $claims = array_merge(
            $this->extraclaims,
            $this->get_lti_message_standard_claims(),
        );
        $ltitoken = new lti_token($claims);

        // Roles could differ depending on the placement, so must be left to the calling code and are therefore passed in.
        $ltitoken->add_claim(\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/roles', $this->roles);

        // Note: Single deployment model means the $tool->id IS the lti_deployment_id.
        $params = [
            'iss' => $this->issuer,
            'target_link_uri' => $this->targetlinkuri,
            'login_hint' => $this->loginhint,
            'lti_message_hint' => $ltitoken->to_jwt(
                privatekey: jwks_helper::get_private_key()['key'],
                kid: jwks_helper::get_private_key()['kid']
            ),
            'client_id' => $this->toolconfig->lti_clientid,
            'lti_deployment_id' => $this->toolconfig->typeid,
        ];
        return new lti_message($this->toolconfig->lti_initiatelogin, $params);
    }

    /**
     * Get those claims used in all lti messages and which are required.
     *
     * Must be claims that are generic and used in all lti messages. Note: this includes things like version, deployment_id and
     * others which, despite not being listed as applicable to all message types in the core spec, in practice, behave as such.
     *
     * @return array the array of standard claims.
     */
    final protected function get_lti_message_standard_claims(): array {
        $prefix = constants::LTI_JWT_CLAIM_PREFIX;
        return [
            'tool_registration_id' => $this->toolconfig->typeid, // Note: This is a Moodle-specific claim.
            'iss' => $this->issuer,
            'aud' => $this->toolconfig->lti_clientid,
            "$prefix/claim/message_type" => $this->messagetype, // https://www.imsglobal.org/spec/lti/v1p3#message-type-and-schemas.
            "$prefix/claim/deployment_id" => $this->toolconfig->typeid, // Used in every message.
            "$prefix/claim/version" => constants::LTI_VERSION_1P3,
            "nonce" => bin2hex(random_string(10)), // Uniqueness of the message hint payload from request to request.
        ];
    }
}
