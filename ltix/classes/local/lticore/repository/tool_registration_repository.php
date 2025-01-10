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

namespace core_ltix\local\lticore\repository;

/**
 * Repository for fetching tool registrations.
 *
 * @package    core_ltix
 * @copyright  2024 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_registration_repository {

    /**
     * Get a registration by id.
     *
     * @param int $id the id of the registration
     * @return object|null the registration object or null if not found.
     */
    public function get_by_id(int $id): ?object {
        // TODO: Replace the get_type_type_config call (which returns a stdClass) with an object representation of a registration.
        try {
            return (object) \core_ltix\helper::get_type_type_config($id);
        } catch (\Throwable $th) {
            return null;
        }
    }
}
