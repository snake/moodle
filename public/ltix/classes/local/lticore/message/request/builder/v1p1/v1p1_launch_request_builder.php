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

namespace core_ltix\local\lticore\message\request\builder\v1p1;

use core_ltix\local\lticore\message\lti_message;
use core_ltix\local\lticore\message\lti_message_base;
use core_ltix\local\lticore\message\request\builder\lti_request_builder;
use core_ltix\oauth_helper;

/**
 * Base class handling the creation of launch request messages for all LTI 1p1 messages.
 *
 * Subclass to create request builders for specific message types.
 *
 * @package    core_ltix
 * @copyright  2025 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class v1p1_launch_request_builder implements lti_request_builder {

    /**
     * Constructor.
     *
     * @param \stdClass $toolconfig the tool configuration.
     * @param string $messagetype the string message type, in 1p1 terms.
     * @param string $launchurl the URL to launch
     * @param array $roles any LTI roles the user should have
     * @param array $extraparams any optional parameters, which will vary depending on the message type being implemented.
     */
    protected function __construct(
        protected \stdClass $toolconfig,
        protected string $messagetype,
        protected string $launchurl,
        protected array $roles = [],
        protected array $extraparams = []
    ) {
    }

    public function build_message(): lti_message_base {
        $roles = [
            'roles' => implode(',', $this->roles),
        ];
        $params = array_merge($this->get_required_launch_params(), $roles);

        // Required params trump extra params.
        $params = array_merge($this->extraparams, $params);

        // TODO: better error handling for improperly set/missing keys.
        [$consumerkey, $secret] = $this->get_signing_keys();

        // TODO: here is probably also the time to add the required 'oauth_callback' => 'about:blank' to $params to be compliant
        //  with 1.0a OAuth.

        $params = oauth_helper::sign_parameters($params, $this->launchurl, 'POST', $consumerkey, $secret);

        return new lti_message($this->launchurl, $params);
    }

//    public function build_launch_request(
//        \stdClass $toolconfig,
//        string $messagetype,
//        string $launchurl,
//        array $roles = [],
//        array $extraparams = []
//    ): lti_message {
//
//        $requiredparams = $this->get_required_launch_params($messagetype);
//
//        // Append any roles.
//        $roles = [
//            'roles' => implode(',', $roles),
//        ];
//        $params = array_merge($requiredparams, $roles);
//
//        $params = array_merge($extraparams, $params);
//
//        [$consumerkey, $secret] = $this->get_signing_keys($toolconfig);
//        $params = \core_ltix\oauth_helper::sign_parameters($params, $launchurl, 'POST', $consumerkey, $secret);
//
//        return new lti_message($launchurl, $params);
//    }

    /**
     * Get the signing keys from tool config.
     *
     * @return array the array of [key, secret].
     */
    protected function get_signing_keys(): array {
        $key = !empty($this->toolconfig->lti_resourcekey) ? $this->toolconfig->lti_resourcekey : '';
        $secret = !empty($this->toolconfig->lti_password) ? $this->toolconfig->lti_password : '';

        return [$key, $secret];
    }

    /**
     * Get those launch params which are required for any LTI 1p1 message.
     *
     * @return array the array of required params.
     */
    protected function get_required_launch_params(): array {
        return [
            'lti_version' => \core_ltix\constants::LTI_VERSION_1,
            'lti_message_type' => $this->messagetype,
        ];
    }
}
