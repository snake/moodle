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
 * Contains an LTI Advantage-specific task responsible for pushing grades to tool platforms.
 *
 * @package    enrol_lti
 * @copyright  2021 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../../../../config.php');
//namespace enrol_lti\local\ltiadvantage\task;

use core\task\scheduled_task;
use enrol_lti\helper;
require_once($CFG->dirroot . '/enrol/lti/classes/local/ltiadvantage/issuer_database.php');
use enrol_lti\local\ltiadvantage\issuer_database;
require_once($CFG->dirroot . '/enrol/lti/classes/local/ltiadvantage/repository/application_registration_repository.php');
use enrol_lti\local\ltiadvantage\repository\application_registration_repository;
require_once($CFG->dirroot . '/enrol/lti/classes/local/ltiadvantage/repository/deployment_repository.php');
use enrol_lti\local\ltiadvantage\repository\deployment_repository;
use enrol_lti\local\ltiadvantage\repository\resource_link_repository;
use enrol_lti\local\ltiadvantage\repository\user_repository;
use IMSGlobal\LTI13\LTI_Assignments_Grades_Service;
use IMSGlobal\LTI13\LTI_Grade;
use IMSGlobal\LTI13\LTI_Lineitem;
require_once($CFG->libdir . '/lti/LTI_Service_Connector.php');
use IMSGlobal\LTI13\LTI_Service_Connector;

// Update manually for now.
$COURSE_ID = 19; // Id of the course, in the platform.
$GRADE_ITEM_ID = 117; // Id of the grade item in the platform.
$TOOL_TYPE_ID = 38; // The id of the preconfigured tool in the platform.
$REGISTRATION_ID = 18; // The id of the registration of the platform, in the tool.
$LINE_ITEMS_URL = "http://localhost/ltiplatform/mod/lti/services.php/$COURSE_ID/lineitems?type_id=$TOOL_TYPE_ID";
$LINE_ITEM_URL = "http://localhost/ltiplatform/mod/lti/services.php/$COURSE_ID/lineitems/$GRADE_ITEM_ID/lineitem?type_id=$TOOL_TYPE_ID";
$SCOPES = [
    "https://purl.imsglobal.org/spec/lti-ags/scope/lineitem",
    "https://purl.imsglobal.org/spec/lti-ags/scope/result.readonly",
    "https://purl.imsglobal.org/spec/lti-ags/scope/score"
];
$RESOURCE_LINK_ID = (string)100; // The id of the mdl_lti record for the external tool.


// Get a service worker for the corresponding application registration.
$appregistrationrepo = new application_registration_repository();
$appregistration = $appregistrationrepo->find($REGISTRATION_ID);
$issuerdb = new issuer_database();
$registration = $issuerdb->find_registration_by_issuer($appregistration->get_platformid());
$sc = new LTI_Service_Connector($registration);
$servicedata = [
    'lineitems' => $LINE_ITEMS_URL,
    'lineitem' => $LINE_ITEM_URL ?? null,
    'scope' => $SCOPES,
];

// Line items, but sending using the service connector so we can control the data.
$body = json_encode(array_filter(
    [
        "scoreMaximum" => 1.0,
        "resourceLinkId" => $RESOURCE_LINK_ID,
        'label' => 'mylineitem'
    ]
));

$response = $sc->make_service_request(
    $servicedata['scope'],
    'POST',
    $LINE_ITEMS_URL,
    $body,
    'application/vnd.ims.lis.v2.lineitem+json',
    'application/vnd.ims.lis.v2.lineitem+json'
);

$httpheader = $response['headers'][0];
$responsecode = explode(' ', $httpheader)[1];

echo "Made request to $LINE_ITEMS_URL".PHP_EOL;
echo "Response code was ".$responsecode . PHP_EOL;

foreach ($response as $key => $val) {
    //echo "Key: $key, Value: $val" . PHP_EOL;
}


// Scores below.
// Place '/scores' before url params
//$scoreurl = $servicedata['lineitem'];
//$pos = strpos($scoreurl, '?');
//$scoreurl = $pos === false ? $scoreurl . '/scores' : substr_replace($scoreurl, '/scores', $pos, 0);


/*$ltigrade = LTI_Grade::new()
->set_score_given('66')
->set_score_maximum($grademax)
->set_user_id($user->get_sourceid())
->set_timestamp(date(\DateTime::ISO8601, $dategraded))
->set_activity_progress('Completed')
->set_grading_progress('FullyGraded');

// Don't specify the line item.
// The default line item will be used or a line item will be created.
$response = $ags->put_grade($ltigrade);
*/
