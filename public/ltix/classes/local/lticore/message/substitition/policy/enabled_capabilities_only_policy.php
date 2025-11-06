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

namespace core_ltix\local\lticore\message\substitition\policy;

/**
 * Policy ensuring that only select variables are resolved.
 *
 * @package    core_ltix
 * @copyright  2025 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enabled_capabilities_only_policy implements substitution_policy {

    /**
     * Ctor.
     *
     * @param array $enabledcapabilities The list of variables WITHOUT THEIR $-PREFIX for which resolving should be permitted.
     */
    public function __construct(protected array $enabledcapabilities) {
    }

    public function should_substitute(string $str): bool {
        return str_starts_with($str, '$') && in_array(substr($str, 1), $this->enabledcapabilities);
    }
}
