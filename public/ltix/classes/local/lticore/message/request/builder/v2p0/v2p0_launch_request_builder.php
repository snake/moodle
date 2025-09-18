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

namespace core_ltix\local\lticore\message\request\builder\v2p0;

use core_ltix\local\lticore\exception\lti_exception;
use core_ltix\local\lticore\message\lti_message;
use core_ltix\local\lticore\message\lti_message_base;
use core_ltix\local\lticore\message\request\builder\lti_request_builder;
use core_ltix\oauth_helper;

/**
 * Base class handling the creation of launch request messages for all LTI 2p0 messages.
 *
 * Subclass to create request builders for specific message types.
 *
 * @package    core_ltix
 * @copyright  2025 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class v2p0_launch_request_builder implements lti_request_builder {

    /** @var object tool proxy instance. */
    protected object $proxy;

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
        // Note: tool proxy MUST be set manually on the passed tool config. It is not set when fetching tool config with other APIs.
        $toolproxy = $this->toolconfig->toolproxy ?? null;
        if (empty($toolproxy)) {
            throw new lti_exception('Error: Tool is missing Tool Proxy. Tool Proxy required for LTI-2p0 launches');
        }
        $this->proxy = $toolproxy;
    }

    public function build_message(): lti_message_base {
        $roles = [
            'roles' => implode(',', $this->roles),
        ];
        $params = array_merge($this->get_required_launch_params(), $roles);

        // Required params trump extra params.
        $params = array_merge($this->extraparams, $params);

        // Filter parameters depending on the tool's enabled LTI capabilities.
        $params = $this->enforce_tool_capabilities($params);

        $params = oauth_helper::sign_parameters($params, $this->launchurl, 'POST', $this->proxy->guid, $this->proxy->secret);

        return new lti_message($this->launchurl, $params);
    }

    /**
     * Filter the request params, excluding those which are controlled by capabilities currently not enabled on the tool.
     *
     * Any params which aren't controlled by capabilities will remain.
     *
     * @param array $params the launch params to filter.
     * @return array the filtered launch params.
     */
    protected function enforce_tool_capabilities(array $params): array {
        $allcapabilities = \core_ltix\helper::get_capabilities();
        $enabledcapabilities = explode("\n", $this->toolconfig->enabledcapability);
        // E.g. of $params: ['context_title' => 'some value'].
        // E.g. of $allcaps: ['Context.title' => 'context_title'].
        // E.g. of $enabledcaps: ['Context.title', 'Context.id'].
        return array_filter($params, function ($paramval, $paramkey) use ($allcapabilities, $enabledcapabilities) {
            $capkey = array_search($paramkey, $allcapabilities);
            return $capkey === false || in_array($capkey, $enabledcapabilities);
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Get those launch params which are required for any LTI 1p1 message.
     *
     * @return array the array of required params.
     */
    protected function get_required_launch_params(): array {
        return [
            'lti_version' => \core_ltix\constants::LTI_VERSION_2,
            'lti_message_type' => $this->messagetype,
            'oauth_callback' => 'about:blank', // Add oauth_callback to be compliant with the 1.0A spec.
        ];
    }
}
