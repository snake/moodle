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

namespace core_ltix\local\lticore\message\substitition\resolver;

/**
 * Class implementing resolution of calculated course variables during parameter expansion.
 *
 * @package    core_ltix
 * @copyright  2025 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class calculated_course_variable_resolver implements variable_resolver {

    public function resolve(string $str, resolve_context $resolvecontext): ?string {
        if (!str_starts_with($str, "$")) {
            return null;
        }
        $str = substr($str, 1);

        // Contexts residing under a course allow for resolution of course object properties. Not all contexts will support this.
        if (($coursecontext = $resolvecontext->context->get_course_context(false)) !== false) {
            $course = get_course($coursecontext->instanceid);
        }

        switch ($str) {
            case 'Context.id.history':
                return implode(",", \core_ltix\helper::get_course_history($course));
            case 'CourseSection.timeFrame.begin':
                if (empty($course->startdate)) {
                    return "";
                }
                $dt = new \DateTime(strval($course->startdate), new \DateTimeZone('UTC'));
                return $dt->format(\DateTime::ATOM);
            case 'CourseSection.timeFrame.end':
                if (empty($course->enddate)) {
                    return "";
                }
                $dt = new \DateTime("@$course->enddate", new \DateTimeZone('UTC'));
                return $dt->format(\DateTime::ATOM);
            default:
                break;
        }

        return null;
    }
}
