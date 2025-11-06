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
 * Resolves variable values based on a map, and sourcedata.
 *
 * The relationship between map and source data is explained via the example below.
 *
 * Map is of the form:
 * ['Context.title' => 'context_title']
 * Mapping a $-prefixed variable, e.g. $Context.title in the above, to a key in sourcedata.
 *
 * Sourcedata is of the form:
 * ['context_title' = 'An example context']
 *
 * Thus, if resolve() were called with $str='$Context.id', the resolved value would be 'An example context'.
 *
 * If the $str does not exist in map, or the resulting key does not exist in sourcedata, then null would be returned.
 *
 * @package    core_ltix
 * @copyright  2025 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class map_variable_resolver implements variable_resolver {

    /**
     * Ctor.
     *
     * @param array $map the map of input:sourcedatakey
     */
    public function __construct(
        protected array $map,
    ) {
    }

    public function resolve(string $str, resolve_context $resolvecontext): ?string {
        if (!str_starts_with($str, '$')) {
            return null;
        }
        $str = substr($str, 1);

        if (array_key_exists($str, $this->map)) {
            $sourcedatakey = $this->map[$str];
            if (array_key_exists($sourcedatakey, $resolvecontext->sourcedata)) {
                return $resolvecontext->sourcedata[$sourcedatakey];
            }
        }

        return null;
    }
}
