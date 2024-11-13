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

namespace core_ltix\local\lticore\models;

/**
 * Tests covering resource_link.
 *
 * @covers \core_ltix\local\lticore\models\resource_link
 * @package    core_ltix
 * @copyright  2024 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class resource_link_test extends \advanced_testcase {

    /**
     * Test creation using the bare minimum number of fields (those required).
     *
     * @dataProvider create_resource_link_provider
     * @return void
     */
    public function test_resource_link_creation(array $setdata, array $expecteddata, ?string$expectedexception = null): void {
        $this->resetAfterTest();

        $rl = new resource_link();

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
        // In cases where this is generated, just confirm it's a string.
        $this->assertIsString($rl->get('uuid'));
        $this->assertNotEmpty($rl->get('uuid'));
    }

    public function create_resource_link_provider(): array {
        global $CFG;
        require_once($CFG->dirroot . '/ltix/constants.php');

        return [
            'minimal set, required fields only' => [
                'setdata' => [
                    'typeid' => 4,
                    'component' => 'mod_lti',
                    'itemtype' => 'example_item_type',
                    'itemid' => 432,
                    'contextid' => 33,
                    'url' => (new \moodle_url('http://tool.example.com/my/resource'))->out(false),
                    'title' => 'My resource',
                ],
                'expecteddata' => [
                    'typeid' => 4,
                    'component' => 'mod_lti',
                    'itemtype' => 'example_item_type',
                    'itemid' => 432,
                    'contextid' => 33,
                    'legacyid' => null,
                    // Note: can't check UUID in this case since it's a randomly generated default, so it's omitted.
                    'url' => 'http://tool.example.com/my/resource',
                    'title' => 'My resource',
                    'text' => null,
                    'textformat' => FORMAT_MOODLE,
                    'gradable' => false,
                    'launchcontainer' => LTI_LAUNCH_CONTAINER_DEFAULT,
                    'customparams' => null,
                    'icon' => null,
                    'servicesalt' => null,
                ],
            ],
            'full set, all fields specified' => [
                'setdata' => [
                    'typeid' => 4,
                    'component' => 'mod_lti',
                    'itemtype' => 'example_item_type',
                    'itemid' => 432,
                    'contextid' => 33,
                    'legacyid' => 56001,
                    'uuid' => '123',
                    'url' => (new \moodle_url('http://tool.example.com/my/resource'))->out(false),
                    'title' => 'My resource',
                    'text' => '<div>This is a larger description of the resource link</div>',
                    'textformat' => FORMAT_HTML,
                    'gradable' => true,
                    'launchcontainer' => LTI_LAUNCH_CONTAINER_EMBED_NO_BLOCKS,
                    'customparams' => 'id=abc-123-fff',
                    'icon' => (new \moodle_url('http://tool.example.com/my/resource/icon.png'))->out(false),
                    'servicesalt' => '664c4261e47d85.53212526',
                ],
                'expecteddata' => [
                    'typeid' => 4,
                    'component' => 'mod_lti',
                    'itemtype' => 'example_item_type',
                    'itemid' => 432,
                    'contextid' => 33,
                    'legacyid' => 56001,
                    'uuid' => '123',
                    'url' => 'http://tool.example.com/my/resource',
                    'title' => 'My resource',
                    'text' => '<div>This is a larger description of the resource link</div>',
                    'textformat' => FORMAT_HTML,
                    'gradable' => true,
                    'launchcontainer' => LTI_LAUNCH_CONTAINER_EMBED_NO_BLOCKS,
                    'customparams' => 'id=abc-123-fff',
                    'icon' => 'http://tool.example.com/my/resource/icon.png',
                    'servicesalt' => '664c4261e47d85.53212526',
                ]
            ],
            'Exceptional case, invalid launchcontainer value' => [
                'setdata' => [
                    'typeid' => 4,
                    'contextid' => 33,
                    'url' => (new \moodle_url('http://tool.example.com/my/resource'))->out(false),
                    'title' => 'My resource',
                    'launchcontainer' => 999999, // Invalid launch container.
                ],
                'expecteddata' => [],
                'expectedexception' => \core\invalid_persistent_exception::class,
            ],
            'Exceptional case, invalid textformat value' => [
                'setdata' => [
                    'typeid' => 4,
                    'contextid' => 33,
                    'url' => (new \moodle_url('http://tool.example.com/my/resource'))->out(false),
                    'title' => 'My resource',
                    'textformat' => 999999, // Invalid text format.
                ],
                'expecteddata' => [],
                'expectedexception' => \core\invalid_persistent_exception::class,
            ],
        ];
    }
}
