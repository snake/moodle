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
 * Class implementing resolution of calculated user variables during parameter expansion.
 *
 * @package    core_ltix
 * @copyright  2025 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class calculated_user_variable_resolver implements variable_resolver {

    public function resolve(string $str, resolve_context $resolvecontext): ?string {
        if (!str_starts_with($str, "$")) {
            return null;
        }
        if (empty($resolvecontext->ltiuser)) {
            return null;
        }
        $str = substr($str, 1);

        global $USER;
        $user = $USER->id == $resolvecontext->ltiuser->id ? $USER : \core_user::get_user($resolvecontext->ltiuser->id);

        // Contexts residing under a course allow for resolution of course object properties. Not all contexts will support this.
        if (($coursecontext = $resolvecontext->context->get_course_context(false)) !== false) {
            $course = get_course($coursecontext->instanceid);
        }

        switch ($str) {
            case 'Moodle.Person.userGroupIds':
                if (!empty($user) && !empty($course)) {
                    return implode(",", groups_get_user_groups($course->id, $user->id)[0]);
                }
                break;
            default:
                break;
        }

        return null;
    }
}
