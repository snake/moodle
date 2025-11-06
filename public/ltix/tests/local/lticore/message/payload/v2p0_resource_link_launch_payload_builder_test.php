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

namespace local\lticore\message\payload;

use core_ltix\constants;
use core_ltix\helper;
use core_ltix\local\lticore\message\payload\custom\common_parameter_substitutor;
use core_ltix\local\lticore\message\payload\custom\custom_param_parser;
use core_ltix\local\lticore\message\payload\custom\v2p0_custom_param_parser;
use core_ltix\local\lticore\message\payload\custom\v2p0_parameter_substitutor;
use core_ltix\local\lticore\message\payload\v2p0_resource_link_launch_payload_builder;
use core_ltix\local\lticore\models\resource_link;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Test class covering the LTI 2p0 resource link launch payload builder.
 *
 * @package    core_ltix
 * @copyright  2025 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(v2p0_resource_link_launch_payload_builder::class)]
final class v2p0_resource_link_launch_payload_builder_test extends \advanced_testcase {

    /**
     * Test building the payload claims.
     *
     * @return void
     */
    public function test_get_claims(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['idnumber' => 'CID:C123']);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher', ['idnumber' => 'UID:U123']);
        $context = \core\context\course::instance($course->id);

        // TODO: we need a new container for tooltype(lti_types) + toolconfig(lti_types_config) data.
        //  helper::get_type() only returns lti_types record, no config.
        //  helper::get_type_config() doesn't return enough information from lti_types.
        //  helper::get_type_type_config() prefixes everything with lti_ which isn't usable by some APIs expecting just
        //  $config->ltiversion or $config->launchcontainer, etc.

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
            'something' => 'attempt_to_override_parameter_data',
            'linkparam' => 123,
            'toollevelparam' => 'overridden_by_link',
        ];

        $resourcelink = new resource_link(0, (object) [
            'typeid' => 123,
            'contextid' => $context->id,
            'url' => 'https://tool.example.com/lti/resource/1',
            'title' => 'Resource 1',
            'text' => 'A plain text description of resource 1',
            'textformat' => FORMAT_PLAIN,
            'launchcontainer' => constants::LTI_LAUNCH_CONTAINER_DEFAULT,
            'customparams' => http_build_query($linkcustomparams, '', "\n"),
            'gradable' => true,
            'servicesalt' => 'abc123',
        ]);

        // TODO set up tool parameter and tool settings values, which become custom params.

        // Create payload, verifying all params.
        // Note: the custom_param_parser dependency is NOT given a $user, and therefore won't resolve user data substitutions.
        // This reflects how launch payload is expected to be created, prior to the auth stage, when user-centric substitution
        // variables are resolved once the user is auth'd.
        $payloadbuilder = new v2p0_resource_link_launch_payload_builder(
            toolconfig: $toolconfig,
            resourcelink: $resourcelink,
            user: $user,
            paramsubstitutor: new v2p0_parameter_substitutor(
                new common_parameter_substitutor(helper::get_capabilities(), $context, $user),
                $toolconfig,
            ),
        );
        $params = $payloadbuilder->get_params();
