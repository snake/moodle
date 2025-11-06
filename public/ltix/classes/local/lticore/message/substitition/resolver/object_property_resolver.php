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
 * Resolves variable values using an input:object-property map.
 *
 * @package    core_ltix
 * @copyright  2025 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class object_property_resolver implements variable_resolver {

    protected array $valuemap;

    protected array $map;

    public function __construct(
        array $map,
    ) {
        // Filter the map, removing any non-object-prop resolutions e.g. keep only ['My.Var' => '$Obj->x']
        $this->map = array_filter($map, function($value) {
            return str_starts_with($value, '$');
        });
    }

    protected function build_value_map(resolve_context $resolvecontext): void {

        // Fetch relevant objects from the resolve context.
        if (!empty($resolvecontext->ltiuser)) {
            global $USER;
            $user = $USER->id == $resolvecontext->ltiuser->id ? $USER : \core_user::get_user($resolvecontext->ltiuser->id);
        }

        // Contexts residing under a course allow for resolution of course object properties. Not all contexts will support this.
        if (($coursecontext = $resolvecontext->context->get_course_context(false)) !== false) {
            $course = get_course($coursecontext->instanceid);
        }

        $objects = [
            ...(isset($user) ? ['USER' => $user]: []),
            ...(isset($course) ? ['COURSE' => $course]: [])
        ];

        // Building an internal input:output map of the form: ['Variable.name' => 'value']
        // To get to that:
        // - start with the map (capabilities => source values)
        // - take the source objects array which must look like: ['Obj' => $objinstance]
        // - Now, for every value in map, e.g. '$Obj->x':
        //  - grab the key (e.g. 'Obj') and the prop (e.g. 'x').
        //  - using the key, get the object instance from $objects[$key]
        //  - set the internal map value to instance->prop or null if not exists.
        // Resulting in:
        // ['My.var' => <the value of $objinstance->x>]
        $valuemap = $this->map;
        $valuemap = array_map(function($value) use ($objects) {
            $pieces = explode('->', substr($value, 1), 2);
            $objname = $pieces[0];
            $objprop = $pieces[1];
            $obj = $objects[$objname] ?? null;
            if ($obj) {
                return $obj->$objprop ?? null;
            }
            return null;
        }, $valuemap);

        $this->valuemap = $valuemap;
    }

    public function resolve(string $str, resolve_context $resolvecontext): ?string {
        $this->build_value_map($resolvecontext);

        if (!str_starts_with($str, "$")) {
            return null;
        }
        return $this->valuemap[substr($str, 1)] ?? null;
    }
}
