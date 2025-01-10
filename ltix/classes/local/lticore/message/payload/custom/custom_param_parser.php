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

use core_ltix\local\lticore\facades\service\launch_service_facade_interface;

class custom_param_parser {

    public function __construct(
        protected array $sourcedatamap,
        protected launch_service_facade_interface $servicefacade,
        protected \stdClass $user,
    ) {
    }

    // Logic overview: 4 ways it can be resolved, else it remains unchanged.
    // If present in the dictionary, either:
    // a) value is matched ito a value in sourcedata or
    // b) value is matched to a global or finally;
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
        global $COURSE; // Yuck, but unavoidable since the map may reference globals like this.

        $val = $this->sourcedatamap[$paramvalue];
        if ($val) {
            if (substr($val, 0, 1) != '$') {
                $value = $sourcedata[$val] ?? "$".$paramvalue; // Resolve if present in source data, else leave unresolved.
            } else {
                $valarr = explode('->', substr($val, 1), 2);
                // Map entries like ['key' => '$USER->username'].
                // Where possible, resolve using instance vars instead of globals.
                if ($valarr[0] == 'USER') {
                    $value = $this->user->{$valarr[1]};
                } else {
                    $value = "{${$valarr[0]}->{$valarr[1]}}";
                }
                $value = str_replace('<br />' , ' ', $value);
                $value = str_replace('<br>' , ' ', $value);
                $value = format_string($value);
            }
        } else {
            $value = $this->calculate_custom_parameter($paramvalue);
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
        global $USER, $COURSE;

        switch ($value) {
            case 'Moodle.Person.userGroupIds':
                return implode(",", groups_get_user_groups($COURSE->id, $USER->id)[0]);
            case 'Context.id.history':
                return implode(",", self::get_course_history($COURSE));
            case 'CourseSection.timeFrame.begin':
                if (empty($COURSE->startdate)) {
                    return "";
                }
                $dt = new \DateTime("@$COURSE->startdate", new \DateTimeZone('UTC'));
                return $dt->format(\DateTime::ATOM);
            case 'CourseSection.timeFrame.end':
                if (empty($COURSE->enddate)) {
                    return "";
                }
                $dt = new \DateTime("@$COURSE->enddate", new \DateTimeZone('UTC'));
                return $dt->format(\DateTime::ATOM);
        }
        return null; // TODO what happens on a null return - check ltiplatform...
    }

}
