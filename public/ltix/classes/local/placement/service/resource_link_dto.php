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

/**
 * Resource link data transfer object.
 *
 * This DTO contains only fields editable by client code, plus an external_identifier
 * for secondary identity lookup. The `id` field is set by the DB layer after creation
 * and is required for updates/deletes.
 * Fields like servicesalt are generated internally by the service.
 *
 * @package    core_ltix
 * @copyright  2025 Mihail Geshoski <mihail@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class resource_link_dto {

    public function __construct(
        public readonly external_identifier $external_identifier,
        public readonly ?int $id = null,
        public readonly int $toolid = 0,
        public readonly string $url = '',
        public readonly string $title = '',
        public readonly ?string $text = null,
        public readonly string $textformat = FORMAT_PLAIN,
        public readonly bool $gradable = false,
        public readonly ?int $launchcontainer = null,
        public readonly ?string $customparams = null,
        public readonly ?string $icon = null,
        public readonly int $effective_launchcontainer = \core_ltix\constants::LTI_LAUNCH_CONTAINER_EMBED_NO_BLOCKS,
    ) {
        if ($title === '') {
            throw new \coding_exception('Title cannot be empty');
        }
    }

    /**
     * Create a DTO for creation scenarios (no id yet).
     *
     * @param external_identifier $external_identifier
     * @param int $toolid
     * @param string $url
     * @param string $title
     * @param string|null $text
     * @param string $textformat
     * @param bool $gradable
     * @param int|null $launchcontainer
     * @param string|null $customparams
     * @param string|null $icon
     * @return self
     */
    public static function from_create_fields(
        external_identifier $external_identifier,
        int $toolid,
        string $url,
        string $title,
        ?string $text = null,
        string $textformat = FORMAT_PLAIN,
        bool $gradable = false,
        ?int $launchcontainer = null,
        ?string $customparams = null,
        ?string $icon = null
    ): self {
        return new self(
            $external_identifier,
            null,
            $toolid,
            $url,
            $title,
            $text,
            $textformat,
            $gradable,
            $launchcontainer,
            $customparams,
            $icon
        );
    }

    /**
     * Create a DTO for update scenarios (with id).
     *
     * @param int $id
     * @param external_identifier $external_identifier
     * @param int $toolid
     * @param array $fields
     * @return self
     */
    public static function from_update_fields(
        int $id,
        external_identifier $external_identifier,
        int $toolid,
        array $fields
    ): self {
        $allowed = ['url', 'title', 'text', 'textformat', 'gradable', 'launchcontainer', 'customparams', 'icon'];
        $unknown = array_diff_key($fields, array_flip($allowed));
        if (!empty($unknown)) {
            throw new \coding_exception('Invalid update fields: ' . implode(', ', array_keys($unknown)));
        }

        return new self(
            $external_identifier,
            $id,
            $toolid,
            $fields['url'] ?? '',
            $fields['title'] ?? '',
            $fields['text'] ?? null,
            $fields['textformat'] ?? FORMAT_PLAIN,
            $fields['gradable'] ?? false,
            $fields['launchcontainer'] ?? null,
            $fields['customparams'] ?? null,
            $fields['icon'] ?? null
        );
    }

    /**
     * Create a DTO from an existing resource_link persistent.
     *
     * @param external_identifier $external_identifier
     * @param int $id
     * @param int $toolid
     * @param string $url
     * @param string $title
     * @param string|null $text
     * @param string $textformat
     * @param bool $gradable
     * @param int|null $launchcontainer
     * @param string|null $customparams
     * @param string|null $icon
     * @param int $effective_launchcontainer
     * @return self
     */
    public static function from_persistent(
        external_identifier $external_identifier,
        int $id,
        int $toolid,
        string $url,
        string $title,
        ?string $text = null,
        string $textformat = FORMAT_PLAIN,
        bool $gradable = false,
        ?int $launchcontainer = null,
        ?string $customparams = null,
        ?string $icon = null,
        int $effective_launchcontainer = \core_ltix\constants::LTI_LAUNCH_CONTAINER_EMBED_NO_BLOCKS
    ): self {
        return new self(
            $external_identifier,
            $id,
            $toolid,
            $url,
            $title,
            $text,
            $textformat,
            $gradable,
            $launchcontainer,
            $customparams,
            $icon,
            $effective_launchcontainer
        );
    }

    /**
     * Get update data (non-empty fields only).
     *
     * @return array
     */
    public function to_update_array(): array {
        $data = [];
        if ($this->url !== '') {
            $data['url'] = $this->url;
        }
        if ($this->title !== '') {
            $data['title'] = $this->title;
        }
        if ($this->text !== null) {
            $data['text'] = $this->text;
        }
        if ($this->textformat !== FORMAT_PLAIN) {
            $data['textformat'] = $this->textformat;
        }
        if ($this->gradable !== false) {
            $data['gradable'] = $this->gradable;
        }
        if ($this->launchcontainer !== null) {
            $data['launchcontainer'] = $this->launchcontainer;
        }
        if ($this->customparams !== null) {
            $data['customparams'] = $this->customparams;
        }
        if ($this->icon !== null) {
            $data['icon'] = $this->icon;
        }
        return $data;
    }
}

