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
use core_ltix\local\lticore\messages\lti_message;
use core_ltix\local\lticore\messages\lti_resource_link_request;

/**
 * Persistent handling lti_types records.
 *
 * @package    core_ltix
 * @copyright  2024 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lti_types extends persistent {

    /** @var string The table name. */
    public const TABLE = 'lti_types';

    protected static function define_properties(): array {
        return [
            'name' => [
                'type' => PARAM_TEXT,
            ],
            'baseurl' => [
                'type' => PARAM_URL,
            ],
            'tooldomain' => [
                'type' => PARAM_TEXT,
            ],
            'state' => [
                'type' => PARAM_INT,
                'default' => \core_ltix\constants::LTI_TOOL_STATE_PENDING,
                'choices' => [
                    \core_ltix\constants::LTI_TOOL_STATE_ANY,
                    \core_ltix\constants::LTI_TOOL_STATE_CONFIGURED,
                    \core_ltix\constants::LTI_TOOL_STATE_PENDING,
                    \core_ltix\constants::LTI_TOOL_STATE_REJECTED,
                ],
            ],
            'course' => [
                'type' => PARAM_INT,
            ],
            'coursevisible' => [
                'type' => PARAM_INT,
                'default' => \core_ltix\constants::LTI_COURSEVISIBLE_NO,
                'choices' => [
                    \core_ltix\constants::LTI_COURSEVISIBLE_NO,
                    \core_ltix\constants::LTI_COURSEVISIBLE_PRECONFIGURED,
                    \core_ltix\constants::LTI_COURSEVISIBLE_ACTIVITYCHOOSER,
                ],
            ],
            'ltiversion' => [
                'type' => PARAM_TEXT,
                'choices' => [
                    \core_ltix\constants::LTI_VERSION_1,
                    \core_ltix\constants::LTI_VERSION_2,
                    \core_ltix\constants::LTI_VERSION_1P3,
                ]
            ],
            'clientid' => [
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'toolproxyid' => [
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'enabledcapability' => [
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'parameter' => [
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'icon' => [
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'secureicon' => [
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'createdby' => [
                'type' => PARAM_INT,
            ],
            'description' => [
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
        ];
    }

    protected function validate_field_incompatible_with_versions($field, array $versions): bool | \lang_string {
        $ltiversion = $this->get('ltiversion');
        if (in_array($ltiversion, $versions)) {
            if (!is_null($field)) {
                return new \lang_string('model:validation:fieldincompatiblewithltiversion', 'core_ltix', $ltiversion);
            }
        }
        return true;
    }

    protected function validate_field_required_for_versions($field, array $versions): bool | \lang_string {
        $ltiversion = $this->get('ltiversion');
        if (in_array($ltiversion, $versions)) {
            if (empty($field)) {
                return new \lang_string('model:validation:fieldrequiredforltiversion', 'core_ltix', $ltiversion);
            }
        }
        return true;
    }

    /**
     * Clientid is required for LTI 1p3 but incompatible with 1p0 and 2p0.
     *
     * @param string|null $clientid the clientid.
     * @return bool|\lang_string
     */
    protected function validate_clientid($clientid) {

        $result = $this->validate_field_required_for_versions($clientid, [\core_ltix\constants::LTI_VERSION_1P3]);

        if ($result === true) {
            $result = $this->validate_field_incompatible_with_versions(
                $clientid,
                [\core_ltix\constants::LTI_VERSION_1, \core_ltix\constants::LTI_VERSION_2]
            );
        }
        return $result;
    }

    /**
     * Toolproxyid is required for LTI 2po but incompatible with 1p0 and 1p3.
     *
     * @param int|null $toolproxyid the tool proxy id.
     * @return bool|\lang_string
     */
    protected function validate_toolproxyid($toolproxyid) {
        $result = $this->validate_field_required_for_versions($toolproxyid, [\core_ltix\constants::LTI_VERSION_2]);
        if ($result === true) {
            $result = $this->validate_field_incompatible_with_versions(
                $toolproxyid,
                [\core_ltix\constants::LTI_VERSION_1, \core_ltix\constants::LTI_VERSION_1P3],
            );
        }
        return $result;
    }

    /**
     * Parameter is incompatible with 1p0 and 1p3.
     *
     * @param string $parameter the parameter string.
     * @return bool|\lang_string
     */
    protected function validate_parameter($parameter) {
        return $this->validate_field_incompatible_with_versions(
            $parameter,
            [\core_ltix\constants::LTI_VERSION_1, \core_ltix\constants::LTI_VERSION_1P3],
        );
    }

    /**
     * Enabledcapability is incompatible with 1p0 and 1p3.
     *
     * @param string $enabledcapability the enabledcapability string.
     * @return bool|\lang_string
     */
    protected function validate_enabledcapability($enabledcapability) {
        return $this->validate_field_incompatible_with_versions(
            $enabledcapability,
            [\core_ltix\constants::LTI_VERSION_1, \core_ltix\constants::LTI_VERSION_1P3],
        );
    }

    /**
     * Calculate the tooldomain when base URL is set.
     *
     * @param string $baseurl the tool URL.
     * @return void
     */
    protected function set_baseurl($baseurl) {
        // Tooldomain is always calculated from the baseurl.
        $tooldomain = \core_ltix\helper::get_domain_from_url($baseurl);

        $this->raw_set('baseurl', $baseurl);
        $this->raw_set('tooldomain', $tooldomain);
    }

    /**
     * When attempting to set the tool domain, only use the provided value if the baseURL hasn't been set yet.
     * Otherwise, use the domain calculated from the baseurl.
     *
     * @param string $tooldomain the tool domain.
     * @return void
     */
    protected function set_tooldomain($tooldomain) {
        // Tool domain is always calculated from the baseurl, provided that's been set already.
        // Note: If tool domain is set first, allow any value, but note that set_baseurl() will change this value when it's called.
        if (($baseurl = $this->get('baseurl')) !== null) {
            $tooldomain = \core_ltix\helper::get_domain_from_url($baseurl);
        }
        $this->raw_set('tooldomain', $tooldomain);
    }

    /**
     * Get the config values for this type.
     *
     * @return lti_types_config[]
     */
    protected function get_config(): array {
        // Lazy load the config.
    }
}
