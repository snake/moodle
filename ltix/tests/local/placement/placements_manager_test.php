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

namespace core_ltix\local\placement;

use core\tests\fake_plugins_test_trait;
use core_ltix\constants;

/**
 * Placements helper tests.
 *
 * @covers \core_ltix\local\placement\placements_manager
 * @package    core_ltix
 * @copyright  2025 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class placements_manager_test extends \advanced_testcase {

    use fake_plugins_test_trait;

    /**
     * Test registration of component placement types during install/upgrade, via the update_placement_types() method.
     *
     * @return void
     * @runInSeparateProcess
     */
    public function test_update_placement_types(): void {
        $this->resetAfterTest();
        global $CFG, $DB;

        // Using a fake plugin mock, verify the lti placement types are registered.
        // See: lib/tests/fixtures/fakeplugins/fake/fullfeatured/db/lti.php, where placementtypes + handlers are defined.
        $this->add_mocked_plugintype('fake', "{$CFG->dirroot}/lib/tests/fixtures/fakeplugins/fake", true);
        $this->add_mocked_plugin('fake', 'fullfeatured', "{$CFG->dirroot}/lib/tests/fixtures/fakeplugins/fake/fullfeatured");

        placements_manager::update_placement_types('fake_fullfeatured');
        $this->assertdebuggingcalledcount(1); // Expected, since one placement string is formatted incorrectly and is skipped.
        $this->assertEquals(3, $DB->count_records('lti_placement_type', ['component' => 'fake_fullfeatured']));

        // Next, mock the situation where a placement type has already been registered, and is now removed from lti.php config.
        $placementtypeid = $DB->insert_record('lti_placement_type', [
            'component' => 'fake_fullfeatured',
            'type' => 'fake_fullfeatured:thisonetobedeleted'
        ]);
        $DB->insert_record('lti_placement', ['placementtypeid' => $placementtypeid, 'toolid' => 101]);
        $this->assertEquals(1, $DB->count_records('lti_placement_type',
            ['component' => 'fake_fullfeatured', 'type' => 'fake_fullfeatured:thisonetobedeleted']));
        $this->assertEquals(1, $DB->count_records('lti_placement', ['toolid' => 101]));

        // The placement type and any placements using it should have been deleted.
        placements_manager::update_placement_types('fake_fullfeatured');
        $this->assertdebuggingcalledcount(1); // Expected, since one placement string is formatted incorrectly.
        $placementtypes = $DB->get_records_menu('lti_placement_type', ['component' => 'fake_fullfeatured']);
        $this->assertCount(3, $placementtypes);
        $this->assertContains('fake_fullfeatured:myfirstplacementtype', $placementtypes);
        $this->assertContains('fake_fullfeatured:anotherplacementtype', $placementtypes);
        $this->assertContains('fake_fullfeatured:thirdplacementtype', $placementtypes);
        $this->assertEquals(0, $DB->count_records('lti_placement_type',
            ['component' => 'fake_fullfeatured', 'type' => 'fake_fullfeatured:thisonetobedeleted']));
        $this->assertEquals(0, $DB->count_records('lti_placement', ['toolid' => 101]));
    }

    /**
     * Test fetching a deep linking placements component implementation.
     *
     * @return void
     * @runInSeparateProcess
     */
    public function test_get_deeplinking_placement_handler(): void {
        $this->resetAfterTest();
        global $CFG;

        // Deep mock a fake plugin with lti placement stubs.
        $this->add_full_mocked_plugintype('fake', 'lib/tests/fixtures/fakeplugins/fake');
        $this->add_mocked_plugin('fake', 'fullfeatured', "{$CFG->dirroot}/lib/tests/fixtures/fakeplugins/fake/fullfeatured");
        placements_manager::update_placement_types('fake_fullfeatured');
        $this->assertdebuggingcalledcount(1);

        // Expect an instance of deeplinking_placement_handler.
        $placementinstance = placements_manager::get_instance()
            ->get_deeplinking_placement_instance('fake_fullfeatured:myfirstplacementtype');
        $this->assertdebuggingcalledcount(1);
        $this->assertInstanceOf(\core_ltix\local\placement\deeplinking_placement_handler::class, $placementinstance);
        $this->assertEquals('fake_fullfeatured\lti\placement\myfirstplacementtype', get_class($placementinstance));

        // Verify an exception is thrown when an invalid placement type string is used.
        try {
            placements_manager::get_instance()->get_deeplinking_placement_instance('fake_fullfeatured:THIS:ISINVALID');
            $this->fail('Exception expected for invalid placement type string.');
        } catch (\coding_exception $e) {
            $this->assertMatchesRegularExpression('/.*Invalid placement type.*/', $e->getMessage());
        }

        // Verify an exception is thrown when an implementation of deeplinking_placement cannot be found.
        try {
            placements_manager::get_instance()->get_deeplinking_placement_instance('fake_fullfeatured:not_a_class_name');
            $this->fail('Exception expected when placement is not implemented.');
        } catch (\Exception $e) {
            $this->assertEquals('codingerror', $e->errorcode);
        }

        // Verify a type error is seen, if the implementor returns the wrong type for the implementation.
        try {
            placements_manager::get_instance()->get_deeplinking_placement_instance('fake_fullfeatured:thirdplacementtype');
            $this->fail('Exception expected when placement is the wrong type.');
        } catch (\Exception $e) {
            $this->assertEquals('codingerror', $e->errorcode);
        }
    }

    /**
     * Test fetching all placements for a tool.
     *
     * @return void
     */
    public function test_get_tool_placements(): void {
        $this->resetAfterTest();

        /** @var \core_ltix_generator $ltigenerator */
        $ltigenerator = $this->getDataGenerator()->get_plugin_generator('core_ltix');
        $tool1id = $ltigenerator->create_tool_types([
            'name' => 'Example tool',
            'baseurl' => 'http://example.com/tool/1'
        ]);
        $tool2id = $ltigenerator->create_tool_types([
            'name' => 'Example tool',
            'baseurl' => 'http://example.com/tool/1'
        ]);

        // Two tools: one has two placements, the other has one.
        $placementtype1 = $ltigenerator->create_placement_type('core_ltix', 'core_ltix:myplacement');
        $placementtype2 = $ltigenerator->create_placement_type('fake_component', 'fake_component:anotherplacement');
        $placementtype3 = $ltigenerator->create_placement_type('mod_foo', 'mod_foo:fooplacement');
        $tool1placement1 = $ltigenerator->create_placement($tool1id, $placementtype1->id);
        $tool1placement2 = $ltigenerator->create_placement($tool1id, $placementtype2->id);
        $tool2placement1 = $ltigenerator->create_placement($tool2id, $placementtype3->id);

        // Tool 1 placements.
        $returnedtool1placements = placements_manager::get_tool_placements($tool1id);
        $this->assertCount(2, $returnedtool1placements);

        $returnedtool1placement1 = $returnedtool1placements[$tool1placement1->id];
        $this->assertEquals($placementtype1->type, $returnedtool1placement1->placementtype);
        $this->assertEquals($tool1id, $returnedtool1placement1->toolid);
        $this->assertEquals($tool1placement1->id, $returnedtool1placement1->id);

        $returnedtool1placement2 = $returnedtool1placements[$tool1placement2->id];
        $this->assertEquals($placementtype2->type, $returnedtool1placement2->placementtype);
        $this->assertEquals($tool1id, $returnedtool1placement2->toolid);
        $this->assertEquals($tool1placement2->id, $returnedtool1placement2->id);

        // Tool 2 placements.
        $returnedtool2placements = placements_manager::get_tool_placements($tool2id);
        $this->assertCount(1, $returnedtool2placements);

        $returnedtool2placement1 = $returnedtool2placements[$tool2placement1->id];
        $this->assertEquals($placementtype3->type, $returnedtool2placement1->placementtype);
        $this->assertEquals($tool2id, $returnedtool2placement1->toolid);
        $this->assertEquals($tool2placement1->id, $returnedtool2placement1->id);
    }

    /**
     * Test get_placement_status().
     * @return void
     */
    public function test_get_placement_status(): void {
        $this->resetAfterTest();

        /** @var \core_ltix_generator $ltigenerator */
        $ltigenerator = $this->getDataGenerator()->get_plugin_generator('core_ltix');

        $tool1id = $ltigenerator->create_tool_types([
            'name' => 'Example tool',
            'baseurl' => 'http://example.com/tool/1',
        ]);
        $tool2id = $ltigenerator->create_tool_types([
            'name' => 'Example tool',
            'baseurl' => 'http://example.com/tool/1',
            'lti_coursevisible' => constants::LTI_COURSEVISIBLE_NO,
        ]);

        $placementtype1 = $ltigenerator->create_placement_type('core_ltix', 'core_ltix:myplacement');
        $placementtype2 = $ltigenerator->create_placement_type('fake_component', 'fake_component:anotherplacement');
        $placementtype3 = $ltigenerator->create_placement_type('fake_component', 'fake_component:placementtwo');
        $placementtype4 = $ltigenerator->create_placement_type('mod_foo', 'mod_foo:fooplacement');

        $placementconfig1 = [
            'default_usage' => 'enabled',
            'supports_deep_linking' => 1,
            'deep_linking_url' => 'https://example.com/deep_link_launch',
        ];
        $placementconfig2 = [
            'default_usage' => 'disabled',
            'supports_deep_linking' => 0,
        ];
        $placementconfig3 = [
            'default_usage' => 'disabled',
            'supports_deep_linking' => 0,
        ];
        $placementconfig4 = [
            'default_usage' => 'enabled',
            'supports_deep_linking' => 0,
        ];
        $tool1placement1 = $ltigenerator->create_placement($tool1id, $placementtype1->id, $placementconfig1);
        $tool1placement2 = $ltigenerator->create_placement($tool1id, $placementtype2->id, $placementconfig2);
        $tool1placement3 = $ltigenerator->create_placement($tool1id, $placementtype3->id, $placementconfig3);
        $tool2placement1 = $ltigenerator->create_placement($tool2id, $placementtype4->id, $placementconfig4);

        // Set the placement status for placement 3 to ENABLED in the course context. This will override the config value.
        $course = $this->getDataGenerator()->create_course();
        $context = \core\context\course::instance($course->id);
        $ltigenerator->create_placement_status_in_context($tool1placement3->id, placement_status::ENABLED, $context->id);

        // Get an enabled status.
        $this->assertEquals(
            placement_status::ENABLED,
            placements_manager::get_placement_status($tool1placement1->id, $context->id)
        );

        // Get a disabled status.
        $this->assertEquals(
            placement_status::DISABLED,
            placements_manager::get_placement_status($tool1placement2->id, $context->id)
        );

        // Can get the status when status is set at the context level.
        $this->assertEquals(
            placement_status::ENABLED,
            placements_manager::get_placement_status($tool1placement3->id, $context->id)
        );

        // Can get the placement status even for a hidden tool.
        $this->assertEquals(
            placement_status::ENABLED,
            placements_manager::get_placement_status($tool2placement1->id, $context->id)
        );
    }

    /**
     * Test getting a list of enabled placement types in a context.
     *
     * @return void
     */
    public function test_get_enabled_placement_types_in_context(): void {
        $this->resetAfterTest();

        /** @var \core_ltix_generator $ltigenerator */
        $ltigenerator = $this->getDataGenerator()->get_plugin_generator('core_ltix');

        $tool1id = $ltigenerator->create_tool_types([
            'name' => 'Example tool',
            'baseurl' => 'http://example.com/tool/1',
            'lti_coursevisible' => constants::LTI_COURSEVISIBLE_PRECONFIGURED,
        ]);
        $tool2id = $ltigenerator->create_tool_types([
            'name' => 'Example tool 2',
            'baseurl' => 'http://example.com/tool/2',
            'lti_coursevisible' => constants::LTI_COURSEVISIBLE_PRECONFIGURED,
        ]);
        $tool3id = $ltigenerator->create_tool_types([
            'name' => 'Example tool 3 - HIDDEN',
            'baseurl' => 'http://example.com/tool/3',
            'lti_coursevisible' => constants::LTI_COURSEVISIBLE_NO,
        ]);

        // Create a couple of placements with associated config.
        $placementtype1 = $ltigenerator->create_placement_type('core_ltix', 'core_ltix:myplacement');
        $placementtype2 = $ltigenerator->create_placement_type('fake_component', 'fake_component:anotherplacement');
        $placementtype3 = $ltigenerator->create_placement_type('fake_component', 'fake_component:testinghidden');

        $placementconfig1 = [
            'default_usage' => 'enabled',
            'supports_deep_linking' => 1,
            'deep_linking_url' => 'https://example.com/deep_link_launch'
        ];
        $placementconfig2 = [
            'default_usage' => 'disabled',
            'supports_deep_linking' => 0,
        ];
        $placementconfig3 = [
            'default_usage' => 'enabled',
            'supports_deep_linking' => 0,
        ];
        $placementconfig4 = [
            'default_usage' => 'enabled',
            'supports_deep_linking' => 0,
        ];
        $tool1placement1 = $ltigenerator->create_placement($tool1id, $placementtype1->id, $placementconfig1);
        $tool1placement2 = $ltigenerator->create_placement($tool1id, $placementtype2->id, $placementconfig2);
        $tool2placement1 = $ltigenerator->create_placement($tool2id, $placementtype1->id, $placementconfig3);
        $tool3placement1 = $ltigenerator->create_placement($tool3id, $placementtype3->id, $placementconfig4);

        // Override the placement status for tool2placement1 in a context.
        $course = $this->getDataGenerator()->create_course();
        $context = \core\context\course::instance($course->id);
        $ltigenerator->create_placement_status_in_context($tool2placement1->id, placement_status::DISABLED, $context->id);

        // Despite 2 tool using $placementtype1, tool2 has a status of DISABLED in the context and is omitted.
        $type1tools = placements_manager::get_enabled_placements_of_type_in_context($placementtype1->type, $context->id);
        $this->assertCount(1, $type1tools);

        // Tool 1's second placement has default_usage=disabled so, despite the tool being visible to courses, won't be included
        // unless overridden at the course context.
        $type2tools = placements_manager::get_enabled_placements_of_type_in_context($placementtype2->type, $context->id);
        $this->assertCount(0, $type2tools);

        // Verify that despite a placement config being present, any hidden tools are not listed.
        $type3tools = placements_manager::get_enabled_placements_of_type_in_context($placementtype3->type, $context->id);
        $this->assertCount(0, $type3tools);

        // TODO: verify the specific data being returned...eventually moving to objects + repo.
    }
}
