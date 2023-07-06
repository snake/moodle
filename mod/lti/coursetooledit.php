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
 * Page allowing instructors to configure course-level tools.
 *
 * @package mod_lti
 * @copyright  2023 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\output\notification;

require_once('../../config.php');
require_once($CFG->dirroot.'/mod/lti/edit_form.php');
require_once($CFG->dirroot.'/mod/lti/lib.php');

$courseid = required_param('course', PARAM_INT);
$typeid = optional_param('typeid', null, PARAM_INT);

// Permissions etc.
require_login($courseid, false);
require_capability('mod/lti:addcoursetool', context_course::instance($courseid));
if (!empty($typeid)) {
    // TODO: this should surely check site-level tools, not just courseid != type->course. What if I pass in the site course and it does match?
    $type = lti_get_type($typeid);
    if ($type->course != $courseid) {
        throw new moodle_exception('You do not have permissions to edit this tool type.');
    }
}

// Page setup.
$url = new moodle_url('/mod/lti/coursetooledit.php', ['courseid' => $courseid]);
$PAGE->set_url($url);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('edittype', 'mod_lti')); // TODO confirm with UX about use of 'edit preconfigured tool' for the page title.
$PAGE->set_secondary_active_tab('coursetools');
$PAGE->add_body_class('limitedwidth');

$type = !empty($typeid) ? lti_get_type_type_config($typeid) : (object) ['lti_clientid' => null];
$pageheading = !empty($typeid) ? get_string('courseexternaltooleditheading', 'mod_lti') :
    get_string('courseexternaltooladdheading', 'mod_lti');
$form = new mod_lti_edit_types_form($url, (object)array('id' => $typeid, 'clientid' => $type->lti_clientid));

if ($form->is_cancelled()) {

    redirect(new moodle_url('/mod/lti/coursetools.php', ['id' => $courseid]));
} else if ($data = $form->get_data()) {

    if (!empty($data->typeid)) {
        require_sesskey();

        $type = (object) ['id' => $data->typeid];
        lti_load_type_if_cartridge($data);
        lti_update_type($type, $data);
        redirect(new moodle_url('/mod/lti/coursetools.php', ['id' => $courseid]), get_string('courseexternaltooleditsuccess',
            'mod_lti', $type->name), 0, notification::NOTIFY_SUCCESS);
    } else {
        require_sesskey();

        $type = (object) ['state' => LTI_TOOL_STATE_CONFIGURED, 'course' => $data->course];
        lti_load_type_if_cartridge($data);
        lti_add_type($type, $data);
        redirect(new moodle_url('/mod/lti/coursetools.php', ['id' => $courseid]), get_string('courseexternaltooladdsuccess',
            'mod_lti', $type->name), 0, notification::NOTIFY_SUCCESS);
    }
}

// Display the form.
echo $OUTPUT->header();
echo $OUTPUT->heading($pageheading);

if (!empty($typeid)) {
    $form->set_data($type);
}
$form->display();

echo $OUTPUT->footer();
