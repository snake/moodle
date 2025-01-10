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

namespace local\lticore\repository;

use core_ltix\helper;
use core_ltix\local\lticore\repository\tool_registration_repository;

/**
 * Test class covering tool_registration_repository.
 *
 * @covers \core_ltix\local\lticore\repository\tool_registration_repository
 * @package    core_ltix
 * @copyright  2024 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_registration_repository_test extends \advanced_testcase {

    /**
     * Test the get_by_id method.
     *
     * @return void
     */
    public function test_get_by_id(): void {
        $this->resetAfterTest();

        // Create a tool type to fetch using the repo.
        $uniqueid = uniqid();
        $type = new \stdClass();
        $type->state = \core_ltix\constants::LTI_TOOL_STATE_CONFIGURED;
        $type->name = "Test tool $uniqueid";
        $type->description = "Example description $uniqueid";
        $type->toolproxyid = null;
        $type->baseurl = "https://tool.example.com/";
        $type->coursevisible = \core_ltix\constants::LTI_COURSEVISIBLE_ACTIVITYCHOOSER;
        $config = new \stdClass();
        $config->lti_coursevisible = \core_ltix\constants::LTI_COURSEVISIBLE_ACTIVITYCHOOSER;
        $type->id = helper::add_type($type, $config);

        $registrationrepo = new tool_registration_repository();

        $registration = $registrationrepo->get_by_id($type->id);
        $this->assertInstanceOf(\stdClass::class, $registration);

        $this->assertNull($registrationrepo->get_by_id(0));
    }
}
