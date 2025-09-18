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

namespace local\lticore\message\request\builder\v2p0;

use core_ltix\constants;
use core_ltix\local\lticore\message\request\builder\v2p0\v2p0_resource_link_launch_request_builder;
use core_ltix\local\lticore\models\resource_link;
use core_ltix\OAuthRequest;
use core_ltix\OAuthServer;
use core_ltix\OAuthSignatureMethod_HMAC_SHA1;
use core_ltix\TrivialOAuthDataStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests covering v2p0_resource_link_launch_request_builder.
 *
 * @package    core_ltix
 * @copyright  2025 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(v2p0_resource_link_launch_request_builder::class)]
class v2p0_resource_link_launch_request_builder_test extends \basic_testcase {

    /**
     * Test building the launch message.
     *
     * @param array $params the params to build the message.
     * @param array $expected expected properties of the message.
     * @return void
     */
    #[DataProvider('build_launch_request_provider')]
    public function test_build_launch_request(array $params, array $expected): void {
        $builderparams = [
            'toolconfig' => $params['toolconfig'],
            'resourcelink' => $params['resourcelink'],
            ...(!empty($params['roles']) ? ['roles' => $params['roles']] : []),
            ...(!empty($params['extraparams']) ? ['extraparams' => $params['extraparams']] : []),
        ];
        $builder = new v2p0_resource_link_launch_request_builder(...$builderparams);
        $message = $builder->build_message();

        $this->assertEquals($expected['url'], $message->get_url());

        // This strips the added oauth params from the message parameters array,
        // allowing comparison of the non-oauth-related params with expected values.
        $nonoauthparams = array_diff_key(
            $message->get_parameters(),
            [
                'oauth_version' => null,
                'oauth_nonce' => null,
                'oauth_timestamp' => null,
                'oauth_consumer_key' => null,
                'oauth_signature_method' => null,
                'oauth_signature' => null,
                'oauth_callback' => null,
            ]
        );
        $this->assertEquals($expected['parameters'], $nonoauthparams);

        // Verify the signature.
        $toolproxy = $params['toolconfig']->toolproxy;
        $store = new TrivialOAuthDataStore();
        $store->add_consumer($toolproxy->guid, $toolproxy->secret);
        $server = new OAuthServer($store);
        $method = new OAuthSignatureMethod_HMAC_SHA1();
        $server->add_signature_method($method);
        $request = new OAuthRequest('POST', $message->get_url(), $message->get_parameters());
        // Note: verification will throw if the signature is invalid.
        $verification = $server->verify_request($request);
        $this->assertIsArray($verification);
    }

