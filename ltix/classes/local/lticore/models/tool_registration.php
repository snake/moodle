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

namespace core_ltix\local\lticore\models;

/**
 * Models a tool registration.
 *
 * Aggregates all tool-registration-centric data into a single container, and includes:
 * - tool registration (the lti_types record)
 * - all associated config (all lti_types_config records for the respective tool type).
 *
 * @package    core_ltix
 * @copyright  2024 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_registration {

    protected string $tooldomain;

    public function __construct(
        protected string $name,
        protected string $baseurl,
        protected string $state,
        protected int $courseid,

        // TODO: other mandatory fields...
        // TODO invariants, or let persistence layer deal with that?
    ) {
        // instance vars representing everything a registration can have.
        // must support invariants for 1.3, 1.1, 2.0.
    }
}
