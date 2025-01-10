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
//
// This file is part of BasicLTI4Moodle
//
// BasicLTI4Moodle is an IMS BasicLTI (Basic Learning Tools for Interoperability)
// consumer for Moodle 1.9 and Moodle 2.0. BasicLTI is a IMS Standard that allows web
// based learning tools to be easily integrated in LMS as native ones. The IMS BasicLTI
// specification is part of the IMS standard Common Cartridge 1.1 Sakai and other main LMS
// are already supporting or going to support BasicLTI. This project Implements the consumer
// for Moodle. Moodle is a Free Open source Learning Management System by Martin Dougiamas.
// BasicLTI4Moodle is a project iniciated and leaded by Ludo(Marc Alier) and Jordi Piguillem
// at the GESSI research group at UPC.
// SimpleLTI consumer for Moodle is an implementation of the early specification of LTI
// by Charles Severance (Dr Chuck) htp://dr-chuck.com , developed by Jordi Piguillem in a
// Google Summer of Code 2008 project co-mentored by Charles Severance and Marc Alier.
//
// BasicLTI4Moodle is copyright 2009 by Marc Alier Forment, Jordi Piguillem and Nikolas Galanis
// of the Universitat Politecnica de Catalunya http://www.upc.edu
// Contact info: Marc Alier Forment granludo @ gmail.com or marc.alier @ upc.edu.

namespace local\lticore\models;

use core_ltix\local\lticore\models\lti_types_config;

/**
 * Tests covering lti_types_config.
 *
 * @covers \core_ltix\local\lticore\models\lti_types_config
 * @package    core_ltix
 * @copyright  2024 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lti_types_config_test extends \advanced_testcase {
    /**
     * Test creation.
     *
     * @dataProvider create_lti_types_config__provider
     * @param array $setdata the data to set before create is called.
     * @param array $expecteddata the expected data on the persistent, post create.
     * @param string|null $expectedexception if an exception is expected, the exception type, else null.
     * @return void
     */
    public function test_lti_types_creation(array $setdata, array $expecteddata, ?string$expectedexception = null): void{
        $this->resetAfterTest();

        $rl = new lti_types_config();
        $timenow = time();

        if (!is_null($expectedexception)) {
            $this->expectException($expectedexception);
        }
        foreach ($setdata as $name => $value) {
            $rl->set($name, $value);
        }
        $rl->save();

        foreach ($expecteddata as $name => $value) {
            $this->assertEquals($value, $rl->get($name));
        }

        $this->assertGreaterThanOrEqual($timenow, $rl->get('timecreated'));
        $this->assertGreaterThanOrEqual($timenow, $rl->get('timemodified'));
    }
}
