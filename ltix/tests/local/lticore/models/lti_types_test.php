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

use core_ltix\local\lticore\models\lti_types;

/**
 * Tests covering lti_types.
 *
 * @covers \core_ltix\local\lticore\models\lti_types
 * @package    core_ltix
 * @copyright  2024 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lti_types_test extends \advanced_testcase {
    /**
     * Test creation.
     *
     * @dataProvider create_lti_types_provider
     * @param array $setdata the data to set before create is called.
     * @param array $expecteddata the expected data on the persistent, post create.
     * @param string|null $expectedexception if an exception is expected, the exception type, else null.
     * @return void
     */
    public function test_lti_types_creation(array $setdata, array $expecteddata, ?string$expectedexception = null): void {
        $this->resetAfterTest();

        $reg = new lti_types();
        $timenow = time();

        if (!is_null($expectedexception)) {
            $this->expectException($expectedexception);
        }
        foreach ($setdata as $name => $value) {
            $reg->set($name, $value);
        }
        $reg->save();

        $reg->get('config');

        foreach ($expecteddata as $name => $value) {
            $this->assertEquals($value, $reg->get($name));
        }

        $this->assertGreaterThanOrEqual($timenow, $reg->get('timecreated'));
        $this->assertGreaterThanOrEqual($timenow, $reg->get('timemodified'));
    }

    /**
     * Data provider for testing model creation.
     *
     * @return array the test data + expectations.
     */
    protected function create_lti_types_provider(): array {

        return [
            'LTI 1p3: required fields + LTI 1p3 required fields only' => [
                'setdata' => [
                    'name' => 'An example lti tool registration',
                    'baseurl' => 'https://tool.example.com/',
                    'course' => 33,
                    'ltiversion' => \core_ltix\constants::LTI_VERSION_1P3,
                    'clientid' => 'afaf1234',
                    'createdby' => 4,
                ],
                'expecteddata' => [
                    'name' => 'An example lti tool registration',
                    'baseurl' => 'https://tool.example.com/',
                    'tooldomain' => 'tool.example.com',
                    'state' => \core_ltix\constants::LTI_TOOL_STATE_PENDING,
                    'course' => 33,
                    'coursevisible' => \core_ltix\constants::LTI_COURSEVISIBLE_NO,
                    'ltiversion' => \core_ltix\constants::LTI_VERSION_1P3,
                    'clientid' => 'afaf1234',
                    'toolproxyid' => null,
                    'enabledcapability' => null,
                    'parameter' => null,
                    'icon' => null,
                    'secureicon' => null,
                    'createdby' => 4,
                    'description' => null,
                ],
            ],
            'LTI 1p3: full expected set, all fields specified' => [
                'setdata' => [
                    'name' => 'An example lti tool registration',
                    'baseurl' => 'https://tool.example.com/',
                    'tooldomain' => 'example.com', // Note: will be overridden by the baseurl domain.
                    'state' => \core_ltix\constants::LTI_TOOL_STATE_CONFIGURED,
                    'course' => 33,
                    'coursevisible' => \core_ltix\constants::LTI_COURSEVISIBLE_ACTIVITYCHOOSER,
                    'ltiversion' => \core_ltix\constants::LTI_VERSION_1P3,
                    'clientid' => 'afaf1234',
                    'icon' => 'https://tool.example.com/lti/icons/icon.png',
                    'secureicon' => 'https://tool.example.com/lti/icons/icon.png',
                    'createdby' => 4,
                    'description' => 'Simple description of the tool',
                ],
                'expecteddata' => [
                    'name' => 'An example lti tool registration',
                    'baseurl' => 'https://tool.example.com/',
                    'tooldomain' => 'tool.example.com', // See note above re domain override.
                    'state' => \core_ltix\constants::LTI_TOOL_STATE_CONFIGURED,
                    'course' => 33,
                    'coursevisible' => \core_ltix\constants::LTI_COURSEVISIBLE_ACTIVITYCHOOSER,
                    'ltiversion' => \core_ltix\constants::LTI_VERSION_1P3,
                    'clientid' => 'afaf1234',
                    'toolproxyid' => null,
                    'enabledcapability' => null,
                    'parameter' => null,
                    'icon' => 'https://tool.example.com/lti/icons/icon.png',
                    'secureicon' => 'https://tool.example.com/lti/icons/icon.png',
                    'createdby' => 4,
                    'description' => 'Simple description of the tool',
                ],
            ],
            'LTI 1p3: required fields present, missing clientid which is required for 1p3' => [
                'setdata' => [
                    'name' => 'An example lti tool registration',
                    'baseurl' => 'https://tool.example.com/',
                    'course' => 33,
                    'ltiversion' => \core_ltix\constants::LTI_VERSION_1P3,
                    'createdby' => 4,
                ],
                'expecteddata' => [],
                'expectedexception' => \core\invalid_persistent_exception::class,
            ],
            'LTI 1p0, required fields' => [
                'setdata' => [
                    'name' => 'An example lti tool registration',
                    'baseurl' => 'https://tool.example.com/',
                    'tooldomain' => 'example.com',
                    'course' => 33,
                    'ltiversion' => \core_ltix\constants::LTI_VERSION_1,
                    'createdby' => 4,
                ],
                'expecteddata' => [
                    'name' => 'An example lti tool registration',
                    'baseurl' => 'https://tool.example.com/',
                    'tooldomain' => 'tool.example.com',
                    'state' => \core_ltix\constants::LTI_TOOL_STATE_PENDING,
                    'course' => 33,
                    'coursevisible' => \core_ltix\constants::LTI_COURSEVISIBLE_NO,
                    'ltiversion' => \core_ltix\constants::LTI_VERSION_1,
                    'clientid' => null,
                    'toolproxyid' => null,
                    'enabledcapability' => null,
                    'parameter' => null,
                    'icon' => null,
                    'secureicon' => null,
                    'createdby' => 4,
                    'description' => null,
                ],
            ],
            'LTI 2p0: required fields + LTI 2p0 required fields only' => [
                'setdata' => [
                    'name' => 'An example lti tool registration',
                    'baseurl' => 'https://tool.example.com/',
                    'tooldomain' => 'example.com',
                    'course' => 33,
                    'ltiversion' => \core_ltix\constants::LTI_VERSION_2,
                    'createdby' => 4,
                    'toolproxyid' => 22,
                ],
                'expecteddata' => [
                    'name' => 'An example lti tool registration',
                    'baseurl' => 'https://tool.example.com/',
                    'tooldomain' => 'tool.example.com',
                    'state' => \core_ltix\constants::LTI_TOOL_STATE_PENDING,
                    'course' => 33,
                    'coursevisible' => \core_ltix\constants::LTI_COURSEVISIBLE_NO,
                    'ltiversion' => \core_ltix\constants::LTI_VERSION_2,
                    'clientid' => null,
                    'toolproxyid' => 22,
                    'enabledcapability' => null,
                    'parameter' => null,
                    'icon' => null,
                    'secureicon' => null,
                    'createdby' => 4,
                    'description' => null,
                ],
            ],
            'tooldomain is a calculated value and is forced to match the domain of baseurl' => [
                'setdata' => [
                    'name' => 'An example lti tool registration',
                    'baseurl' => 'https://tool.example.com/',
                    'tooldomain' => 'moodle.org', // Will not be used. Instead, the domain from baseurl will be saved.
                    'course' => 33,
                    'ltiversion' => \core_ltix\constants::LTI_VERSION_1P3,
                    'clientid' => 'afaf1234',
                    'createdby' => 4,
                ],
                'expecteddata' => [
                    'name' => 'An example lti tool registration',
                    'baseurl' => 'https://tool.example.com/',
                    'tooldomain' => 'tool.example.com',
                    'state' => \core_ltix\constants::LTI_TOOL_STATE_PENDING,
                    'course' => 33,
                    'coursevisible' => \core_ltix\constants::LTI_COURSEVISIBLE_NO,
                    'ltiversion' => \core_ltix\constants::LTI_VERSION_1P3,
                    'clientid' => 'afaf1234',
                    'toolproxyid' => null,
                    'enabledcapability' => null,
                    'parameter' => null,
                    'icon' => null,
                    'secureicon' => null,
                    'createdby' => 4,
                    'description' => null,
                ],
            ],
            'Clientid is a 1p3 field and should cause an error is set for a 1p1 record' => [
                'setdata' => [
                    'name' => 'An example lti tool registration',
                    'baseurl' => 'https://tool.example.com/',
                    'course' => 33,
                    'ltiversion' => \core_ltix\constants::LTI_VERSION_1,
                    'clientid' => 'afaf1234',
                    'createdby' => 4,
                ],
                'expecteddata' => [],
                'expectedexception' => \core\invalid_persistent_exception::class,
            ],
            'Clientid is a 1p3 field and should cause an error is set for a 2p0 record' => [
                'setdata' => [
                    'name' => 'An example lti tool registration',
                    'baseurl' => 'https://tool.example.com/',
                    'course' => 33,
                    'ltiversion' => \core_ltix\constants::LTI_VERSION_2,
                    'clientid' => 'afaf1234',
                    'toolproxyid' => 22,
                    'createdby' => 4,
                ],
                'expecteddata' => [],
                'expectedexception' => \core\invalid_persistent_exception::class,
            ],
            'Toolproxyid is a 2p0 field and should cause an error is set for a 1p1 record' => [
                'setdata' => [
                    'name' => 'An example lti tool registration',
                    'baseurl' => 'https://tool.example.com/',
                    'course' => 33,
                    'ltiversion' => \core_ltix\constants::LTI_VERSION_1,
                    'toolproxyid' => 22,
                    'createdby' => 4,
                ],
                'expecteddata' => [],
                'expectedexception' => \core\invalid_persistent_exception::class,
            ],
            'Toolproxyid is a 2p0 field and should cause an error is set for a 1p3 record' => [
                'setdata' => [
                    'name' => 'An example lti tool registration',
                    'baseurl' => 'https://tool.example.com/',
                    'course' => 33,
                    'ltiversion' => \core_ltix\constants::LTI_VERSION_1P3,
                    'clientid' => 'afaf1234',
                    'toolproxyid' => 22,
                    'createdby' => 4,
                ],
                'expecteddata' => [],
                'expectedexception' => \core\invalid_persistent_exception::class,
            ],
            'Parameter is a 2p0 field and should cause an error is set for a 1p1 record' => [
                'setdata' => [
                    'name' => 'An example lti tool registration',
                    'baseurl' => 'https://tool.example.com/',
                    'course' => 33,
                    'ltiversion' => \core_ltix\constants::LTI_VERSION_1,
                    'parameter' => 'something=test',
                    'createdby' => 4,
                ],
                'expecteddata' => [],
                'expectedexception' => \core\invalid_persistent_exception::class,
            ],
            'Parameter is a 2p0 field and should cause an error is set for a 1p3 record' => [
                'setdata' => [
                    'name' => 'An example lti tool registration',
                    'baseurl' => 'https://tool.example.com/',
                    'course' => 33,
                    'ltiversion' => \core_ltix\constants::LTI_VERSION_1P3,
                    'clientid' => 'afaf1234',
                    'parameter' => 'something=test',
                    'createdby' => 4,
                ],
                'expecteddata' => [],
                'expectedexception' => \core\invalid_persistent_exception::class,
            ],
            'Enabledcapability is a 2p0 field and should cause an error is set for a 1p1 record' => [
                'setdata' => [
                    'name' => 'An example lti tool registration',
                    'baseurl' => 'https://tool.example.com/',
                    'course' => 33,
                    'ltiversion' => \core_ltix\constants::LTI_VERSION_1,
                    'enabledcapability' => "Context.id\nCourseSection.title",
                    'createdby' => 4,
                ],
                'expecteddata' => [],
                'expectedexception' => \core\invalid_persistent_exception::class,
            ],
            'Enabledcapability is a 2p0 field and should cause an error is set for a 1p3 record' => [
                'setdata' => [
                    'name' => 'An example lti tool registration',
                    'baseurl' => 'https://tool.example.com/',
                    'course' => 33,
                    'ltiversion' => \core_ltix\constants::LTI_VERSION_1P3,
                    'clientid' => 'afaf1234',
                    'enabledcapability' => "Context.id\nCourseSection.title",
                    'createdby' => 4,
                ],
                'expecteddata' => [],
                'expectedexception' => \core\invalid_persistent_exception::class,
            ],
        ];
    }
}
