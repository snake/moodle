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

/**
 * Substitutor encapsulating common placeholder substitution logic, and which does not impose specific LTI-version-related rules.
 *
 * Specific LTI-version-related substitution instances should create wrappers around this if they need additional control over
 * substitution.
 *
 * Common substitution logic includes:
 * - resolving a placeholder to a value in sourcedata
 * - resolving a placeholder to a property of a global ($USER and $COURSE supported).
 * - resolving a placeholder to a calculated value
 *
 * @package    core_ltix
 * @copyright  2025 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class common_parameter_substitutor implements parameter_substitutor_interface {

    /**
     * Constructor.
     *
     * @param array $sourcedatamap maps parameter keys (e.g. $User.id) to keys in the lookup data array (or to globals).
     * @param \core\context $context a context instance, used to resolve context values.
     * @param \stdClass|null $user user record, or null to prevent substitution to $USER->xxx values.
     */
    public function __construct(
        protected array $sourcedatamap,
        protected \core\context $context,
        protected ?\stdClass $user = null,
    ) {
    }

    /**
     * Substitute the variable in $customparam with its corresponding value.
     *
     * @param string $customparam
     * @param array $sourcedata
     * @return string
     */
    public function substitute(string $customparam, array $sourcedata): string {
        $value = $customparam;
        if (str_starts_with($value, '\\')) {
            $value = substr($value, 1);
        } else if (str_starts_with($value, '$')) {
            $value1 = substr($value, 1);
            if (array_key_exists($value1, $this->sourcedatamap)) {
                $value = $this->resolve_from_map($value1, $sourcedata);
            }
        }
        return $value;
    }

    /**
     * Resolve a variable to its corresponding dictionary or calculated value.
     *
     * This assumes the value exists as a key in {@link self::sourcedatamap}.
     *
     * There are 3 ways a variable can be resolved:
     * a) The value is matched to a value in sourcedata or
     * b) The value is matched to a global or, finally;
     * c) The value is calculated (if the dictionary entry is false-y).
     *
     * @param string $paramvalue the value to substitute
     * @param array $sourcedata array of ['key' => 'value'] pairs, which will be substituted if a param is mapped to the 'key'.
     * @return string the substituted/calculated value.
     */
    protected function resolve_from_map(string $paramvalue, array $sourcedata): string {
        $val = $this->sourcedatamap[$paramvalue];
        if ($val) {
            if (!str_starts_with($val, '$')) {
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
                    if (($coursecontext = $this?->context->get_course_context(false)) !== false) {
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
