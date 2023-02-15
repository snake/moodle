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
 * OAuth 2 Configuration page.
 *
 * @package    core_oauth2
 * @copyright  2017 Damyon Wiese <damyon@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/tablelib.php');

$PAGE->set_url('/admin/oauth2/issuers.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('admin');
$strheading = get_string('oauth2services', 'oauth2');
$PAGE->set_title($strheading);
$PAGE->set_heading($strheading);

require_admin();

$renderer = $PAGE->get_renderer('core_oauth2');

$action = optional_param('action', '', PARAM_ALPHAEXT);
$issuerid = optional_param('id', '', PARAM_RAW);
$issuer = null;

if ($issuerid) {
    $issuer = \core\oauth2\api::get_issuer($issuerid);
    if (!$issuer) {
        throw new \moodle_exception('invaliddata');
    }
}

if ($action == 'edit') {

    $type = $issuer->get('servicetype');
    $mform = \core\oauth2\service\helper::get_service_issuer_form($type, $issuer, ['action' => 'edit']);

    if ($mform->is_cancelled()) {
        redirect(new moodle_url('/admin/oauth2/issuers.php'));
    } else if ($mform->is_submitted() && $data = $mform->get_data()) {
        try {
            $issuer = core\oauth2\api::save_issuer($data);
            redirect($PAGE->url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
        } catch (Exception $e) {
            redirect($PAGE->url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
        }
    }

    // Show the edit form.
    $PAGE->navbar->add(get_string('editissuer', 'oauth2', s($issuer->get('name'))));
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('editissuer', 'oauth2', s($issuer->get('name'))));
    $mform->display();
    echo $OUTPUT->footer();

} else if ($action == 'add') {

    $type = required_param('type', PARAM_ALPHANUM);
    $mform = \core\oauth2\service\helper::get_service_issuer_form($type, null, ['action' => 'add']);

    if ($mform->is_cancelled()) {
        redirect(new moodle_url('/admin/oauth2/issuers.php'));
    } else if ($mform->is_submitted() && $data = $mform->get_data()) {
        core\oauth2\api::save_issuer($data);
        redirect($PAGE->url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    // Show the add form.
    $issuer = core\oauth2\api::init_standard_issuer($type); // Hook allowing plugins to prefill the 'add' form.
    $servicename = core\oauth2\service\helper::get_service_shortname($type);

    $PAGE->navbar->add(get_string('createnewservice', 'oauth2') . ' ' . $servicename);
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('createnewservice', 'oauth2') . ' ' . $servicename);
    if ($issuer) {
        $mform->set_data($issuer->to_record());
    }
    $mform->display();
    echo $OUTPUT->footer();

} else if ($action == 'enable') {

    require_sesskey();
    core\oauth2\api::enable_issuer($issuerid);
    redirect($PAGE->url, get_string('issuerenabled', 'oauth2'), null, \core\output\notification::NOTIFY_SUCCESS);

} else if ($action == 'disable') {

    require_sesskey();
    core\oauth2\api::disable_issuer($issuerid);
    redirect($PAGE->url, get_string('issuerdisabled', 'oauth2'), null, \core\output\notification::NOTIFY_SUCCESS);

} else if ($action == 'delete') {

    if (!optional_param('confirm', false, PARAM_BOOL)) {
        $continueparams = ['action' => 'delete', 'id' => $issuerid, 'sesskey' => sesskey(), 'confirm' => true];
        $continueurl = new moodle_url('/admin/oauth2/issuers.php', $continueparams);
        $cancelurl = new moodle_url('/admin/oauth2/issuers.php');
        echo $OUTPUT->header();
        echo $OUTPUT->confirm(get_string('deleteconfirm', 'oauth2', s($issuer->get('name'))), $continueurl, $cancelurl);
        echo $OUTPUT->footer();
    } else {
        require_sesskey();
        core\oauth2\api::delete_issuer($issuerid);
        redirect($PAGE->url, get_string('issuerdeleted', 'oauth2'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

} else if ($action == 'auth') {

    if (!optional_param('confirm', false, PARAM_BOOL)) {
        $continueparams = ['action' => 'auth', 'id' => $issuerid, 'sesskey' => sesskey(), 'confirm' => true];
        $continueurl = new moodle_url('/admin/oauth2/issuers.php', $continueparams);
        $cancelurl = new moodle_url('/admin/oauth2/issuers.php');
        echo $OUTPUT->header();
        echo $OUTPUT->confirm(get_string('authconfirm', 'oauth2', s($issuer->get('name'))), $continueurl, $cancelurl);
        echo $OUTPUT->footer();
    } else {
        require_sesskey();
        $params = ['sesskey' => sesskey(), 'id' => $issuerid, 'action' => 'auth', 'confirm' => true, 'response' => true];
        if (core\oauth2\api::connect_system_account($issuer, new moodle_url('/admin/oauth2/issuers.php', $params))) {
            redirect($PAGE->url, get_string('authconnected', 'oauth2'), null, \core\output\notification::NOTIFY_SUCCESS);
        } else {
            redirect($PAGE->url, get_string('authnotconnected', 'oauth2'), null, \core\output\notification::NOTIFY_ERROR);
        }
    }
} else if ($action == 'moveup') {
    require_sesskey();
    core\oauth2\api::move_up_issuer($issuerid);
    redirect($PAGE->url);

} else if ($action == 'movedown') {
    require_sesskey();
    core\oauth2\api::move_down_issuer($issuerid);
    redirect($PAGE->url);

} else {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('oauth2services', 'oauth2'));
    echo $OUTPUT->doc_link('OAuth2_Services', get_string('serviceshelp', 'oauth2'));
    $issuers = core\oauth2\api::get_all_issuers(true);
    echo $renderer->issuers_table($issuers);

    echo $renderer->container_start();
    echo get_string('createnewservice', 'oauth2') . ' ';

    foreach (\core\oauth2\service\helper::get_service_names() as $service => $shortname) {
        $docs = "oauth2/issuers/$service"; // TODO plugins to link to docs via API?
        $params = ['action' => 'add', 'type' => $service, 'sesskey' => sesskey(), 'docslink' => $docs];
        $addurl = new moodle_url('/admin/oauth2/issuers.php', $params);
        echo $renderer->single_button($addurl, $shortname);
    }

    // Google template.
    $docs = 'oauth2/issuers/google';
    $params = ['action' => 'edittemplate', 'type' => 'google', 'sesskey' => sesskey(), 'docslink' => $docs];
    $addurl = new moodle_url('/admin/oauth2/issuers.php', $params);
    echo $renderer->single_button($addurl, get_string('google_service', 'oauth2'));

    // Microsoft template.
    $docs = 'oauth2/issuers/microsoft';
    $params = ['action' => 'edittemplate', 'type' => 'microsoft', 'sesskey' => sesskey(), 'docslink' => $docs];
    $addurl = new moodle_url('/admin/oauth2/issuers.php', $params);
    echo $renderer->single_button($addurl, get_string('microsoft_service', 'oauth2'));

    // Facebook template.
    $docs = 'oauth2/issuers/facebook';
    $params = ['action' => 'edittemplate', 'type' => 'facebook', 'sesskey' => sesskey(), 'docslink' => $docs];
    $addurl = new moodle_url('/admin/oauth2/issuers.php', $params);
    echo $renderer->single_button($addurl, get_string('facebook_service', 'oauth2'));

    // Nextcloud template.
    $docs = 'oauth2/issuers/nextcloud';
    $params = ['action' => 'edittemplate', 'type' => 'nextcloud', 'sesskey' => sesskey(), 'docslink' => $docs];
    $addurl = new moodle_url('/admin/oauth2/issuers.php', $params);
    echo $renderer->single_button($addurl, get_string('nextcloud_service', 'oauth2'));

    // IMS Open Badges Connect template.
    $docs = 'oauth2/issuers/imsobv2p1';
    $params = ['action' => 'edittemplate', 'type' => 'imsobv2p1', 'sesskey' => sesskey(), 'docslink' => $docs];
    $addurl = new moodle_url('/admin/oauth2/issuers.php', $params);
    echo $renderer->single_button($addurl, get_string('imsobv2p1_service', 'oauth2'));

    // Linkedin template.
    $docs = 'oauth2/issuers/linkedin';
    $params = ['action' => 'edittemplate', 'type' => 'linkedin', 'sesskey' => sesskey(), 'docslink' => $docs];
    $addurl = new moodle_url('/admin/oauth2/issuers.php', $params);
    echo $renderer->single_button($addurl, get_string('linkedin_service', 'oauth2'));

    // Clever template.
    $docs = 'oauth2/issuers/clever';
    $params = ['action' => 'edittemplate', 'type' => 'clever', 'sesskey' => sesskey(), 'docslink' => $docs];
    $addurl = new moodle_url('/admin/oauth2/issuers.php', $params);
    echo $renderer->single_button($addurl, get_string('clever_service', 'oauth2'));

    // Generic issuer.
    $addurl = new moodle_url('/admin/oauth2/issuers.php', ['action' => 'edit']);
    echo $renderer->single_button($addurl, get_string('custom_service', 'oauth2'));

    echo $renderer->container_end();
    echo $OUTPUT->footer();
}
