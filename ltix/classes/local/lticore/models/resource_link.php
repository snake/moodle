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

use core\persistent;
use core\uuid;

/**
 * Resource link persistent.
 *
 * @package    core_ltix
 * @copyright  2024 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class resource_link extends persistent {

    /** @var string The table name. */
    public const TABLE = 'lti_resource_link';

    protected static function define_properties(): array {
        global $CFG;
        require_once($CFG->dirroot . '/ltix/constants.php');

        return [
            'typeid' => [
                'type' => PARAM_INT,
            ],
            'contextid' => [
                'type' => PARAM_INT,
            ],
            'legacyid' => [
                'type' => PARAM_INT,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'uuid' => [
                'type' => PARAM_ALPHANUMEXT,
                'default' => static function(): string {
                    return uuid::generate();
                },
            ],
            'url' => [
                'type' => PARAM_URL,
            ],
            'title' => [
                'type' => PARAM_TEXT,
            ],
            'text' => [
                'type' => PARAM_CLEANHTML,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'textformat' => [
                'type' => PARAM_INT,
                'default' => FORMAT_MOODLE,
                'null' => NULL_ALLOWED,
                'choices' => [
                    FORMAT_MOODLE,
                    FORMAT_HTML,
                    FORMAT_PLAIN,
                    FORMAT_MARKDOWN,
                ],
            ],
            'launchcontainer' => [
                'type' => PARAM_INT,
                'default' => LTI_LAUNCH_CONTAINER_DEFAULT,
                'null' => NULL_NOT_ALLOWED,
                'choices' => [
                    LTI_LAUNCH_CONTAINER_DEFAULT,
                    LTI_LAUNCH_CONTAINER_EMBED,
                    LTI_LAUNCH_CONTAINER_EMBED_NO_BLOCKS,
                    LTI_LAUNCH_CONTAINER_WINDOW,
                    LTI_LAUNCH_CONTAINER_REPLACE_MOODLE_WINDOW,
                ],
            ],
            'customparams' => [
                'type' => PARAM_TEXT,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'icon' => [
                'type' => PARAM_URL,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'servicesalt' => [
                'type' => PARAM_TEXT,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
        ];
    }
}
