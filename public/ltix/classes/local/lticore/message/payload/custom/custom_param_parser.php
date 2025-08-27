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

namespace core_ltix\local\lticore\message\payload\custom;

use core_ltix\helper;
use core_ltix\local\lticore\facades\service\launch_service_facade_interface;


// TODO: create an interface.
class custom_param_parser {

    public function __construct(
        protected array $sourcedatamap,
        protected launch_service_facade_interface $servicefacade,
        protected \core\context $context,
        protected ?\stdClass $user = null,
    ) {
    }

    // Logic overview: 4 ways it can be resolved, else it remains unchanged.
    // If present in the dictionary, either:
    // a) value is matched to a value in sourcedata or
    // b) value is matched to a global or, finally;
    // c) value is calculated (if dictionary entry is falsey).
    // If not present in the dictionary:
    // d) delegate to the servicefacade to attempt to resolve via the tool services.
//    public function parse_all(array $customparams, array $sourcedata): array {
//        $parsed = [];
//        foreach ($customparams as $key => $paramvalue) {
//            $parsed[$key] = $this->parse($paramvalue, $sourcedata);
//        }
//        return $parsed;
//    }

    public function parse(string $customparam, array $sourcedata): string {
        $value = $customparam;
        if (substr($value, 0, 1) == '\\') {
            $value = substr($value, 1);
        } else if (substr($value, 0, 1) == '$') {
            $value1 = substr($value, 1);
            if (array_key_exists($value1, $this->sourcedatamap)) {
                $value = $this->resolve_from_map($value1, $sourcedata);
            } else {
                // Substitution for params defined in services: allow services to resolve these.
                $value = $this->services_parse_custom_param($value);
            }
        }
        return $value;
    }

    protected function resolve_from_map(string $paramvalue, array $sourcedata): string {
        $val = $this->sourcedatamap[$paramvalue];
        if ($val) {
            if (substr($val, 0, 1) != '$') {
                $value = $sourcedata[$val] ?? "$".$paramvalue; // Resolve, if present, from source data, else leave unresolved.
            } else {
                $valarr = explode('->', substr($val, 1), 2);
                // Map entries that refer to course or user objects. e.g. ['key' => '$USER->username'].
                // Resolve using the respective instance vars, if possible.
                if ($valarr[0] == 'USER') {
                    $value = $this->user->{$valarr[1]} ?? "$".$paramvalue;
                } elseif ($valarr[0] == 'COURSE') {
                    // Resolve course from the context in which substitution is taking place:
                    // If the context can resolve a parent course, then use that, else skip substitution for the param.
                    /** @var \core\context\course $coursecontext */
                    if (($coursecontext = $this?->context->get_course_context()) !== null) {
                        $course = get_course($coursecontext->instanceid);
                        $value = $course->{$valarr[1]} ?? "$".$paramvalue;
                    }
                }
                $value = str_replace('<br />' , ' ', $value);
                $value = str_replace('<br>' , ' ', $value);
                $value = format_string($value);
            }
        } else {
            $value = $this->resolve_calculated($paramvalue);
        }
        return $value;
    }

    protected function services_parse_custom_param($val): string {
        return $this->servicefacade->parse_custom_param_value($val);
    }

    /**
     * When the param value is calculated and not fetched from sourcedata or servicedata.
     *
     * @param string $value
     * @return null|string
     */
    protected function resolve_calculated(string $value): ?string {

        /** @var \core\context\course $coursecontext */
        if (($coursecontext = $this?->context->get_course_context()) !== null) {
            $course = get_course($coursecontext->instanceid);

            switch ($value) {
                case 'Moodle.Person.userGroupIds':
                    if (!empty($this->user)) {
                        return implode(",", groups_get_user_groups($course->id, $this->user->id)[0]);
                    }
                    break;
                case 'Context.id.history':
                    return implode(",", helper::get_course_history($course));
                case 'CourseSection.timeFrame.begin':
                    if (empty($course->startdate)) {
                        return "";
                    }
                    $dt = new \DateTime("@$course->startdate", new \DateTimeZone('UTC'));
                    return $dt->format(\DateTime::ATOM);
                case 'CourseSection.timeFrame.end':
                    if (empty($course->enddate)) {
                        return "";
                    }
                    $dt = new \DateTime("@$course->enddate", new \DateTimeZone('UTC'));
                    return $dt->format(\DateTime::ATOM);
            }
        }
        return null;
        // TODO \core_ltix\helper::calculate_custom_parameter() returns null in this case too,
        //  but what happens on a null return? check ltiplatform...
        //  maybe safer to return empty string but confirm what happens during a launch...to confirm if nulls are sent and if valid.
    }

}
