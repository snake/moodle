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

namespace local\lticore\message\request\builder\v1p3;

use core_ltix\local\lticore\message\lti_message_base;
use core_ltix\local\ltiopenid\jwks_helper;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;

/**
 * Tests covering v1p3_launch_request_builder.
 *
 * @covers     \core_ltix\local\lticore\message\request\builder\v1p3\v1p3_launch_request_builder
 * @package    core_ltix
 * @copyright  2025 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class v1p3_launch_request_builder_test extends \basic_testcase {

    /**
     * Test building the initiate login launch message.
     *
     * @return void
     */
    public function test_build_launch_request(): void {

        $toolconfig = (object) [
            'id' => '123',
            'lti_clientid' => '123456-abcd',
            'lti_ltiversion' => \core_ltix\constants::LTI_VERSION_1P3,
            'lti_initiatelogin' => 'https://tool.example.com/lti/initiatelogin',
        ];

        $builderparams = [
            'toolconfig' => $toolconfig,
            'messagetype' => 'LtiResourceLinkRequest',
            'issuer' => 'https://moodle-lms.institution.example.org',
            'targetlinkuri' => 'https://tool.example.com/some/resource',
            'loginhint' => '24',
        ];
        global $CFG;
        require_once($CFG->dirroot . '/ltix/tests/fixtures/testable_v1p3_launch_request_builder.php');
        $builder = new \core_ltix\fixtures\testable_v1p3_launch_request_builder(...$builderparams);

        $message = $builder->build_message();

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
        // TODO assert the actual message hint property values are correct too.
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
