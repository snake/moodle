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

/**
 * This module is responsible for the calendar filter.
 *
 * @module     core_calendar/calendar_selectors
 * @package    core_calendar
 * @copyright  2017 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    return {
        eventFilterItem: "[data-action='filter-event-type']",
        eventType: {
            site: "[data-eventtype-site]",
            category: "[data-eventtype-category]",
            course: "[data-eventtype-course]",
            group: "[data-eventtype-group]",
            user: "[data-eventtype-user]",
        },
        popoverType: {
            site: "[data-popover-eventtype-site]",
            category: "[data-popover-eventtype-category]",
            course: "[data-popover-eventtype-course]",
            group: "[data-popover-eventtype-group]",
            user: "[data-popover-eventtype-user]",
        },
        calendarPeriods: {
            month: "[data-period='month']",
        },
        editLink: 'a[data-action="edit"]',
        deleteLink: 'a[data-action="delete"]',
        courseSelector: 'select[name="course"]',
        newEventButton: 'button[data-action="new-event-button"]',
        actions: {
            edit: '[data-action="edit"]',
            remove: '[data-action="delete"]',
        },
    };
});
