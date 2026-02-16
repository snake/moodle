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

namespace core_ltix\local\placement\service;

use core_ltix\local\lticore\models\resource_link;

/**
 * Resource link service tests.
 *
 * @covers     \core_ltix\local\placement\service\resource_link_service
 * @package    core_ltix
 * @copyright  2025 Mihail Geshoski <mihail@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class resource_link_service_test extends \advanced_testcase {

    /**
     * Test creating a resource link with a DTO.
     */
    public function test_create_resource_link(): void {
        global $SITE;

        $this->resetAfterTest();

        $ltigenerator = $this->getDataGenerator()->get_plugin_generator('core_ltix');

        // Create a tool.
        $toolid = $ltigenerator->create_tool_types([
            'name' => 'Example tool',
            'baseurl' => 'http://example.com/tool/1',
            'lti_coursevisible' => \core_ltix\constants::LTI_COURSEVISIBLE_PRECONFIGURED,
            'state' => \core_ltix\constants::LTI_TOOL_STATE_CONFIGURED
        ]);

        // Create a placement type.
        $placementtype = $ltigenerator->create_placement_type([
            'component' => 'core_ltix',
            'placementtype' => 'core_ltix:validplacement'
        ]);

        // Create a placement.
        $ltigenerator->create_tool_placements([
            'toolid' => $toolid,
            'placementtypeid' => $placementtype->id,
            'config_default_usage' => 'enabled',
            'config_supports_deep_linking' => 0,
        ]);

        $context = \core\context\course::instance($SITE->id);
        $identifier = external_identifier::from_context(
            'core_ltix',
            'core_ltix:validplacement',
            1,
            $context
        );

        $dto = resource_link_dto::from_create_fields(
            external_identifier: $identifier,
            toolid: $toolid,
            url: 'http://example.com/tool/1/resource/1',
            title: 'Resource title',
            text: '<p>Resource description</p>',
            textformat: FORMAT_HTML,
            gradable: true,
            launchcontainer: \core_ltix\constants::LTI_LAUNCH_CONTAINER_WINDOW,
            customparams: 'id=1',
            icon: 'http://example.com/icon.png'
        );

        $service = new resource_link_service();
        $createdDto = $service->create_resource_link($dto);

        $this->assertInstanceOf(resource_link_dto::class, $createdDto);
        $this->assertNotNull($createdDto->id);
        $this->assertEquals('http://example.com/tool/1/resource/1', $createdDto->url);
        $this->assertEquals('Resource title', $createdDto->title);
        $this->assertEquals('<p>Resource description</p>', $createdDto->text);
        $this->assertEquals(FORMAT_HTML, $createdDto->textformat);
        $this->assertTrue($createdDto->gradable);
        $this->assertEquals(\core_ltix\constants::LTI_LAUNCH_CONTAINER_WINDOW, $createdDto->launchcontainer);
        $this->assertEquals('id=1', $createdDto->customparams);
        $this->assertEquals('http://example.com/icon.png', $createdDto->icon);

        // Verify the persistent was created correctly.
        $persistent = (new resource_link())->get_record(['id' => $createdDto->id]);
        $this->assertNotNull($persistent);
        $this->assertEquals($toolid, $persistent->get('typeid'));
        $this->assertNotEmpty($persistent->get('servicesalt'));
    }

    /**
     * Test getting a resource link by id.
     */
    public function test_get_resource_link(): void {
        global $SITE;

        $this->resetAfterTest();

        $ltigenerator = $this->getDataGenerator()->get_plugin_generator('core_ltix');

        // Create a tool.
        $toolid = $ltigenerator->create_tool_types([
            'name' => 'Example tool',
            'baseurl' => 'http://example.com/tool/1',
            'lti_coursevisible' => \core_ltix\constants::LTI_COURSEVISIBLE_PRECONFIGURED,
            'state' => \core_ltix\constants::LTI_TOOL_STATE_CONFIGURED
        ]);

        // Create a placement type.
        $placementtype = $ltigenerator->create_placement_type([
            'component' => 'core_ltix',
            'placementtype' => 'core_ltix:validplacement'
        ]);

        // Create a placement.
        $ltigenerator->create_tool_placements([
            'toolid' => $toolid,
            'placementtypeid' => $placementtype->id,
            'config_default_usage' => 'enabled',
            'config_supports_deep_linking' => 0,
        ]);

        $context = \core\context\course::instance($SITE->id);
        $identifier = external_identifier::from_context(
            'core_ltix',
            'core_ltix:validplacement',
            1,
            $context
        );

        $dto = resource_link_dto::from_create_fields(
            external_identifier: $identifier,
            toolid: $toolid,
            url: 'http://example.com/tool/1/resource/1',
            title: 'Resource title'
        );

        $service = new resource_link_service();
        $createdDto = $service->create_resource_link($dto);

        // Get the resource link.
        $retrievedDto = $service->get_resource_link($createdDto->id);

        $this->assertInstanceOf(resource_link_dto::class, $retrievedDto);
        $this->assertEquals($createdDto->id, $retrievedDto->id);
        $this->assertEquals($createdDto->url, $retrievedDto->url);
        $this->assertEquals($createdDto->title, $retrievedDto->title);
    }

    /**
     * Test updating a resource link.
     */
    public function test_update_resource_link(): void {
        global $SITE;

        $this->resetAfterTest();

        $ltigenerator = $this->getDataGenerator()->get_plugin_generator('core_ltix');

        // Create a tool.
        $toolid = $ltigenerator->create_tool_types([
            'name' => 'Example tool',
            'baseurl' => 'http://example.com/tool/1',
            'lti_coursevisible' => \core_ltix\constants::LTI_COURSEVISIBLE_PRECONFIGURED,
            'state' => \core_ltix\constants::LTI_TOOL_STATE_CONFIGURED
        ]);

        // Create a placement type.
        $placementtype = $ltigenerator->create_placement_type([
            'component' => 'core_ltix',
            'placementtype' => 'core_ltix:validplacement'
        ]);

        // Create a placement.
        $ltigenerator->create_tool_placements([
            'toolid' => $toolid,
            'placementtypeid' => $placementtype->id,
            'config_default_usage' => 'enabled',
            'config_supports_deep_linking' => 0,
        ]);

        $context = \core\context\course::instance($SITE->id);
        $identifier = external_identifier::from_context(
            'core_ltix',
            'core_ltix:validplacement',
            1,
            $context
        );

        $dto = resource_link_dto::from_create_fields(
            external_identifier: $identifier,
            toolid: $toolid,
            url: 'http://example.com/tool/1/resource/1',
            title: 'Resource title'
        );

        $service = new resource_link_service();
        $createdDto = $service->create_resource_link($dto);

        // Update the resource link.
        $updateDto = resource_link_dto::from_update_fields(
            id: $createdDto->id,
            external_identifier: $identifier,
            toolid: $toolid,
            fields: [
                'url' => 'http://example.com/tool/1/resource/updated',
                'title' => 'Updated title',
                'gradable' => true,
            ]
        );

        $updatedDto = $service->update_resource_link($updateDto);

        $this->assertInstanceOf(resource_link_dto::class, $updatedDto);
        $this->assertEquals($createdDto->id, $updatedDto->id);
        $this->assertEquals('http://example.com/tool/1/resource/updated', $updatedDto->url);
        $this->assertEquals('Updated title', $updatedDto->title);
        $this->assertTrue($updatedDto->gradable);
    }

    /**
     * Test deleting a resource link.
     */
    public function test_delete_resource_link(): void {
        global $SITE;

        $this->resetAfterTest();

        $ltigenerator = $this->getDataGenerator()->get_plugin_generator('core_ltix');

        // Create a tool.
        $toolid = $ltigenerator->create_tool_types([
            'name' => 'Example tool',
            'baseurl' => 'http://example.com/tool/1',
            'lti_coursevisible' => \core_ltix\constants::LTI_COURSEVISIBLE_PRECONFIGURED,
            'state' => \core_ltix\constants::LTI_TOOL_STATE_CONFIGURED
        ]);

        // Create a placement type.
        $placementtype = $ltigenerator->create_placement_type([
            'component' => 'core_ltix',
            'placementtype' => 'core_ltix:validplacement'
        ]);

        // Create a placement.
        $ltigenerator->create_tool_placements([
            'toolid' => $toolid,
            'placementtypeid' => $placementtype->id,
            'config_default_usage' => 'enabled',
            'config_supports_deep_linking' => 0,
        ]);

        $context = \core\context\course::instance($SITE->id);
        $identifier = external_identifier::from_context(
            'core_ltix',
            'core_ltix:validplacement',
            1,
            $context
        );

        $dto = resource_link_dto::from_create_fields(
            external_identifier: $identifier,
            toolid: $toolid,
            url: 'http://example.com/tool/1/resource/1',
            title: 'Resource title'
        );

        $service = new resource_link_service();
        $createdDto = $service->create_resource_link($dto);

        // Delete the resource link.
        $deleted = $service->delete_resource_link($createdDto->id);

        $this->assertTrue($deleted);

        // Verify it's deleted.
        $retrieved = $service->get_resource_link($createdDto->id);
        $this->assertNull($retrieved);
    }

    /**
     * Test deleting a resource link by external identifier.
     */
    public function test_delete_by_external_identifier(): void {
        global $SITE;

        $this->resetAfterTest();

        $ltigenerator = $this->getDataGenerator()->get_plugin_generator('core_ltix');

        // Create a tool.
        $toolid = $ltigenerator->create_tool_types([
            'name' => 'Example tool',
            'baseurl' => 'http://example.com/tool/1',
            'lti_coursevisible' => \core_ltix\constants::LTI_COURSEVISIBLE_PRECONFIGURED,
            'state' => \core_ltix\constants::LTI_TOOL_STATE_CONFIGURED
        ]);

        // Create a placement type.
        $placementtype = $ltigenerator->create_placement_type([
            'component' => 'core_ltix',
            'placementtype' => 'core_ltix:validplacement'
        ]);

        // Create a placement.
        $ltigenerator->create_tool_placements([
            'toolid' => $toolid,
            'placementtypeid' => $placementtype->id,
            'config_default_usage' => 'enabled',
            'config_supports_deep_linking' => 0,
        ]);

        $context = \core\context\course::instance($SITE->id);
        $identifier = external_identifier::from_context(
            'core_ltix',
            'core_ltix:validplacement',
            1,
            $context
        );

        $dto = resource_link_dto::from_create_fields(
            external_identifier: $identifier,
            toolid: $toolid,
            url: 'http://example.com/tool/1/resource/1',
            title: 'Resource title'
        );

        $service = new resource_link_service();
        $createdDto = $service->create_resource_link($dto);

        $deleted = $service->delete_by_external_id($identifier);
        $this->assertTrue($deleted);

        $retrieved = $service->get_resource_link($createdDto->id);
        $this->assertNull($retrieved);
    }

    /**
     * Test update with no id throws exception.
     */
    public function test_update_without_id_throws_exception(): void {
        $this->resetAfterTest();

        $identifier = external_identifier::from_context_id('core_ltix', 'core_ltix:validplacement', 1, 1);
        $dto = resource_link_dto::from_create_fields(
            external_identifier: $identifier,
            toolid: 1,
            url: 'http://example.com/tool/1/resource/1',
            title: 'Resource title'
        );

        $service = new resource_link_service();

        $this->expectException(\coding_exception::class);
        $this->expectExceptionMessage('Cannot update resource link. DTO must have an id set.');
        $service->update_resource_link($dto);
    }

    /**
     * Test that servicesalt is generated internally.
     */
    public function test_servicesalt_generated_internally(): void {
        global $SITE;

        $this->resetAfterTest();

        $ltigenerator = $this->getDataGenerator()->get_plugin_generator('core_ltix');

        // Create a tool.
        $toolid = $ltigenerator->create_tool_types([
            'name' => 'Example tool',
            'baseurl' => 'http://example.com/tool/1',
            'lti_coursevisible' => \core_ltix\constants::LTI_COURSEVISIBLE_PRECONFIGURED,
            'state' => \core_ltix\constants::LTI_TOOL_STATE_CONFIGURED
        ]);

        // Create a placement type.
        $placementtype = $ltigenerator->create_placement_type([
            'component' => 'core_ltix',
            'placementtype' => 'core_ltix:validplacement'
        ]);

        // Create a placement.
        $ltigenerator->create_tool_placements([
            'toolid' => $toolid,
            'placementtypeid' => $placementtype->id,
            'config_default_usage' => 'enabled',
            'config_supports_deep_linking' => 0,
        ]);

        $context = \core\context\course::instance($SITE->id);
        $identifier = external_identifier::from_context(
            'core_ltix',
            'core_ltix:validplacement',
            1,
            $context
        );

        $dto = resource_link_dto::from_create_fields(
            external_identifier: $identifier,
            toolid: $toolid,
            url: 'http://example.com/tool/1/resource/1',
            title: 'Resource title'
        );

        $service = new resource_link_service();
        $createdDto = $service->create_resource_link($dto);

        // Verify servicesalt was generated.
        $persistent = (new resource_link())->get_record(['id' => $createdDto->id]);
        $servicesalt = $persistent->get('servicesalt');
        $this->assertNotEmpty($servicesalt);
        $this->assertIsString($servicesalt);
    }
}

