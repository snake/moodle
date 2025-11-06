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

use core_ltix\local\lticore\facades\service\substitution_service_facade;

/**
 * Class implementing resolution of service variables during substitution parameter expansion.
 *
 * @package    core_ltix
 * @copyright  2025 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class service_variable_resolver implements variable_resolver {

    /**
     * Ctor.
     *
     * @param substitution_service_facade $servicefacade a service facade instance.
     */
    public function __construct(
        protected substitution_service_facade $servicefacade
    ) {
    }

    public function resolve(string $str, resolve_context $resolvecontext): ?string {
        if (!str_starts_with($str, "$")) {
            return null;
        }

        return $this->servicefacade->substitute($str);
    }
}
