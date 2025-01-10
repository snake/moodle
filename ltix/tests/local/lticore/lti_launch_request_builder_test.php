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

namespace core_ltix\local\lticore;

use core_ltix\local\lticore\message\lti_message_base;
use core_ltix\local\ltiopenid\jwks_helper;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;

/**
 * Tests covering resource_link.
 *
 * @covers     \core_ltix\local\lticore\lti_launch_request_builder
 * @package    core_ltix
 * @copyright  2024 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lti_launch_request_builder_test extends \advanced_testcase {

    /**
     * Test building the initiate login launch message.
     *
     * @return void
     */
    public function test_build_launch_request(): void {

        $builder = new lti_launch_request_builder();

        $toolconfig = (object) [
            'id' => '123',
            'lti_clientid' => '123456-abcd',
            'lti_ltiversion' => '1.3.0',
            'lti_initiatelogin' => 'https://tool.example.com/lti/initiatelogin',
        ];

        $builderparams = [
            'toolconfig' => $toolconfig,
            'messagetype' => 'LtiResourceLinkRequest',
            'issuer' => 'https://moodle-lms.institution.example.org',
            'targetlinkuri' => 'https://tool.example.com/some/resource',
            'loginhint' => '24',
        ];

        $message = $builder->build_launch_request(...$builderparams);

        $this->assertInstanceOf(lti_message_base::class, $message);
        $this->assertEquals($toolconfig->lti_initiatelogin, $message->get_url());

        // The message hint is a JWT, so validate that it can be decoded by the site's corresponding key.
        $this->assertNotEmpty($message->get_parameters()['lti_message_hint']);
        $decodedltimessagehint = JWT::decode(
            $message->get_parameters()['lti_message_hint'],
            JWK::parseKeySet(jwks_helper::get_jwks())
        );
        $this->assertIsObject($decodedltimessagehint);
        $this->assertObjectHasProperty('tool_registration_id', $decodedltimessagehint);
        $this->assertObjectHasProperty('iss', $decodedltimessagehint);
        $this->assertObjectHasProperty('aud', $decodedltimessagehint);
        $this->assertObjectHasProperty(\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/message_type', $decodedltimessagehint);
        $this->assertObjectHasProperty(\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/deployment_id', $decodedltimessagehint);
        $this->assertObjectHasProperty(\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/version', $decodedltimessagehint);
        $this->assertObjectHasProperty('nonce', $decodedltimessagehint);
        // TODO assert the actual message hint property values are correct.
    }

    // TODO: we need to update this and make the above test use it, perhaps with a deep linking message example data set.
    public function launch_request_provider(): array {
        return [
            'minimal set, required fields only' => [
                'setdata' => [
                    'typeid' => 4,
                    'contextid' => 33,
                    'url' => (new \moodle_url('http://tool.example.com/my/resource'))->out(false),
                    'title' => 'My resource',
                ],
                'expecteddata' => [
                    'typeid' => 4,
                    'contextid' => 33,
                    'legacyid' => null,
                    // Note: can't check UUID in this case since it's a randomly generated default, so it's omitted.
                    'url' => 'http://tool.example.com/my/resource',
                    'title' => 'My resource',
                    'text' => null,
                    'textformat' => FORMAT_MOODLE,
                    'launchcontainer' => \core_ltix\constants::LTI_LAUNCH_CONTAINER_DEFAULT,
                    'customparams' => null,
                    'icon' => null,
                    'servicesalt' => null,
                ],
            ],
        ];
    }
}
