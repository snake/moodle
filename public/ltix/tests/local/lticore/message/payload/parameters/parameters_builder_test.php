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

namespace local\lticore\message\payload\parameters;

use core_ltix\constants;
use core_ltix\local\lticore\facades\service\resource_link_launch_service_facade;
use core_ltix\local\lticore\message\payload\parameters\builders\parameters_builder;
use core_ltix\local\lticore\message\payload\parameters\resolvers\common\context_resolver;
use core_ltix\local\lticore\message\payload\parameters\resolvers\custom\resource_link_launch_custom_resolver;
use core_ltix\local\lticore\message\payload\parameters\resolvers\launch_presentation\launch_presentation_resolver;
use core_ltix\local\lticore\message\payload\parameters\resolvers\lis\lis_bo_resolver;
use core_ltix\local\lticore\message\payload\parameters\resolvers\lis\lis_resolver;
use core_ltix\local\lticore\message\payload\parameters\resolvers\policy\pii_policy;
use core_ltix\local\lticore\message\payload\parameters\resolvers\service\ltixservice_resolver;
use core_ltix\local\lticore\message\payload\parameters\resolvers\tool_consumer\tool_consumer_resolver;
use core_ltix\local\lticore\message\payload\parameters\resolvers\transforms\custom_parameter_normalisation_mode;
use core_ltix\local\lticore\message\payload\parameters\resolvers\transforms\custom_parameter_normaliser;
use core_ltix\local\lticore\models\resource_link;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests covering parameters_builder.
 *
 * @package    core_ltix
 * @copyright  2025 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(parameters_builder::class)]
class parameters_builder_test extends \basic_testcase {

