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

namespace core_ltix\local\lticore;

use core_ltix\helper;
use core_ltix\local\lticore\message\lti_message;
use core_ltix\local\lticore\message\lti_message_base;
use core_ltix\local\lticore\token\lti_token;
use core_ltix\local\ltiopenid\jwks_helper;

/**
 * Class encapsulating the creation of the launch request.
 *
 * @package    core_ltix
 * @copyright  2024 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lti_launch_request_builder {

    /**
     * Build a launch request, the first request to send to the tool's initiate login endpoint.
     *
     * Note: For the value of $tool MUST pass in the result of \core_ltix\helper::get_type_type_config
     *
     * @param \stdClass $toolconfig
     * @param string $messagetype
     * @param string $issuer
     * @param string $targetlinkuri
     * @param string $loginhint
     * @param array $roles
     * @param array $extraclaims
     * @return lti_message_base
     */
    public function build_launch_request(
        \stdClass $toolconfig,
        string $messagetype,
        string $issuer,
        string $targetlinkuri,
        string $loginhint,
        array $roles = [],
        array $extraclaims = []
    ): lti_message_base {

        // TODO Ideally, another object should build the token.
        //  A rough breakdown of the types of data included in a token and where that data comes from:
        //  1. message types, as defined in the various spec docs, can require claims that apply to that message type only
        //  2. services can include claims based on message types (there is existing code for this)
        //    - service::get_launch_parameters()
        //    - A service may wish to include a claim for ANY message to the tool, and that's valid. e.g. AGS claim.
        //  3. custom claims may be supported, depending on message type. Substitution needs to take place here too.
        //    - build_custom_parameters() etc.
        //      - services are also allowed to take part in substitution.
        //    - some message types require custom claims from other message types. E.g. subreview requires resourcelink custom params.
        //  Of the above, none make sense to implement here.
        //  1 is claims that only apply to one specific message type.
        //  2 is claims that may apply to certain message types (several, all, only 1), but may require message-type-specific data,
        //   not available here (e.g. details of a resource link might be required to add the claim to a particular message type).
        //   In this case, we'd have those details when building an LtiResourceLinkRequest message, but not when deep linking prior.
        //  3 is claims that may or may not be present for the given message type, so needs to be handled for the types that support
        //  it.
        //  1, 2 and 3 should be handled in the subclasses.

        // Create the partially complete launch token. This will be finalised with user claims during auth.
        // TODO: Can we get the standard claims unformatted, then format?
        $claims = array_merge(
            helper::get_lti_message_standard_claims($toolconfig, $messagetype, $issuer),
            $extraclaims,
        );
        $ltitoken = new lti_token($claims);

        // Roles could differ depending on the placement, so must be left to the individual message type/passed in.
        $ltitoken->add_claim(\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/roles', $roles);

        // Note: Single deployment model means the $tool->id IS the deployment id.
        $params = [
            'iss' => $issuer,
            'target_link_uri' => $targetlinkuri,
            'login_hint' => $loginhint,
            'lti_message_hint' => $ltitoken->to_jwt(
                privatekey: jwks_helper::get_private_key()['key'],
                kid: jwks_helper::get_private_key()['kid']
            ),
            'client_id' => $toolconfig->lti_clientid,
            'lti_deployment_id' => $toolconfig->id,
        ];
        return new lti_message($toolconfig->lti_initiatelogin, $params);
    }
}