    /**
     * Data provider for testing build_launch_request().
     *
     * @return array the test data.
     */
    public static function build_launch_request_provider(): array {
        return [
            'Required fields only' => [
                'params' => [
                    'toolconfig' => (object) [
                        'id' => '123',
                        'lti_toolurl' => 'https://tool.example.com',
                        'lti_ltiversion' => \core_ltix\constants::LTI_VERSION_2,
                        'toolproxy' => (object) [
                            'guid' => 'RDL4JE5M1wr2Ke9',
                            'secret' => '6E2KfWpbMsrw'
                        ],
                        'enabledcapability' => "User.id\nPerson.name.full",
                    ],
                    'resourcelink' => new resource_link(0, (object) [
                        'id' => 24,
                        'typeid' => 123,
                        'contextid' => 456,
                        'url' => 'https://tool.example.com/lti/resource/1',
                        'title' => 'Resource 1',
                        'text' => 'A plain text description of resource 1',
                        'launchcontainer' => constants::LTI_LAUNCH_CONTAINER_DEFAULT,
                        'customparams' => http_build_query(['mycustomparam' => '123'], '', "\n"),
                        'gradable' => true,
                        'servicesalt' => 'abc123',
                    ]),
                ],
                'expected' => [
                    'url' => 'https://tool.example.com/lti/resource/1',
                    'parameters' => [
                        'lti_version' => \core_ltix\constants::LTI_VERSION_2,
                        'lti_message_type' => 'basic-lti-launch-request',
                    ]
                ],
            ],
            'Roles and extra params can be included' => [
                'params' => [
                    'toolconfig' => (object) [
                        'id' => '123',
                        'lti_toolurl' => 'https://tool.example.com',
                        'lti_ltiversion' => \core_ltix\constants::LTI_VERSION_2,
                        'toolproxy' => (object) [
                            'guid' => 'RDL4JE5M1wr2Ke9',
                            'secret' => '6E2KfWpbMsrw'
                        ],
                        'enabledcapability' => "User.id\nMembership.role", // Membership.role capability controls role inclusion.
                    ],
                    'resourcelink' => new resource_link(0, (object) [
                        'id' => 24,
                        'typeid' => 123,
                        'contextid' => 456,
                        'url' => 'https://tool.example.com/lti/resource/1',
                        'title' => 'Resource 1',
                        'text' => 'A plain text description of resource 1',
                        'launchcontainer' => constants::LTI_LAUNCH_CONTAINER_DEFAULT,
                        'customparams' => http_build_query(['mycustomparam' => '123'], '', "\n"),
                        'gradable' => true,
                        'servicesalt' => 'abc123',
                    ]),
                    'roles' => ['Instructor', 'Learner'],
                    'extraparams' => ['myparam' => 'myparamvalue']
                ],
                'expected' => [
                    'url' => 'https://tool.example.com/lti/resource/1',
                    'parameters' => [
                        'lti_version' => \core_ltix\constants::LTI_VERSION_2,
                        'lti_message_type' => 'basic-lti-launch-request',
                        'roles' => 'Instructor,Learner',
                        'myparam' => 'myparamvalue',
                    ]
                ],
            ],
            'Roles and extra params passed, tool capabilities controlling parameter inclusion' => [
                'params' => [
                    'toolconfig' => (object) [
                        'id' => '123',
                        'lti_toolurl' => 'https://tool.example.com',
                        'lti_ltiversion' => \core_ltix\constants::LTI_VERSION_2,
                        'toolproxy' => (object) [
                            'guid' => 'RDL4JE5M1wr2Ke9',
                            'secret' => '6E2KfWpbMsrw'
                        ],
                        'enabledcapability' => "User.id\nPerson.name.full", // N.b. Person.name.full controls lis_person_name_full.
                    ],
                    'resourcelink' => new resource_link(0, (object) [
                        'id' => 24,
                        'typeid' => 123,
                        'contextid' => 456,
                        'url' => 'https://tool.example.com/lti/resource/1',
                        'title' => 'Resource 1',
                        'text' => 'A plain text description of resource 1',
                        'launchcontainer' => constants::LTI_LAUNCH_CONTAINER_DEFAULT,
                        'customparams' => http_build_query(['mycustomparam' => '123'], '', "\n"),
                        'gradable' => true,
                        'servicesalt' => 'abc123',
                    ]),
                    'roles' => ['Instructor'],
                    'extraparams' => [
                        'lis_person_name_full' => "Homer Simpson", // Will be included based on LTI capability.
                        'lis_person_name_given' => "Homer", // Will be excluded based on LTI capability.
                        'lis_person_name_family' => "Simpson", // Will be excluded based on LTI capability.
                    ]
                ],
                'expected' => [
                    'url' => 'https://tool.example.com/lti/resource/1',
                    'parameters' => [
                        'lti_version' => \core_ltix\constants::LTI_VERSION_2,
                        'lti_message_type' => 'basic-lti-launch-request',
                        'lis_person_name_full' => "Homer Simpson",
                    ]
                ],
            ],
            'URL falls back on tool URL if link URL is empty' => [
                'params' => [
                    'toolconfig' => (object) [
                        'id' => '123',
                        'lti_toolurl' => 'https://tool.example.com',
                        'lti_ltiversion' => \core_ltix\constants::LTI_VERSION_2,
                        'toolproxy' => (object) [
                            'guid' => 'RDL4JE5M1wr2Ke9',
                            'secret' => '6E2KfWpbMsrw'
                        ],
                        'enabledcapability' => "User.id\nPerson.name.full", // N.b. Person.name.full controls lis_person_name_full.
                    ],
                    'resourcelink' => new resource_link(0, (object) [
                        'id' => 24,
                        'typeid' => 123,
                        'contextid' => 456,
                        'url' => '',
                        'title' => 'Resource 1',
                        'text' => 'A plain text description of resource 1',
                        'launchcontainer' => constants::LTI_LAUNCH_CONTAINER_DEFAULT,
                        'customparams' => http_build_query(['mycustomparam' => '123'], '', "\n"),
                        'gradable' => true,
                        'servicesalt' => 'abc123',
                    ]),
                ],
                'expected' => [
                    'url' => 'https://tool.example.com',
                    'parameters' => [
                        'lti_version' => \core_ltix\constants::LTI_VERSION_2,
                        'lti_message_type' => 'basic-lti-launch-request',
                    ]
                ],
            ],
            'Extra params cannot trump required params of the same name or roles' => [
                'params' => [
                    'toolconfig' => (object) [
                        'id' => '123',
                        'lti_toolurl' => 'https://tool.example.com',
                        'lti_ltiversion' => \core_ltix\constants::LTI_VERSION_2,
                        'toolproxy' => (object) [
                            'guid' => 'RDL4JE5M1wr2Ke9',
                            'secret' => '6E2KfWpbMsrw'
                        ],
                        'enabledcapability' => "User.id\nPerson.name.full", // N.b. Person.name.full controls lis_person_name_full.
                    ],
                    'resourcelink' => new resource_link(0, (object) [
                        'id' => 24,
                        'typeid' => 123,
                        'contextid' => 456,
                        'url' => '',
                        'title' => 'Resource 1',
                        'text' => 'A plain text description of resource 1',
                        'launchcontainer' => constants::LTI_LAUNCH_CONTAINER_DEFAULT,
                        'customparams' => http_build_query(['mycustomparam' => '123'], '', "\n"),
                        'gradable' => true,
                        'servicesalt' => 'abc123',
                    ]),
                    'extraparams' => [
                        'lti_version' => '9000',
                        'lti_message_type' => 'not-a-real-message-type',
                        'roles' => 'notarole'
                    ],
                ],
                'expected' => [
                    'url' => 'https://tool.example.com',
                    'parameters' => [
                        'lti_version' => \core_ltix\constants::LTI_VERSION_2,
                        'lti_message_type' => 'basic-lti-launch-request',
                    ]
                ],
            ],
        ];
    }
}