//
//        // Any required claims for a Resource Link Launch Request are resolved by the request builders for the respective
//        // message type. As such we don't expect to see that data here.
//        $this->assertArrayNotHasKey(constants::LTI_JWT_CLAIM_PREFIX.'/claim/target_link_uri', $claims);
//        $this->assertArrayNotHasKey(constants::LTI_JWT_CLAIM_PREFIX.'/claim/resource_link', $claims);
//        $this->assertArrayNotHasKey(constants::LTI_JWT_CLAIM_PREFIX.'/claim/target_link_uri', $claims);
//        $this->assertArrayNotHasKey(constants::LTI_JWT_CLAIM_PREFIX.'/claim/version', $claims);
//        $this->assertArrayNotHasKey(constants::LTI_JWT_CLAIM_PREFIX.'/claim/deployment_id', $claims);
//        $this->assertArrayNotHasKey(constants::LTI_JWT_CLAIM_PREFIX.'/claim/message_type', $claims);
//        $this->assertArrayNotHasKey(constants::LTI_JWT_CLAIM_PREFIX.'/claim/roles', $claims);
//
//        // Context claim.
//        $this->assertEquals($course->id, $claims[constants::LTI_JWT_CLAIM_PREFIX.'/claim/context']['id']);
//        $this->assertEquals($context->get_context_name(), $claims[constants::LTI_JWT_CLAIM_PREFIX.'/claim/context']['label']);
//        $this->assertEquals($context->get_context_name(), $claims[constants::LTI_JWT_CLAIM_PREFIX.'/claim/context']['title']);
//        $this->assertEquals(
//            ['http://purl.imsglobal.org/vocab/lis/v2/course#CourseSection'],
//            $claims[constants::LTI_JWT_CLAIM_PREFIX.'/claim/context']['type']
//        );
//
//        // Tool platform claim.
//        global $CFG;
//        $this->assertEquals("moodle", $claims[constants::LTI_JWT_CLAIM_PREFIX.'/claim/tool_platform']['product_family_code']);
//        $this->assertEquals(strval($CFG->version), $claims[constants::LTI_JWT_CLAIM_PREFIX.'/claim/tool_platform']['version']);
//        $this->assertEquals(
//            helper::get_organizationid((array)$toolconfig),
//            $claims[constants::LTI_JWT_CLAIM_PREFIX.'/claim/tool_platform']['guid']
//        );
//        // TODO: platform name can be resovled from CFG vars and there's a mod_lti fallback, so probably also want to test that,
//        //  perhaps in a separate test case.
//        $this->assertEquals(
//            get_site()->shortname,
//            $claims[constants::LTI_JWT_CLAIM_PREFIX.'/claim/tool_platform']['name']
//        );
//        $this->assertEquals(
//            trim(html_to_text(get_site()->fullname, 0)),
//            $claims[constants::LTI_JWT_CLAIM_PREFIX.'/claim/tool_platform']['description']
//        );
//
//        // Launch presentation claim.
//        // TODO: current language uses the global USER which isn't ideal inside an builder that is scoped TO a specific user.
//        //  we should fix that. Locale also could be considered a user-centric field and left until auth time.
//        $this->assertEquals(
//            current_language(),
//            $claims[constants::LTI_JWT_CLAIM_PREFIX.'/claim/launch_presentation']['locale']
//        );
//        // TODO: There are several variations to document target, depending on tool configuration, so we'll want to test that works,
//        //  probably in another test case.
//        $this->assertEquals('iframe', $claims[constants::LTI_JWT_CLAIM_PREFIX.'/claim/launch_presentation']['document_target']);
//        $this->assertStringContainsString(
//            'ltix/return.php',
//            $claims[constants::LTI_JWT_CLAIM_PREFIX.'/claim/launch_presentation']['return_url']
//        );
//
//        // LIS claim.
//        // Note: in LTI 1p3, the person_sourcedid - being user-centric - is added at auth time and should not be present yet.
//        $this->assertArrayNotHasKey(
//            'person_sourcedid',
//            $claims[constants::LTI_JWT_CLAIM_PREFIX.'/claim/lis']
//        );
//        $this->assertEquals($course->idnumber, $claims[constants::LTI_JWT_CLAIM_PREFIX.'/claim/lis']['course_section_sourcedid']);
//
//        // Basic Outcome claim is included in LTI 1p3 because that service does officially support a 1p3 binding.
//        $this->assertArrayHasKey('lis_result_sourcedid', $claims[constants::LTI_JWT_CLAIM_PREFIX.'-bo/claim/basicoutcome']);
//        $this->assertStringContainsString(
//            'ltix/service.php',
//            $claims[constants::LTI_JWT_CLAIM_PREFIX.'-bo/claim/basicoutcome']['lis_outcome_service_url']
//        );
//
//        // TODO: add another test case covering other shipped service claims. This could also be done inside each respective
//        //  service's unit tests though, which would make more sense.
//
//        // CUSTOM CLAIM: PARAMETER ORDER-OF-PRECEDENCE AND VARIABLE SUBSTITUTION.
//        // TODO: a lot of the custom param substitution below is really behaviour controlled by the parser instance.
//        //  We could mock the parser and verify the results, or just note it here as we've already done.
//        //  We could equally just confirm that we're calling into the parser for the various custom params, and leave it at that.
//
//        // Substitution is not performed for custom params that resolve to user data.
//        // These params are substituted at auth time in LTI 1p3 and are expected to be in their non-substituted form.
//        $this->assertEquals('$Person.sourcedId', $claims[constants::LTI_JWT_CLAIM_PREFIX.'/claim/custom']['idnumber']);
//        $this->assertEquals('$Person.address.timezone', $claims[constants::LTI_JWT_CLAIM_PREFIX.'/claim/custom']['user#TIME#zone']);
//        $this->assertEquals('$Person.name.full', $claims[constants::LTI_JWT_CLAIM_PREFIX.'/claim/custom']['subContainingPII']);
//
//        // The normalised version of a custom claim key is included.
//        $this->assertArrayHasKey('user_time_zone', $claims[constants::LTI_JWT_CLAIM_PREFIX.'/claim/custom']);
//        $this->assertArrayHasKey('some_param', $claims[constants::LTI_JWT_CLAIM_PREFIX.'/claim/custom']);
//        $this->assertEquals('234', $claims[constants::LTI_JWT_CLAIM_PREFIX.'/claim/custom']['some_param']);
//
//        // Additionally, LTI 1p3 payloads include non-normalised versions of custom params.
//        $this->assertArrayHasKey('some#PARAM', $claims[constants::LTI_JWT_CLAIM_PREFIX.'/claim/custom']);
//        $this->assertEquals('234', $claims[constants::LTI_JWT_CLAIM_PREFIX.'/claim/custom']['some#PARAM']);
//
//        // A custom param on the link results in a custom claim.
//        $this->assertEquals('123', $claims[constants::LTI_JWT_CLAIM_PREFIX.'/claim/custom']['linkparam']);
//
//        // If the Resource Link contains custom params having the same name as
//        // params defined by the tool, the link params take precedence.
//        $this->assertEquals(
//            $linkcustomparams['toollevelparam'],
//            $claims[constants::LTI_JWT_CLAIM_PREFIX.'/claim/custom']['toollevelparam']
//        );
//        // If the Resource Link contains custom params having the same name as
//        // params defined by a service, the service params take precedence.
//        $this->assertEquals(
//            'custom service data trumps link-level data with the same name',
//            $claims[constants::LTI_JWT_CLAIM_PREFIX.'/claim/custom']['something']
//        );
//
//        // LTI services custom params and claims.
//
//        // Service custom param without substitution.
//        $this->assertEquals(
//            'https://lms.example.com/something',
//            $claims[constants::LTI_JWT_CLAIM_PREFIX.'/claim/custom']['another_custom_claim']
//        );
//
//        // Service custom param with substitution.
//        $this->assertEquals(
//            $course->startdate,
//            $claims[constants::LTI_JWT_CLAIM_PREFIX.'/claim/custom']['service_substitution_2']
//        );
//
//        // User-centric substitution params in service custom params are not resolved until auth time either.
//        $this->assertEquals(
//            '$User.username',
//            $claims[constants::LTI_JWT_CLAIM_PREFIX.'/claim/custom']['substitution']
//        );
//
//        // Substitution is delegated to the service facade and is resolved.
//        $this->assertEquals(
//            'value resolved by service substitution',
//            $claims[constants::LTI_JWT_CLAIM_PREFIX.'/claim/custom']['example_service_param']
//        );
//
//        // A service can define a custom param that can be claim-mapped.
//        $this->assertEquals(
//            ['abc','def'],
//            $claims[constants::LTI_JWT_CLAIM_PREFIX.'-eg/claim/service_group']['service_claim']
//        );
    }
}
