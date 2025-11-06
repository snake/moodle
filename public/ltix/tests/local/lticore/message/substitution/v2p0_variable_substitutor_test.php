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

namespace local\lticore\message\substitution;

use core_ltix\local\lticore\message\substitition\v2p0_variable_substitutor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests covering v2p0_variable_substitutor.
 *
 * @package    core_ltix
 * @copyright  2025 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(v2p0_variable_substitutor::class)]
class v2p0_variable_substitutor_test extends \advanced_testcase {

    /**
     * Test substitution for a range of variables.
     *
     * @param array $params the setup params.
     * @param array $iomap the map of input to expected output values.
     * @return void
     */
    #[DataProvider('substitute_provider')]
    public function test_substitute(array $params, array $iomap): void {
        $this->resetAfterTest();

        // Internally, some of the v2p0 substitutor's dependencies use global functions which pull course from context or calculate
        // things about a course or context in the DB. These cannot be easily mocked, so must be generated from dataprovider data.
        $course = $this->getDataGenerator()->create_course($params['course']);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student', $params['user']);
        $context = \core\context\course::instance($course->id);

        $toolconfig = (object) [
            'id' => '123',
            'lti_toolurl' => 'https://tool.example.com',
            'lti_ltiversion' => \core_ltix\constants::LTI_VERSION_2,
            'toolproxy' => (object) [
                'guid' => 'RDL4JE5M1wr2Ke9',
                'secret' => '6E2KfWpbMsrw'
            ],
            'enabledcapability' => implode("\n", $params['enabledcapability']),
        ];

        $substitutor = new v2p0_variable_substitutor(
            toolconfig: $toolconfig,
            sourcedata: $params['sourcedata'],
            user: $user,
            context: $context
        );

        $this->assertEquals(array_values($iomap), $substitutor->substitute(array_keys($iomap)));
    }

    /**
     * Data provider for testing substitute().
     *
     * @return array the test data.
     */
    public static function substitute_provider(): array {
        return [
            'Sub values mapped and present in sourcedata, subbed according to enabled caps policy' => [
                'params' => [
                    'enabledcapability' => [
                        // Resolved via sourcedata.
                        'User.id',
                        'Person.name.family',

                        // Resolved to user object property.
                        'User.username',

                        // Resolved to a course object property.
                        'Context.longDescription',

                        // Resolved via calculation.
                        'CourseSection.timeFrame.end',
                        'CourseSection.timeFrame.begin',

                        // Simulate the case where the policy permits substitution, but it's not mapped to anything.
                        'Unmapped.var.example',
                    ],
                    'sourcedata' => [
                        'user_id' => '12345',
                        'lis_person_name_family' => 'Doe',
                        'lis_person_name_given' => 'John',
                    ],
                    'course' => (object) [
                        'id' => '100000',
                        'category' => '1',
                        'sortorder' => '0',
                        'fullname' => 'Test course 1',
                        'shortname' => 'tc_1',
                        'idnumber' => '',
                        'summary' => 'Test course 1 Lorem ipsum.',
                        'summaryformat' => '0',
                        'format' => 'topics',
                        'showgrades' => '1',
                        'newsitems' => '0',
                        'startdate' => '1759420800',
                        'enddate' => '0',
                        'relativedatesmode' => '0',
                        'marker' => '0',
                        'maxbytes' => '0',
                        'legacyfiles' => '0',
                        'showreports' => '0',
                        'visible' => '1',
                        'visibleold' => '1',
                        'downloadcontent' => null,
                        'groupmode' => '0',
                        'groupmodeforce' => '0',
                        'defaultgroupingid' => '0',
                        'lang' => '',
                        'calendartype' => '',
                        'theme' => '',
                        'timecreated' => '1759471365',
                        'timemodified' => '1759471365',
                        'requested' => '0',
                        'enablecompletion' => '0',
                        'completionnotify' => '0',
                        'cacherev' => '0',
                        'originalcourseid' => null,
                        'showactivitydates' => '0',
                        'showcompletionconditions' => '1',
                        'pdfexportfont' => null,
                        'hiddensections' => 1,
                        'coursedisplay' => 0,
                    ],
                    'user' => (object) [
                        'id' => '103000',
                        'auth' => 'manual',
                        'confirmed' => '1',
                        'policyagreed' => '0',
                        'deleted' => '0',
                        'suspended' => '0',
                        'mnethostid' => '1',
                        'username' => 'username1',
                        'password' => '',
                        'idnumber' => '',
                        'firstname' => '秀英',
                        'lastname' => '楊',
                        'email' => 'username1@example.com',
                        'emailstop' => '0',
                        'phone1' => '',
                        'phone2' => '',
                        'institution' => '',
                        'department' => '',
                        'address' => '',
                        'city' => '',
                        'country' => '',
                        'lang' => 'en',
                        'calendartype' => 'gregorian',
                        'theme' => '',
                        'timezone' => '99',
                        'firstaccess' => '0',
                        'lastaccess' => '0',
                        'lastlogin' => '0',
                        'currentlogin' => '0',
                        'lastip' => '0.0.0.0',
                        'secret' => '',
                        'picture' => '0',
                        'description' => null,
                        'descriptionformat' => '1',
                        'mailformat' => '1',
                        'maildigest' => '0',
                        'maildisplay' => '2',
                        'autosubscribe' => '1',
                        'trackforums' => '0',
                        'timecreated' => '1759471365',
                        'timemodified' => '1759471365',
                        'trustbitmask' => '0',
                        'imagealt' => null,
                        'lastnamephonetic' => 'García',
                        'firstnamephonetic' => '翔太',
                        'middlename' => '秀英',
                        'alternatename' => 'Jan',
                        'moodlenetprofile' => null,
                    ]
                ],
                'iomap' => [
                    // Will be substituted.
                    '$User.id' => '12345', // Resolved, via sourcedata map, to the value of 'user_id'.
                    '$Person.name.family' => 'Doe', // Resolved, via sourcedata map, to the value of 'lis_person_name_family'.
                    '$User.username' => 'username1', // Resolved, via obj prop, to $user->username.
                    '$Context.longDescription' => 'Test course 1 Lorem ipsum.', // Resolved, via obj prop, to $course->summary.
                    // Resolved via a calculation. The expected value is based on course start date.
                    '$CourseSection.timeFrame.begin' => (new \DateTime('1759420800', new \DateTimeZone('UTC')))
                        ->format(\DateTime::ATOM),

                    // Will not be substituted.
                    '$Person.name.given' => '$Person.name.given', // Not an enabled capability, so not substituted.
                    '$Unmapped.var.example' => '$Unmapped.var.example', // Policy permits it, but not mapped, so not substituted.
                    'cat' => 'cat', // Not a variable, so not substituted.
                ],
            ],
        ];
    }
}