    public function test_build(): void {

        $course = (object) [
            'id' => '100000',
            'category' => '1',
            'sortorder' => '0',
            'fullname' => 'Test course 1',
            'shortname' => 'tc_1',
            'idnumber' => '',
            'summary' => 'Test course 1
Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Nulla non arcu lacinia neque faucibus fringilla. Vivamus porttitor turpis ac leo. Integer in sapien. Nullam eget nisl. Aliquam erat volutpat. Cras elementum. Mauris suscipit, ligula sit amet pharetra semper, nibh ante cursus purus, vel sagittis velit mauris vel metus. Integer malesuada. Nullam lectus justo, vulputate eget mollis sed, tempor sed magna. Mauris elementum mauris vitae tortor. Aliquam erat volutpat.
Temporibus autem quibusdam et aut officiis debitis aut rerum necessitatibus saepe eveniet ut et voluptates repudiandae sint et molestiae non recusandae. Pellentesque ipsum. Cras pede libero, dapibus nec, pretium sit amet, tempor quis. Aliquam ante. Proin in tellus sit amet nibh dignissim sagittis. Vivamus porttitor turpis ac leo. Duis bibendum, lectus ut viverra rhoncus, dolor nunc faucibus libero, eget facilisis enim ipsum id lacus. In sem justo, commodo ut, suscipit at, pharetra vitae, orci. Aliquam erat volutpat. Nulla est.
Vivamus luctus egestas leo. Aenean fermentum risus id tortor. Mauris dictum facilisis augue. Aliquam erat volutpat. Aliquam ornare wisi eu metus. Aliquam id dolor. Duis condimentum augue id magna semper rutrum. Donec iaculis gravida nulla. Pellentesque ipsum. Etiam dictum tincidunt diam. Quisque tincidunt scelerisque libero. Etiam egestas wisi a erat.
Integer lacinia. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Mauris tincidunt sem sed arcu. Nullam feugiat, turpis at pulvinar vulputate, erat libero tristique tellus, nec bibendum odio risus sit amet ante. Aliquam id dolor. Maecenas sollicitudin. Et harum quidem rerum facilis est et expedita distinctio. Mauris suscipit, ligula sit amet pharetra semper, nibh ante cursus purus, vel sagittis velit mauris vel metus. Nullam dapibus fermentum ipsum. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Pellentesque sapien. Duis risus. Mauris elementum mauris vitae tortor. Suspendisse nisl. Integer rutrum, orci vestibulum ullamcorper ultricies, lacus quam ultricies odio, vitae placerat pede sem sit amet enim.
In laoreet, magna id viverra tincidunt, sem odio bibendum justo, vel imperdiet sapien wisi sed libero. Proin pede metus, vulputate nec, fermentum fringilla, vehicula vitae, justo. Nullam justo enim, consectetuer nec, ullamcorper ac, vestibulum in, elit. Quis autem vel eum iure reprehenderit qui in ea voluptate velit esse quam nihil molestiae consequatur, vel illum qui dolorem eum fugiat quo voluptas nulla pariatur? Maecenas lorem. Etiam posuere lacus quis dolor. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos hymenaeos. Curabitur ligula sapien, pulvinar a vestibulum quis, facilisis vel sapien. Nam sed tellus id magna elementum tincidunt. Suspendisse nisl. Vivamus luctus egestas leo. Nulla non arcu lacinia neque faucibus fringilla. Etiam dui sem, fermentum vitae, sagittis id, malesuada in, quam. Etiam dictum tincidunt diam. Etiam commodo dui eget wisi. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Proin pede metus, vulputate nec, fermentum fringilla, vehicula vitae, justo. Duis ante orci, molestie vitae vehicula venenatis, tincidunt ac pede. Pellentesque sapien.',
            'summaryformat' => '0',
            'format' => 'topics',
            'showgrades' => '1',
            'newsitems' => '0',
            'startdate' => '1765382400',
            'enddate' => '0',
            'relativedatesmode' => '0',
            'marker' => '0',
            'maxbytes' => '0',
            'legacyfiles' => '0',
            'showreports' => '0',
            'visible' => '1',
            'visibleold' => '1',
            'downloadcontent' => NULL,
            'groupmode' => '0',
            'groupmodeforce' => '0',
            'defaultgroupingid' => '0',
            'lang' => '',
            'calendartype' => '',
            'theme' => '',
            'timecreated' => '1765422564',
            'timemodified' => '1765422564',
            'requested' => '0',
            'enablecompletion' => '0',
            'completionnotify' => '0',
            'cacherev' => '0',
            'originalcourseid' => NULL,
            'showactivitydates' => '0',
            'showcompletionconditions' => '1',
            'pdfexportfont' => NULL,
            'hiddensections' => 1,
            'coursedisplay' => 0,
        ];

        $user = (object) [
            'id' => '103000',
            'auth' => 'manual',
            'confirmed' => '1',
            'policyagreed' => '0',
            'deleted' => '0',
            'suspended' => '0',
            'mnethostid' => '1',
            'username' => 'username1',
            'password' => '',
            'idnumber' => 'UID:U123',
            'firstname' => '美羽',
            'lastname' => '斎藤',
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
            'description' => NULL,
            'descriptionformat' => '1',
            'mailformat' => '1',
            'maildigest' => '0',
            'maildisplay' => '2',
            'autosubscribe' => '1',
            'trackforums' => '0',
            'timecreated' => '1765442016',
            'timemodified' => '1765442016',
            'trustbitmask' => '0',
            'imagealt' => NULL,
            'lastnamephonetic' => '高橋',
            'firstnamephonetic' => 'Michael',
            'middlename' => 'Leah',
            'alternatename' => '娜',
            'moodlenetprofile' => NULL,
        ];

        $toolconfig = (object) [
            'typeid' => 123,
            'lti_clientid' => '123456-abcd',
            'lti_ltiversion' => '1.3.0',
            'lti_initiatelogin' => 'https://tool.example.com/lti/initiatelogin',
            'lti_organizationid' => 'https://platform.example.com',
            'lti_launchcontainer' => constants::LTI_LAUNCH_CONTAINER_EMBED_NO_BLOCKS,
            'lti_acceptgrades' => constants::LTI_SETTING_ALWAYS,
            'ltixservice_gradesynchronization' => 2,
            'ltixservice_memberships' => 1,
            'lti_customparameters' => "idnumber=\$Person.sourcedId\nuser#TIME#zone=\$Person.address.timezone\n".
                "toollevelparam=test\nsubContainingPII=\$Person.name.full\nsome#PARAM=234",
        ];

        $linkcustomparams = [
            'something' => 'attempt_to_override_service_data',
            'linkparam' => 123,
            'toollevelparam' => 'overridden_by_link',
        ];

        $resourcelink = new resource_link(0, (object) [
            'typeid' => 123,
            'contextid' => 456,
            'url' => 'https://tool.example.com/lti/resource/1',
            'title' => 'Resource 1',
            'text' => 'A plain text description of resource 1',
            // TODO: We need to drop the format from the resource link text.
            //  It's supposed to be plain text and this is unnecessary complication.
            'textformat' => FORMAT_PLAIN,
            'launchcontainer' => constants::LTI_LAUNCH_CONTAINER_DEFAULT, // Defer to tool configuration value.
            'customparams' => http_build_query($linkcustomparams, '', "\n"),
            'gradable' => true,
            'servicesalt' => 'abc123',
        ]);

        // Mock the service layer to remove plugin dependency from the test.
// Stub service claim builder simulating both target_link_uri override and custom parameter inclusion for services.
        $rllservicefacadestub = $this->createStub(resource_link_launch_service_facade::class);
        $rllservicefacadestub->method('get_launch_parameters')
            ->willReturn([
                'something' => 'custom service data trumps link-level data with the same name',
                'substitution' => '$User.username',
                'my_claim' => 'abc,def',
                'service_substitution_2' => '$Context.timeFrame.begin',
                'another_custom_claim' => 'https://lms.example.com/something',
                'example_service_param' => '$Service.substitution.example'
            ]);


        // Test wiring up the parameters for a given message type and version.
        $builder = new parameters_builder();
        $params = $builder->with('parameter1', 'value1')
            ->with('parameter2', 'value2')
            ->add_resolver(new context_resolver($course))
            // TODO: custom params vary based on message type (RLL vs DL [no link exists so no link-level custom] vs SR etc.)
            //->add_resolver(new custom_resolver($toolconfig))
            ->add_resolver(new resource_link_launch_custom_resolver($toolconfig, $resourcelink))
            ->add_resolver(new custom_parameter_normaliser(custom_parameter_normalisation_mode::MODE_BOTH))
            ->add_resolver(new lis_resolver($toolconfig, $course, $user))
            ->add_resolver(new lis_bo_resolver($toolconfig, $resourcelink, $user))
            ->add_resolver(new tool_consumer_resolver($toolconfig))
            ->add_resolver(new launch_presentation_resolver($toolconfig, $resourcelink))
            ->add_resolver(new ltixservice_resolver($rllservicefacadestub))
            ->add_resolver(new pii_policy($toolconfig))
            ->build();

        ksort($params);

        if (true) {

        }

    }
}
