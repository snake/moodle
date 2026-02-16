<?php
declare(strict_types=1);
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

use core\context;
use core_ltix\constants;
use core_ltix\local\lticore\models\resource_link;
use core_ltix\local\placement\placement_repository;
use core_ltix\local\placement\placements_manager;
use core_useragent;

/**
 * Placement resource link service class.
 *
 * This service class provides CRUD functionality for resource links using DTOs.
 * The service is DI-aware and uses instance methods.
 *
 * @package    core_ltix
 * @copyright  2025 Mihail Geshoski <mihail@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class resource_link_service {

    /**
     * Compute the effective launch container for a link.
     *
     * Resolves the launch container taking into account device type and delegation to tool config.
     *
     * @param ?int $launchcontainer The explicit launch container value (may be null or DEFAULT)
     * @param ?int $toolid The tool type id for config lookup
     * @return int The effective launch container constant
     */
    private function compute_effective_launchcontainer(?int $launchcontainer, ?int $toolid): int {
        $devicetype = core_useragent::get_device_type();

        // Mobile/tablet: always use full screen.
        if ($devicetype === core_useragent::DEVICETYPE_MOBILE || $devicetype === core_useragent::DEVICETYPE_TABLET) {
            return constants::LTI_LAUNCH_CONTAINER_REPLACE_MOODLE_WINDOW;
        }

        // Link has explicit non-default value: use it.
        $launchcontainerInt = (int) $launchcontainer;
        if (!empty($launchcontainer) && $launchcontainerInt != constants::LTI_LAUNCH_CONTAINER_DEFAULT) {
            return $launchcontainerInt;
        }

        // Delegate to tool config.
        $toolconfig = !empty($toolid) ? \core_ltix\helper::get_type_config($toolid) : [];
        if (isset($toolconfig['launchcontainer']) && (int) $toolconfig['launchcontainer'] != constants::LTI_LAUNCH_CONTAINER_DEFAULT) {
            return (int) $toolconfig['launchcontainer'];
        }

        // Fallback.
        return constants::LTI_LAUNCH_CONTAINER_EMBED_NO_BLOCKS;
    }

    /**
     * Creates a resource link from a DTO.
     *
     * The DTO contains the external_identifier with placement and context information.
     *
     * @param resource_link_dto $dto
     * @return resource_link_dto The DTO with id set after creation
     * @throws \coding_exception
     */
    public function create_resource_link(resource_link_dto $dto): resource_link_dto {
        global $DB;

        $identifier = $dto->external_identifier;
        $context = \core\context::instance_by_id($identifier->contextid);

        // Validate context.
        if (!($context instanceof \core\context\course || $context instanceof \core\context\module)) {
            throw new \coding_exception("Invalid context.");
        }

        // Validate placement type.
        if (!placements_manager::is_valid_placement_type($identifier->itemtype)) {
            throw new \coding_exception("Invalid placement type.");
        }

        $placementtyperecord = $DB->get_record('lti_placement_type', ['type' => $identifier->itemtype], 'component');

        // Validate component.
        if ($identifier->component !== $placementtyperecord->component) {
            throw new \coding_exception("Invalid component.");
        }

        // Validate placement enabled for tool.
        $coursecontext = ($context instanceof \core\context\course) ? $context : $context->get_course_context();
        $isplacementenabledfortool = placement_repository::is_placement_enabled_for_tool_in_course(
            $identifier->itemtype,
            $dto->toolid,
            intval($coursecontext->instanceid)
        );

        if (!empty($dto->toolid) && !$isplacementenabledfortool) {
            throw new \coding_exception(
                "The resource link cannot be created for the specified placement in the given tool.");
        }

        // Generate servicesalt internally.
        $servicesalt = uniqid('', true);

        // Create persistent from DTO and database fields.
        $resourcelink = new resource_link(0, (object) [
            'typeid' => $dto->toolid,
            'component' => $identifier->component,
            'itemtype' => $identifier->itemtype,
            'contextid' => $identifier->contextid,
            'itemid' => $identifier->itemid,
            'url' => $dto->url,
            'title' => $dto->title,
            ...(!empty($dto->text) ? ['text' => $dto->text] : []),
            'textformat' => $dto->textformat,
            'gradable' => $dto->gradable,
            'servicesalt' => $servicesalt,
            ...(isset($dto->launchcontainer) ? ['launchcontainer' => $dto->launchcontainer] : []),
            ...(!empty($dto->icon) ? ['icon' => $dto->icon] : []),
            ...(!empty($dto->customparams) ? ['customparams' => $dto->customparams] : []),
        ]);

        $resourcelink->create();

        // Compute effective launch container.
        $effectiveLaunchContainer = $this->compute_effective_launchcontainer(
            $dto->launchcontainer,
            $dto->toolid
        );

        // Return DTO with id set and effective launch container computed.
        return new resource_link_dto(
            $dto->external_identifier,
            (int) $resourcelink->get('id'),
            $dto->toolid,
            $dto->url,
            $dto->title,
            $dto->text,
            $dto->textformat,
            $dto->gradable,
            $dto->launchcontainer,
            $dto->customparams,
            $dto->icon,
            $effectiveLaunchContainer
        );
    }

    /**
     * Get a resource link by id and return as DTO.
     *
     * @param int $id
     * @return resource_link_dto|null
     */
    public function get_resource_link(int $id): ?resource_link_dto {
        $resourcelink = (new resource_link())->get_record(['id' => $id]);

        if (!$resourcelink) {
            return null;
        }

        $identifier = new external_identifier(
            (string) $resourcelink->get('component'),
            (string) $resourcelink->get('itemtype'),
            (int) $resourcelink->get('itemid'),
            (int) $resourcelink->get('contextid')
        );

        // Compute effective launch container.
        $effectiveLaunchContainer = $this->compute_effective_launchcontainer(
            $resourcelink->get('launchcontainer'),
            (int) $resourcelink->get('typeid')
        );

        return resource_link_dto::from_persistent(
            $identifier,
            (int) $resourcelink->get('id'),
            (int) $resourcelink->get('typeid'),
            (string) $resourcelink->get('url'),
            (string) $resourcelink->get('title'),
            $resourcelink->get('text'),
            (string) $resourcelink->get('textformat'),
            (bool) $resourcelink->get('gradable'),
            $resourcelink->get('launchcontainer'),
            $resourcelink->get('customparams'),
            $resourcelink->get('icon'),
            $effectiveLaunchContainer
        );
    }

    /**
     * Update a resource link using DTO data.
     *
     * The DTO must have an id set. Only editable fields from the DTO are updated.
     *
     * @param resource_link_dto $dto
     * @return resource_link_dto|null The updated DTO, or null if not found
     * @throws \coding_exception if the DTO has no id
     */
    public function update_resource_link(resource_link_dto $dto): ?resource_link_dto {
        if ($dto->id === null) {
            throw new \coding_exception('Cannot update resource link. DTO must have an id set.');
        }

        $updatedata = $dto->to_update_array();

        if (empty($updatedata)) {
            return null;
        }

        $resourcelink = (new resource_link())->get_record(['id' => $dto->id]);

        if (!$resourcelink) {
            return null;
        }

        foreach ($updatedata as $property => $value) {
            $resourcelink->set($property, $value);
        }

        $resourcelink->update();

        // Compute effective launch container from updated values.
        $effectiveLaunchContainer = $this->compute_effective_launchcontainer(
            $updatedata['launchcontainer'] ?? $resourcelink->get('launchcontainer'),
            $dto->toolid
        );

        // Return updated DTO with id and external_identifier.
        return new resource_link_dto(
            $dto->external_identifier,
            $dto->id,
            $dto->toolid,
            $updatedata['url'] ?? (string) $resourcelink->get('url'),
            $updatedata['title'] ?? (string) $resourcelink->get('title'),
            $updatedata['text'] ?? $resourcelink->get('text'),
            $updatedata['textformat'] ?? (string) $resourcelink->get('textformat'),
            $updatedata['gradable'] ?? (bool) $resourcelink->get('gradable'),
            $updatedata['launchcontainer'] ?? $resourcelink->get('launchcontainer'),
            $updatedata['customparams'] ?? $resourcelink->get('customparams'),
            $updatedata['icon'] ?? $resourcelink->get('icon'),
            $effectiveLaunchContainer
        );
    }

    /**
     * Delete a resource link by external identifier.
     *
     * @param external_identifier $identifier
     * @return bool True on success, false if not found
     */
    public function delete_by_external_id(external_identifier $identifier): bool {
        $resourcelink = (new resource_link())->get_record([
            'component' => $identifier->component,
            'itemtype' => $identifier->itemtype,
            'itemid' => $identifier->itemid,
            'contextid' => $identifier->contextid,
        ]);

        if (!$resourcelink) {
            return false;
        }

        return $resourcelink->delete();
    }

    /**
     * Delete a resource link by id.
     *
     * @param int $id
     * @return bool True on success, false if not found
     */
    public function delete_resource_link(int $id): bool {
        $resourcelink = (new resource_link())->get_record(['id' => $id]);

        if (!$resourcelink) {
            return false;
        }

        return $resourcelink->delete();
    }
}

