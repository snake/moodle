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

namespace core_ltix\local\lticore\message\substitition;

use core\context;
use core_ltix\local\lticore\facades\service\launch_service_facade_interface;
use core_ltix\local\lticore\message\substitition\policy\enabled_capabilities_only_policy;
use core_ltix\local\lticore\message\substitition\policy\substitute_all_policy;
use core_ltix\local\lticore\message\substitition\policy\substitute_enabled_capabilities_policy;
use core_ltix\local\lticore\message\substitition\resolver\calculated_course_variable_resolver;
use core_ltix\local\lticore\message\substitition\resolver\calculated_variable_resolver;
use core_ltix\local\lticore\message\substitition\resolver\composite_chain_resolver;
use core_ltix\local\lticore\message\substitition\resolver\map_variable_resolver;
use core_ltix\local\lticore\message\substitition\resolver\object_property_resolver;
use core_ltix\local\lticore\message\substitition\resolver\service_variable_resolver;

/**
 * LTI-v1px-specific variable substitution implementation.
 *
 * Wraps variable_substitutor, pre-wiring the relevant policies and resolvers.
 *
 * LTI v1p1 and v1p3 substitution is unconditional, unlike v2p0. Additionally, services can substitute variables.
 *
 * @package    core_ltix
 * @copyright  2025 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class v1px_variable_substitutor {

    /** @var variable_substitutor $substitutor internally wired instance handling substitution. */
    protected variable_substitutor $substitutor;

    /**
     * Ctor.
     *
     * @param array $sourcedata
     * @param context $context
     * @param launch_service_facade_interface $launchservicefacade
     * @param \stdClass|null $user
     */
    public function __construct(
        array $sourcedata,
        context $context,
        launch_service_facade_interface $launchservicefacade,
        ?\stdClass $user = null,
    ) {

        $map = \core_ltix\helper::get_capabilities();

        // Contexts residing under a course allow for resolution of course object properties. Not all contexts will support this.
        if (($coursecontext = $context->get_course_context(false)) !== false) {
            $course = get_course($coursecontext->instanceid);
        }

        $this->substitutor = new variable_substitutor(
            new substitute_all_policy(
            ),
            new composite_chain_resolver([
                new map_variable_resolver($map, $sourcedata),
                new object_property_resolver(
                    $map,
                    [
                        ...(isset($user) ? ['USER' => $user]: []),
                        ...(isset($course) ? ['COURSE' => $course]: [])
                    ]
                ),
                ...(isset($course) ? [new calculated_course_variable_resolver($course)] : []),
                ...(isset($user) && isset($course) ? [new calculated_user_variable_resolver($user, $course)] : []),
                new service_variable_resolver(
                    $launchservicefacade
                )
            ])
        );
    }

    /**
     * Perform substitution for an array of substitution parameters.
     *
     * @param array $params the array of substitution params (keys don't matter, they are not changed)
     * @return array the array once value substitution has taken place.
     */
    public function substitute(array $params): array {
        return $this->substitutor->substitute($params);
    }
}
