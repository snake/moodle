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

/**
 * External identifier for resource links.
 *
 * This class encapsulates the secondary identity of a resource link, used when
 * clients don't store the database id. It combines placement identity with context.
 *
 * @package    core_ltix
 * @copyright  2025 Mihail Geshoski <mihail@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class external_identifier {

    public function __construct(
        public readonly string $component,
        public readonly string $itemtype,
        public readonly int $itemid,
        public readonly int $contextid,
    ) {
        if ($component === '') {
            throw new \coding_exception('Component cannot be empty');
        }
        if ($itemtype === '') {
            throw new \coding_exception('Itemtype cannot be empty');
        }
        if ($itemid < 0) {
            throw new \coding_exception('Itemid cannot be negative');
        }
        if ($contextid < 0) {
            throw new \coding_exception('Contextid cannot be negative');
        }
    }

    /**
     * Create an external identifier from a context and placement data.
     *
     * @param string $component
     * @param string $itemtype
     * @param int $itemid
     * @param context $context
     * @return self
     */
    public static function from_context(
        string $component,
        string $itemtype,
        int $itemid,
        context $context
    ): self {
        return new self($component, $itemtype, $itemid, $context->id);
    }

    /**
     * Create an external identifier from context id.
     *
     * @param string $component
     * @param string $itemtype
     * @param int $itemid
     * @param int $contextid
     * @return self
     */
    public static function from_context_id(
        string $component,
        string $itemtype,
        int $itemid,
        int $contextid
    ): self {
        return new self($component, $itemtype, $itemid, $contextid);
    }
}

