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

/**
 * This file contains all necessary code to view a lti activity instance
 *
 * @package mod_lti
 * @copyright  2009 Marc Alier, Jordi Piguillem, Nikolas Galanis
 *  marc.alier@upc.edu
 * @copyright  2009 Universitat Politecnica de Catalunya http://www.upc.edu
 * @author     Marc Alier
 * @author     Jordi Piguillem
 * @author     Nikolas Galanis
 * @author     Chris Scribner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once($CFG->dirroot.'/mod/lti/lib.php');
require_once($CFG->dirroot.'/mod/lti/locallib.php');

$cmid = required_param('id', PARAM_INT); // Course Module ID.
$triggerview = optional_param('triggerview', 1, PARAM_BOOL);
$action = optional_param('action', '', PARAM_TEXT);
$foruserid = optional_param('user', 0, PARAM_INT);

$cm = get_coursemodule_from_id('lti', $cmid, 0, false, MUST_EXIST);
$lti = $DB->get_record('lti', array('id' => $cm->instance), '*', MUST_EXIST);

// TODO: Launch workflow overview (implement this):
//  1. get the link, where itemtype='mod_lti:activityplacement', itemid=$cm->instance.
//  2. Find the tool associated with the link:
//  a) it might be set on the link directly (1p3/2p0 mostly, outside of backup and restore scenarios,
//   1p1 sometimes as can be manually configured via legacy feature)
//  b) if not set on the link, then try to domain match (won't find manually configure 1p1 instances though).
//  3. At this stage, there's no guarantee we have a tool, however, we likely do.
//  for the 1p1 manually configured instance case, we can probably fudge the necessary tool info from the instance table....
//  Then:
//  4. a) if 1p3, use the launch builder. we must have a tool by now for 1p3.
//     b) if 1p1 ( if we have a tool) or 2p0...
//     c) for 1p1 where we don't...
// ---------
// legacy_launch_link($link); for 1p1/2p0 and
// builders for 1p3?
// Pseudocode:
// $link = core_ltix\helper::get_placement_link(...);
// $toolconfig = helper::get_tool_config_from_link(); //also does domain matching based on link URL.
// if (!$toolconfig) {
//     special case for manually-configured 1p1 instances.
//     launch_tool_replacement()
// } else {
//     if 1p1/2p0
//         launch_tool_replacement()
//     if 1p3
//         $builder = new lti_resource_link_launch_request_builder();
//         $builder->build_resource_link_launch_request($toolconfig, $link, $servicefacade, $issuer, $USER);
// }
// This is complex, however, this launch endpoint is very likely located in a core_ltix view.
// But what does that look like? How are consumers presenting their links in content, for example?
// Some ideas for link presentation:
// In general, at the most basic level (matching current mod_lti behaviours):
// - embedded: the component decides where the embedding takes place. API to embed the frame and delegate the launch to core.
//   the component can choose to surface the lti links for launch really anywhere.
// - new window: components need to surface a link directly to ltix/launch.php?blah
//
// the core endpoint might be ltix/launch.php, which just takes id of the link.
//
// Problem: how does a placement KNOW what the tool settings are for a given link, in order to make decisions like whether to link
// to ltix/launch.php, or whether to embed it?
// A: We're going to need an API for this...
// something like:
//
// this can check in the following way:
// 1. tool associated with the link (first check, most links)
// 2. if no associated tool config, try to domain match a tool (handles bnr)
// 3. else no tool config found
//
// For mod_lti, we'd need to check this, and then add additional logic supporting those legacy, manually configured instances, since
// the tool data resides inside the lti table in this case:
// if (helper::get_tool_config_from_link($link) === null)) {
//     try to read the launch container from the lti table and make inferences from that.
// }
// So ltix/launch.php can be ugly, but it can't depend on lti instances!
// Options with minimal change for 1p1/2p0:
// Can we provide an adaptor allowing the core_ltix link launch to work with lti legacy?
$typeid = $lti->typeid;
if (empty($typeid) && ($tool = \core_ltix\helper::get_tool_by_url_match($lti->toolurl))) {
    $typeid = $tool->id;
}
if ($typeid) {
    $config = \core_ltix\helper::get_type_config($typeid);
    $missingtooltype = empty($config);
    if (!$missingtooltype) {
        $config = \core_ltix\helper::get_type_type_config($typeid);
        if ($config->lti_ltiversion === \core_ltix\constants::LTI_VERSION_1P3) {
            if (!isset($SESSION->lti_initiatelogin_status)) {
                $msgtype = 'basic-lti-launch-request';
                if ($action === 'gradeReport') {
                    $msgtype = 'LtiSubmissionReviewRequest';
                }
                echo \core_ltix\helper::initiate_login($cm->course, $cmid, $lti, $config, $msgtype, '', '', $foruserid);
                exit;
            } else {
                unset($SESSION->lti_initiatelogin_status);
            }
        }
    }
}

$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/lti:view', $context);

if (!empty($missingtooltype)) {
    $PAGE->set_url(new moodle_url('/mod/lti/launch.php'));
    $PAGE->set_context($context);
    $PAGE->set_secondary_active_tab('modulepage');
    $PAGE->set_pagelayout('incourse');
    echo $OUTPUT->header();
    throw new moodle_exception('tooltypenotfounderror', 'mod_lti');
}

// Completion and trigger events.
if ($triggerview) {
    lti_view($lti, $course, $cm, $context);
}

$lti->cmid = $cm->id;
\core_ltix\helper::launch_tool($lti, $foruserid);
