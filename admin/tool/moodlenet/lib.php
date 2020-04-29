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

/**
 * This page lists public api for tool_moodlenet plugin.
 *
 * @package    tool_moodlenet
 * @copyright  2020 Peter Dias
 * @license    http://www.gnu.org/copyleft/gpl.html GNU
 */

defined('MOODLE_INTERNAL') || die;

define('DEFAULT_MOODLE_NET_LINK', get_config('tool_moodlenet', 'defaultmoodlenet'));

/**
 * Generate the endpoint url to the user's moodlenet site.
 *
 * @param string $profileurl The user's moodlenet profile page
 * @param int $course The moodle course the mnet resource will be added to
 * @param int $section The section of the course will be added to. Defaults to the 0th element.
 * @return string the resulting endpoint
 * @throws moodle_exception
 */
function generate_mnet_endpoint(string $profileurl, int $course, int $section = 0) {
    global $CFG;
    $urlportions = explode('@', $profileurl);
    $domain = end($urlportions);
    $importurl = new moodle_url('admin/tool/moodlenet/import.php', ['course' => $course, 'section' => $section]);
    $endpoint = new moodle_url('endpoint', ['site' => $CFG->wwwroot, 'path' => $importurl->out(false)]);
    return "$domain/{$endpoint->out(false)}";
}

/**
 * Hooking function to build up the initial Activity Chooser footer information for MoodleNet
 *
 * @return object What we are going too pass to the Activity Chooser to orender as a footer
 * @throws dml_exception
 */
function tool_moodlenet_custom_choooser_footer($courseid) {
    global $USER;
    $tool = core_plugin_manager::instance()->get_plugin_info('tool_moodlenet');
    if ($tool) {
        $enabled = get_config('core', 'enablemoodlenet');
        $installed = class_exists('tool_moodlenet\profile_manager', true);

        $advanced = false;
        if ($installed) {
            $mnetprofile = \tool_moodlenet\profile_manager::get_moodlenet_user_profile($USER->id);
            if ($mnetprofile !== null) {
                // This is firing on every page load, which is adding a huge lag.
                //$profilelink = \tool_moodlenet\profile_manager::get_moodlenet_profile_link($mnetprofile);
                //$advanced = $profilelink['domain'] ?? '';

                // Set the site and path. MoodleNet requires this to send the user back to Moodle.
                global $CFG;
                $advanced = $mnetprofile->get_domain() ?? '';
                $advanced = "https://moodlenet.prototype.moodledemo.net/testclient.php";
                $advanced .= "?site=" . urlencode($CFG->wwwroot) . "&path=" . urlencode("admin/tool/moodlenet/import.php?course=".$courseid);
            }
        }

        $footerdata = (object)[
            'enabled' => (bool)$enabled, // Mocks the adv feat setting.
            'installed' => $installed, // Mocks some CB we will do to see if the plugin is installed.
            'generic' => DEFAULT_MOODLE_NET_LINK, // Mock of the default HQ run mnet instance.
            'advanced' => $advanced, // Can be false if user has not entered text into the fake form.
            'image' => [
                'name' => 'MoodleNet',
                'component' => 'tool_moodlenet', // Logo for use in templates etc.
            ],
            // The following two items are required for your footer to show up in the activity chooser.
            'customfooterjs' => 'tool_moodlenet/instance_form',
            'customfootertemplate' => 'tool_moodlenet/chooser_footer',
        ];
    }

    return $footerdata;
}
