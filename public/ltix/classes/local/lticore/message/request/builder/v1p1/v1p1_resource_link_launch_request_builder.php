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

use core_ltix\local\lticore\models\resource_link;

/**
 * Class handling the creation of resource link launch request messages for LTI 1p1.
 *
 * @package    core_ltix
 * @copyright  2025 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class v1p1_resource_link_launch_request_builder extends v1p1_launch_request_builder {

    /**
     * Constructor.
     *
     * @param \stdClass $toolconfig
     * @param resource_link $resourcelink
     * @param array $extraparams
     */
    public function __construct(
        protected \stdClass $toolconfig,
        protected resource_link $resourcelink,
        array $roles = [],
        array $extraparams = []
    ) {
        parent::__construct(
            toolconfig: $toolconfig,
            messagetype: 'basic-lti-launch-request',
            launchurl: $this->resolve_target_link_uri(),
            roles: $roles,
            // Required params take precedence over extra params.
            extraparams: array_merge($extraparams, $this->create_required_request_params())
        );
    }


//    public function build_resource_link_launch_request(
//        array $extraparams = [],
//    ) {
//
//        $requiredparams = $this->create_required_request_params();
//
//        $params = array_merge($extraparams, $requiredparams);
//
//        return parent::build_launch_request(
//            toolconfig: $this->toolconfig,
//            messagetype: 'basic-lti-launch-request',
//            launchurl: $this->resolve_target_link_uri(),
//            roles: [], // TODO:
//            extraparams: $params,
//        );
//    }

    /**
     * Resolve the target link URI via either the link, or the tool, in that order.
     *
     * @return string the target link URI.
     */
    protected function resolve_target_link_uri(): string {
        return $this->resourcelink->get('url') ?: $this->toolconfig->lti_toolurl;
    }

    /**
     * Adds required params for this message type.
     *
     * @return array the array of claims.
     */
    protected function create_required_request_params(): array {
        $description = $this->resourcelink->get('text');
        return [
            'resource_link_id' => $this->resourcelink->get('id'),
            'resource_link_title' => $this->resourcelink->get('title'),
            ...(!is_null($description) ? ['resource_link_description' => $description] : []),
        ];
    }

}
