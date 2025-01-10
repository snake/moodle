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

namespace local\lticore;

use core_ltix\local\lticore\lti_resource_link_launch_request_builder;
use core_ltix\local\lticore\message\lti_message_base;
use core_ltix\local\lticore\models\resource_link;
use core_ltix\local\ltiopenid\jwks_helper;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use core_ltix\local\lticore\facades\service\resource_link_launch_service_facade;

/**
 * Tests covering resource_link.
 *
 * @covers     \core_ltix\local\lticore\lti_launch_request_builder
 * @package    core_ltix
 * @copyright  2024 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lti_resource_link_launch_request_builder_test extends \advanced_testcase {

    /**
     * Test building the initiate login launch message.
     *
     * @return void
     */
    public function test_build_resource_link_launch_request(): void {
        // TODO: If we pass in a token builder, or something like that, we can then just confirm it's called for the expected claims
        //  and remove any requirements to hit the DB here. We're only concerned with verifying:
        //  - all expected RL claims + LTI message claims are present.
        //  - any extra claims are also present
        //  - verifying some other claim builder is called (one that deals with resolving the domain-specific claims).
        //  Others to consider (basically additional claims that we expect need Moodle domain logic to resolve):
        //  - custom claim (custom params, substitution params)
        //  - any claims added by configured service plugins
        //    - How do we handle these? another object to fetch those claims for a given tool config + messagetype?
        //      - e.g. resource_link_custom_claim_builder

        $this->resetAfterTest();

        global $CFG;

        $course = $this->getDataGenerator()->create_course(['idnumber' => 'CID:C123']);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher', ['idnumber' => 'UID:U123']);
        $context = \core\context\course::instance($course->id);

        $builder = new lti_resource_link_launch_request_builder();

        // TODO: we need a new container for tooltype(lti_types) + toolconfig(lti_types_config) data.
        //  helper::get_type() only returns lti_types record, no config.
        //  helper::get_type_config() doesn't return enough information from lti_types.
        //  helper::get_type_type_config() prefixes everything with lti_ which isn't usable by some APIs expecting just
        //  $config->ltiversion or $config->launchcontainer, etc.

        $toolconfig = (object) [
            'id' => 123,
            'lti_clientid' => '123456-abcd',
            'lti_ltiversion' => '1.3.0',
            'lti_initiatelogin' => 'https://tool.example.com/lti/initiatelogin',
            'lti_organizationid' => 'https://platform.example.com',
            'lti_launchcontainer' => \core_ltix\constants::LTI_LAUNCH_CONTAINER_EMBED_NO_BLOCKS,
            'ltixservice_gradesynchronization' => 2,
            'ltixservice_memberships' => 1,
            'lti_customparameters' => "idnumber=\$Person.sourcedId\nuser#TIME#zone=\$Person.address.timezone\n".
                "toollevelparam=test\nsubContainingPII=\$Person.name.full",
        ];
        // TODO: The above Person.address.timezone  required a global $USER;
        $this->setAdminUser();

        $resourcelink = new resource_link(0, (object) [
            'typeid' => 123,
            'contextid' => $context->id,
            'url' => 'https://tool.example.com/lti/resource/1',
            'title' => 'Resource 1',
            'launchcontainer' => \core_ltix\constants::LTI_LAUNCH_CONTAINER_DEFAULT, // Defer to tool configuration value.
            'customparams' => "something=attempt_to_override_service_data\nlinkparam=123\ntoollevelparam=overridden_by_link",
        ]);

        // Stub service claim builder simulating both target_link_uri override and custom parameter inclusion for services.
        $servicefacadestub = $this->createStub(resource_link_launch_service_facade::class);
        $servicefacadestub->method('get_target_link_uri')
            ->willReturn('https://tool.example.com/lti/resource/1/subreviewlaunch');
        $servicefacadestub->method('get_launch_parameters')
            ->willReturn([
                'something' => 'custom service data trumps link-level data with the same name',
                'substitution' => '$User.username'
            ]);

        $builderparams = [
            'toolconfig' => $toolconfig,
            'resourcelink' => $resourcelink,
            'servicefacade' => $servicefacadestub,
            'issuer' => 'https://moodle-lms.institution.example.org',
            'userid' => $user->id,
            'extraclaims' => [
                'some claim' => ['item1', 'item2']
            ]
        ];

        $message = $builder->build_resource_link_launch_request(...$builderparams);

        $this->assertInstanceOf(lti_message_base::class, $message);
        $this->assertEquals($toolconfig->lti_initiatelogin, $message->get_url());

        // TODO: we need to eventually generate an lti_message for both 1.1 and 1.3 tools and verify their payloads accordingly.
        //  i.e. the builder will eventually be version aware, per the version included in the tool config.

        // The message hint is a JWT, so validate that it can be decoded by the site's corresponding key.
        $this->assertNotEmpty($message->get_parameters()['lti_message_hint']);
        $decodedltimessagehint = JWT::decode(
            $message->get_parameters()['lti_message_hint'],
            JWK::parseKeySet(jwks_helper::get_jwks())
        );
        $decodedltimessagehint = json_decode(json_encode($decodedltimessagehint), true); // Use array format.

        // Core claims for any message.
        $this->assertArrayHasKey('tool_registration_id', $decodedltimessagehint);
        $this->assertArrayHasKey('iss', $decodedltimessagehint);
        $this->assertArrayHasKey('aud', $decodedltimessagehint);
        $this->assertArrayHasKey('exp', $decodedltimessagehint);
        $this->assertArrayHasKey('iat', $decodedltimessagehint);
        $this->assertArrayHasKey('nonce', $decodedltimessagehint);
        $this->assertArrayHasKey(\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/message_type', $decodedltimessagehint);
        $this->assertArrayHasKey(\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/deployment_id', $decodedltimessagehint);
        $this->assertArrayHasKey(\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/version', $decodedltimessagehint);

        // Custom claim properties, including substitution params.
        $this->assertEquals($user->idnumber, $decodedltimessagehint[\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/custom']['idnumber']);
        $this->assertEquals($user->timezone, $decodedltimessagehint[\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/custom']['user_time_zone']);
        $this->assertEquals($user->timezone, $decodedltimessagehint[\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/custom']['user#TIME#zone']);
        $this->assertEquals('overridden_by_link', $decodedltimessagehint[\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/custom']['toollevelparam']);
        $this->assertEquals(
            'custom service data trumps link-level data with the same name',
            $decodedltimessagehint[\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/custom']['something']
        );
        $this->assertEquals('123', $decodedltimessagehint[\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/custom']['linkparam']);
        $this->assertEquals($user->username, $decodedltimessagehint[\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/custom']['substitution']);

        // Substitution params resolving to PII aren't resolved until the OIDC authentication stage.
        $this->assertEquals('$Person.name.full', $decodedltimessagehint[\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/custom']['subContainingPII']);

        // Context claim.
        $this->assertEquals($course->id, $decodedltimessagehint[\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/context']['id']);
        $this->assertEquals($context->get_context_name(), $decodedltimessagehint[\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/context']['label']);
        $this->assertEquals($context->get_context_name(), $decodedltimessagehint[\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/context']['title']);
        $this->assertEquals(
            ['http://purl.imsglobal.org/vocab/lis/v2/course#CourseSection'],
            $decodedltimessagehint[\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/context']['type']
        );

        // Resource link claim.
        $this->assertEquals($resourcelink->get('uuid'), $decodedltimessagehint[\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/resource_link']['id']);
        $this->assertEquals(
            $resourcelink->get('title'),
            $decodedltimessagehint[\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/resource_link']['title']
        );

        // Tool platform claim.
        $this->assertEquals('moodle', $decodedltimessagehint[\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/tool_platform']['product_family_code']);
        $this->assertEquals(strval($CFG->version), $decodedltimessagehint[\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/tool_platform']['version']);
        $this->assertIsString($decodedltimessagehint[\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/tool_platform']['guid']); // TODO confirm value.
        $this->assertEquals(get_site()->shortname, $decodedltimessagehint[\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/tool_platform']['name']);
        $this->assertEquals(
            trim(html_to_text(get_site()->fullname, 0)),
            $decodedltimessagehint[\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/tool_platform']['description']
        );

        // Launch presentation claim.
        // Launch container expected to be 'iframe' since the link uses the 'default' launch container, and the tool registration
        // defined the default as "embed, without blocks".
        $this->assertEquals(current_language(), $decodedltimessagehint[\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/launch_presentation']['locale']);
        $this->assertEquals('iframe', $decodedltimessagehint[\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/launch_presentation']['document_target']);
        $this->assertStringContainsString(
            'ltix/return.php',
            $decodedltimessagehint[\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/launch_presentation']['return_url']
        );

        // LIS claim.
        $this->assertEquals($user->idnumber, $decodedltimessagehint[\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/lis']['person_sourcedid']);
        $this->assertEquals(
            $course->idnumber,
            $decodedltimessagehint[\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/lis']['course_section_sourcedid']
        );

        // Roles claim.
        $this->assertContains(
            'http://purl.imsglobal.org/vocab/lis/v2/membership#Instructor',
            $decodedltimessagehint[\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/roles'],
        );

        // Target link URI claim.
        $this->assertEquals($resourcelink->get('url'), $decodedltimessagehint[\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/target_link_uri']);

        // Extra claims are present and the custom claim of the same name
        // returned by the service claim builder has been overridden by the extra claim.
        $this->assertEquals(array_shift($builderparams['extraclaims']), $decodedltimessagehint['some claim']);
    }
}
