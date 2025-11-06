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

namespace local\ltiopenid;

use core_ltix\local\lticore\exception\lti_exception;
use core_ltix\local\lticore\message\payload\custom\custom_param_parser;
use core_ltix\local\lticore\message\payload\custom\factory\custom_param_parser_factory;
use core_ltix\local\lticore\message\payload\lis_vocab_converter;
use core_ltix\local\lticore\message\payload\lti_1px_payload_converter;
use core_ltix\local\lticore\message\substitition\resolver\resolve_context;
use core_ltix\local\lticore\message\substitition\v1px_variable_substitutor;
use core_ltix\local\lticore\message\substitition\variable_substitutor;
use core_ltix\local\lticore\message\substitition\variable_substitutor_factory;
use core_ltix\local\lticore\repository\tool_registration_repository;
use core_ltix\local\lticore\token\lti_token;
use core_ltix\local\ltiopenid\jwks_helper;
use core_ltix\local\ltiopenid\lti_auth_result;
use core_ltix\local\ltiopenid\lti_oidc_authenticator;
use core_ltix\local\ltiopenid\lti_user;
use core_ltix\local\ltiopenid\lti_user_authenticator;
use core_ltix\oauth_helper;

/**
 * Tests covering lti_oidc_authenticator.
 *
 * @covers \core_ltix\local\ltiopenid\lti_oidc_authenticator
 * @package    core_ltix
 * @copyright  2025 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lti_oidc_authenticator_test extends \basic_testcase {

    /**
     * Helper to fetch the signing key pair (private key + jwks).
     *
     * @return array the key pair.
     */
    protected static function get_ltix_key_pair(): array {
        return [
            'privatekey' => [
                'kid' => get_config('core_ltix', 'kid'),
                'key' => get_config('core_ltix', 'privatekey'),
            ],
            'jwks' => jwks_helper::get_jwks(),
        ];
    }

    /**
     * Create stub objects used in tests.
     *
     * @param array $authinfo object containing success flag and null|lti_user object instance.
     * @param object $toolconfig the tool registration.
     * @return array
     */
    protected function create_stubs(array $authinfo, object $toolconfig): array {
        // If an auth'd user is being mocked, get it.
        $authuser = $authinfo['auth_user'];
        $authsuccess = $authinfo['auth_success'];

        // Stub lti_user_authenticator.
        $stubuserauthenticator = $this->createStub(lti_user_authenticator::class);
        $stubuserauthenticator->method('authenticate')
            ->willReturn(
                new lti_auth_result(
                    successful: $authsuccess,
                    ltiuser: $authuser,
                )
            );

        // Stub registration_repository, returning the tool config.
        $stubregistrationrepo = $this->createStub(tool_registration_repository::class);
        $stubregistrationrepo->method('get_by_id')
            ->willReturn($toolconfig);

        // Stub a substitutor instance.
        // This example just substitutes any $Person.xx param with the user's full name, otherwise returns the unmodified value.
        $stubcustomparamparser = $this->createStub(variable_substitutor::class);
        $stubcustomparamparser->method('substitute')
            ->willReturnCallback(function(array $customparams, resolve_context $resolvecontext) use ($authuser) {
                $userdata = $authuser->get_unformatted_userdata();
                return array_map(function($customparam) use ($userdata) {
                    if (str_starts_with($customparam, '$Person.')) {
                        return !empty($userdata['lis_person_name_full']) ? $userdata['lis_person_name_full'] : $customparam;
                    }
                    return $customparam;
                }, $customparams);
            });
        $stubcustomparamparserfactory = $this->createStub(variable_substitutor_factory::class);
        $stubcustomparamparserfactory->method('get_for_tool')
            ->willReturn($stubcustomparamparser);

        return [
            'stubuserauthenticator' => $stubuserauthenticator,
            'stubregistrationrepo' => $stubregistrationrepo,
            'stubcustomparamparserfactory' => $stubcustomparamparserfactory,
        ];
    }

    /**
     * Test the authenticate() method.
     *
     * @dataProvider authenticate_data_provider
     * @param array $authinfo mock data for user auth.
     * @param object $toolconfig mock tool config/registration.
     * @param array $authrequestpayload mock auth request payload.
     * @param array $keys the private + public (via JWKS) key pair.
     * @param array $expected the array of expected lti_message data.
     * @return void
     */
    public function test_authenticate(
        array $authinfo,
        object $toolconfig,
        array $authrequestpayload,
        array $keys,
        array $expected
    ): void {

        [
            'stubuserauthenticator' => $stubuserauthenticator,
            'stubregistrationrepo' => $stubregistrationrepo,
            'stubcustomparamparserfactory' => $stubcustomparamparserfactory,
        ] = $this->create_stubs($authinfo, $toolconfig);

        $oidcauthenticator = new lti_oidc_authenticator(
            userauthenticator: $stubuserauthenticator,
            registrationrepository: $stubregistrationrepo,
            payloadconverter: new lti_1px_payload_converter(new lis_vocab_converter()),
            substitutorfactory: $stubcustomparamparserfactory,
            jwks: $keys['jwks']
        );

        if (!empty($expected['auth_exception'])) {
            $this->expectException($expected['auth_exception']);
            if (!empty($expected['auth_exception_contains_text'])) {
                $this->expectExceptionMessageMatches('/.*'.$expected['auth_exception_contains_text'].'.*/');
            }
        }
        $ltimessage = $oidcauthenticator->authenticate($authrequestpayload);

        // Verify signed JWT.
        $ltitoken = lti_token::from_jwt_with_keyset(
            $ltimessage->get_parameters()['id_token'],
            $keys['jwks']
        );

        // Verify message state.
        $this->assertEquals($expected['state'], $ltimessage->get_parameters()['state']);

        // Verify expected claims in the JWT.
        foreach ($expected['jwt_claims'] as $claimname => $claimvalue) {
            $this->assertEquals($claimvalue, $ltitoken->get_claim($claimname));
        }
    }

    /**
     * Provider for testing authenticate().
     *
     * @return array the test case data.
     */
    public static function authenticate_data_provider(): array {
        $keys = self::get_ltix_key_pair();
        return [
            'Valid auth, user is returned by the user_authenticator dependency' => [
                'authinfo' => [
                    'auth_success' => true,
                    'auth_user' => new lti_user(
                        id: '340',
                        name: 'Kermit DaFrog',
                        givenname: 'Kermit',
                        familyname: 'DaFrog',
                        email: 'kermit@dakermitroom.com',
                        idnumber: 'kf340',
                        username: 'kfrog',
                    ),
                ],
                'toolconfig' => (object) [
                    'id' => 123,
                    'lti_clientid' => '123456-abcd',
                    'lti_ltiversion' => '1.3.0',
                    'lti_sendname' => \core_ltix\constants::LTI_SETTING_ALWAYS,
                    'lti_sendemailaddr' => \core_ltix\constants::LTI_SETTING_ALWAYS,
                    'lti_initiatelogin' => 'https://tool.example.com/lti/initiatelogin',
                    'lti_organizationid' => 'https://platform.example.com',
                    'lti_launchcontainer' => \core_ltix\constants::LTI_LAUNCH_CONTAINER_EMBED_NO_BLOCKS,
                    'lti_redirectionuris' => "https://tool.example.com/lti/redirecturi\nhttps://tool.example.com/lti/redirecturi2",
                    'ltixservice_gradesynchronization' => 2,
                    'ltixservice_memberships' => 1,
                    'lti_customparameters' => "idnumber=\$Person.sourcedId\nuser#TIME#zone=\$Person.address.timezone\n".
                        "toollevelparam=test\nsubContainingPII=\$Person.name.full",
                ],
                'authrequestpayload' => [
                    'lti_message_hint' => (new lti_token([
                        'tool_registration_id' => 123, // Matches toolconfig.id.
                        \core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/custom' => [
                            'subContainingPII' => '$Person.name.full'
                        ]
                    ]))->to_jwt(privatekey: $keys['privatekey']['key'], kid: $keys['privatekey']['kid']),
                    'lti_deployment_id' => 123, // Matches toolconfig.id.
                    'scope' => 'openid',
                    'response_type' => 'id_token',
                    'client_id' => '123456-abcd', // Matches toolconfig.lti_clientid.
                    'redirect_uri' => 'https://tool.example.com/lti/redirecturi', // Must match one defined in toolconfig.
                    'login_hint' => '340', // Matches auth_user.id.
                    'response_mode' => 'form_post',
                    'prompt' => 'none',
                    'nonce' => 'TOOL-NONCE-abc-123', // Set by the tool. Opaque to the platform.
                    'state' => 'TOOL-STATE-1234', // Set by the tool. Opaque to the platform.
                ],
                'keys' => $keys,
                'expected' => [
                    'state' => 'TOOL-STATE-1234', // Matches the auth request payload state.
                    'jwt_claims' => [
                        'sub' => '340',
                        'name' => 'Kermit DaFrog',
                        'given_name' => 'Kermit',
                        'family_name' => 'DaFrog',
                        'email' => 'kermit@dakermitroom.com',
                        \core_ltix\constants::LTI_JWT_CLAIM_PREFIX . '/claim/ext' => [
                            'user_username' => 'kfrog',
                        ],
                        'nonce' => 'TOOL-NONCE-abc-123', // Matches auth request payload nonce.
                        \core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/custom' => [
                            // Stub custom param resolver resolves $Person.name.full to user fullname.
                            'subContainingPII' => 'Kermit DaFrog',
                        ]
                    ],
                ],
            ],
            'Successful user auth with a user having no PII returned by the user_authenticator dependency' => [
                'authinfo' => [
                    'auth_success' => true,
                    'auth_user' => new lti_user(
                        id: '234'
                    ),
                ],
                'toolconfig' => (object) [
                    'id' => 123,
                    'lti_clientid' => '123456-abcd',
                    'lti_ltiversion' => '1.3.0',
                    'lti_sendname' => \core_ltix\constants::LTI_SETTING_ALWAYS,
                    'lti_sendemailaddr' => \core_ltix\constants::LTI_SETTING_ALWAYS,
                    'lti_initiatelogin' => 'https://tool.example.com/lti/initiatelogin',
                    'lti_organizationid' => 'https://platform.example.com',
                    'lti_launchcontainer' => \core_ltix\constants::LTI_LAUNCH_CONTAINER_EMBED_NO_BLOCKS,
                    'lti_redirectionuris' => "https://tool.example.com/lti/redirecturi\nhttps://tool.example.com/lti/redirecturi2",
                    'ltixservice_gradesynchronization' => 2,
                    'ltixservice_memberships' => 1,
                    'lti_customparameters' => "idnumber=\$Person.sourcedId\nuser#TIME#zone=\$Person.address.timezone\n".
                        "toollevelparam=test\nsubContainingPII=\$Person.name.full",
                ],
                'authrequestpayload' => [
                    'lti_message_hint' => (new lti_token([
                        'tool_registration_id' => 123, // Matches toolconfig.id.
                        \core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/custom' => [
                            'subContainingPII' => '$Person.name.full'
                        ]
                    ]))->to_jwt(privatekey: $keys['privatekey']['key'], kid: $keys['privatekey']['kid']),
                    'lti_deployment_id' => 123, // Matches toolconfig.id.
                    'scope' => 'openid',
                    'response_type' => 'id_token',
                    'client_id' => '123456-abcd', // Matches toolconfig.lti_clientid.
                    'redirect_uri' => 'https://tool.example.com/lti/redirecturi', // Must match one defined in toolconfig.
                    'login_hint' => '340', // Matches auth_user.id.
                    'response_mode' => 'form_post',
                    'prompt' => 'none',
                    'nonce' => 'TOOL-NONCE-abc-123', // Set by the tool. Opaque to the platform.
                    'state' => 'TOOL-STATE-1234', // Set by the tool. Opaque to the platform.
                ],
                'keys' => $keys,
                'expected' => [
                    'state' => 'TOOL-STATE-1234',
                    'jwt_claims' => [
                        'sub' => '234',
                        'nonce' => 'TOOL-NONCE-abc-123', // Matches auth request payload nonce.
                        \core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/custom' => [
                            // User substitution can't resolve without user sourcedata.
                            'subContainingPII' => '$Person.name.full',
                        ]
                    ]
                ],
            ],
            'Unsuccessful user auth returned by user_authenticator dependency' => [
                'authinfo' => [
                    'auth_success' => false,
                    'auth_user' => null,
                ],
                'toolconfig' => (object) [
                    'id' => 123,
                    'lti_clientid' => '123456-abcd',
                    'lti_ltiversion' => '1.3.0',
                    'lti_sendname' => \core_ltix\constants::LTI_SETTING_ALWAYS,
                    'lti_sendemailaddr' => \core_ltix\constants::LTI_SETTING_ALWAYS,
                    'lti_initiatelogin' => 'https://tool.example.com/lti/initiatelogin',
                    'lti_organizationid' => 'https://platform.example.com',
                    'lti_launchcontainer' => \core_ltix\constants::LTI_LAUNCH_CONTAINER_EMBED_NO_BLOCKS,
                    'lti_redirectionuris' => "https://tool.example.com/lti/redirecturi\nhttps://tool.example.com/lti/redirecturi2",
                    'ltixservice_gradesynchronization' => 2,
                    'ltixservice_memberships' => 1,
                    'lti_customparameters' => "idnumber=\$Person.sourcedId\nuser#TIME#zone=\$Person.address.timezone\n".
                        "toollevelparam=test\nsubContainingPII=\$Person.name.full",
                ],
                'authrequestpayload' => [
                    'lti_message_hint' => (new lti_token([
                        'tool_registration_id' => 123, // Matches toolconfig.id.
                        \core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/custom' => [
                            'subContainingPII' => '$Person.name.full'
                        ]
                    ]))->to_jwt(privatekey: $keys['privatekey']['key'], kid: $keys['privatekey']['kid']),
                    'lti_deployment_id' => 123, // Matches toolconfig.id.
                    'scope' => 'openid',
                    'response_type' => 'id_token',
                    'client_id' => '123456-abcd', // Matches toolconfig.lti_clientid.
                    'redirect_uri' => 'https://tool.example.com/lti/redirecturi', // Must match one defined in toolconfig.
                    'login_hint' => '340', // Matches auth_user.id.
                    'response_mode' => 'form_post',
                    'prompt' => 'none',
                    'nonce' => 'TOOL-NONCE-abc-123', // Set by the tool. Opaque to the platform.
                    'state' => 'TOOL-STATE-1234', // Set by the tool. Opaque to the platform.
                ],
                'keys' => $keys,
                'expected' => [
                    'auth_exception' => lti_exception::class,
                ],
            ],
            'OIDC auth request validation failure - invalid scope' => [
                'authinfo' => [
                    'auth_success' => true,
                    'auth_user' => new lti_user(
                        id: '234'
                    ),
                ],
                'toolconfig' => (object) [
                    'id' => 123,
                    'lti_clientid' => '123456-abcd',
                    'lti_ltiversion' => '1.3.0',
                    'lti_sendname' => \core_ltix\constants::LTI_SETTING_ALWAYS,
                    'lti_sendemailaddr' => \core_ltix\constants::LTI_SETTING_ALWAYS,
                    'lti_initiatelogin' => 'https://tool.example.com/lti/initiatelogin',
                    'lti_organizationid' => 'https://platform.example.com',
                    'lti_launchcontainer' => \core_ltix\constants::LTI_LAUNCH_CONTAINER_EMBED_NO_BLOCKS,
                    'lti_redirectionuris' => "https://tool.example.com/lti/redirecturi\nhttps://tool.example.com/lti/redirecturi2",
                    'ltixservice_gradesynchronization' => 2,
                    'ltixservice_memberships' => 1,
                    'lti_customparameters' => "idnumber=\$Person.sourcedId\nuser#TIME#zone=\$Person.address.timezone\n".
                        "toollevelparam=test\nsubContainingPII=\$Person.name.full",
                ],
                'authrequestpayload' => [
                    'lti_message_hint' => (new lti_token([
                        'tool_registration_id' => 123, // Matches toolconfig.id.
                        \core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/custom' => [
                            'subContainingPII' => '$Person.name.full'
                        ]
                    ]))->to_jwt(privatekey: $keys['privatekey']['key'], kid: $keys['privatekey']['kid']),
                    'lti_deployment_id' => 123, // Matches toolconfig.id.
                    'scope' => 'invalid', // Invalid scope.
                    'response_type' => 'id_token',
                    'client_id' => '123456-abcd', // Matches toolconfig.lti_clientid.
                    'redirect_uri' => 'https://tool.example.com/lti/redirecturi', // Must match one defined in toolconfig.
                    'login_hint' => '340', // Matches auth_user.id.
                    'response_mode' => 'form_post',
                    'prompt' => 'none',
                    'nonce' => 'TOOL-NONCE-abc-123', // Set by the tool. Opaque to the platform.
                    'state' => 'TOOL-STATE-1234', // Set by the tool. Opaque to the platform.
                ],
                'keys' => $keys,
                'expected' => [
                    'auth_exception' => lti_exception::class,
                ],
            ],
            'OIDC auth request validation failure - invalid response_type' => [
                'authinfo' => [
                    'auth_success' => true,
                    'auth_user' => new lti_user(
                        id: '234'
                    ),
                ],
                'toolconfig' => (object) [
                    'id' => 123,
                    'lti_clientid' => '123456-abcd',
                    'lti_ltiversion' => '1.3.0',
                    'lti_sendname' => \core_ltix\constants::LTI_SETTING_ALWAYS,
                    'lti_sendemailaddr' => \core_ltix\constants::LTI_SETTING_ALWAYS,
                    'lti_initiatelogin' => 'https://tool.example.com/lti/initiatelogin',
                    'lti_organizationid' => 'https://platform.example.com',
                    'lti_launchcontainer' => \core_ltix\constants::LTI_LAUNCH_CONTAINER_EMBED_NO_BLOCKS,
                    'lti_redirectionuris' => "https://tool.example.com/lti/redirecturi\nhttps://tool.example.com/lti/redirecturi2",
                    'ltixservice_gradesynchronization' => 2,
                    'ltixservice_memberships' => 1,
                    'lti_customparameters' => "idnumber=\$Person.sourcedId\nuser#TIME#zone=\$Person.address.timezone\n".
                        "toollevelparam=test\nsubContainingPII=\$Person.name.full",
                ],
                'authrequestpayload' => [
                    'lti_message_hint' => (new lti_token([
                        'tool_registration_id' => 123, // Matches toolconfig.id.
                        \core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/custom' => [
                            'subContainingPII' => '$Person.name.full'
                        ]
                    ]))->to_jwt(privatekey: $keys['privatekey']['key'], kid: $keys['privatekey']['kid']),
                    'lti_deployment_id' => 123, // Matches toolconfig.id.
                    'scope' => 'openid',
                    'response_type' => 'invalid', // Invalid response_type.
                    'client_id' => '123456-abcd', // Matches toolconfig.lti_clientid.
                    'redirect_uri' => 'https://tool.example.com/lti/redirecturi', // Must match one defined in toolconfig.
                    'login_hint' => '340', // Matches auth_user.id.
                    'response_mode' => 'form_post',
                    'prompt' => 'none',
                    'nonce' => 'TOOL-NONCE-abc-123', // Set by the tool. Opaque to the platform.
                    'state' => 'TOOL-STATE-1234', // Set by the tool. Opaque to the platform.
                ],
                'keys' => $keys,
                'expected' => [
                    'auth_exception' => lti_exception::class,
                ],
            ],
            'OIDC auth request validation failure - invalid response_mode' => [
                'authinfo' => [
                    'auth_success' => true,
                    'auth_user' => new lti_user(
                        id: '234'
                    ),
                ],
                'toolconfig' => (object) [
                    'id' => 123,
                    'lti_clientid' => '123456-abcd',
                    'lti_ltiversion' => '1.3.0',
                    'lti_sendname' => \core_ltix\constants::LTI_SETTING_ALWAYS,
                    'lti_sendemailaddr' => \core_ltix\constants::LTI_SETTING_ALWAYS,
                    'lti_initiatelogin' => 'https://tool.example.com/lti/initiatelogin',
                    'lti_organizationid' => 'https://platform.example.com',
                    'lti_launchcontainer' => \core_ltix\constants::LTI_LAUNCH_CONTAINER_EMBED_NO_BLOCKS,
                    'lti_redirectionuris' => "https://tool.example.com/lti/redirecturi\nhttps://tool.example.com/lti/redirecturi2",
                    'ltixservice_gradesynchronization' => 2,
                    'ltixservice_memberships' => 1,
                    'lti_customparameters' => "idnumber=\$Person.sourcedId\nuser#TIME#zone=\$Person.address.timezone\n".
                        "toollevelparam=test\nsubContainingPII=\$Person.name.full",
                ],
                'authrequestpayload' => [
                    'lti_message_hint' => (new lti_token([
                        'tool_registration_id' => 123, // Matches toolconfig.id.
                        \core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/custom' => [
                            'subContainingPII' => '$Person.name.full'
                        ]
                    ]))->to_jwt(privatekey: $keys['privatekey']['key'], kid: $keys['privatekey']['kid']),
                    'lti_deployment_id' => 123, // Matches toolconfig.id.
                    'scope' => 'openid',
                    'response_type' => 'id_token',
                    'client_id' => '123456-abcd', // Matches toolconfig.lti_clientid.
                    'redirect_uri' => 'https://tool.example.com/lti/redirecturi', // Must match one defined in toolconfig.
                    'login_hint' => '340', // Matches auth_user.id.
                    'response_mode' => 'invalid', // Invalid response_mode.
                    'prompt' => 'none',
                    'nonce' => 'TOOL-NONCE-abc-123', // Set by the tool. Opaque to the platform.
                    'state' => 'TOOL-STATE-1234', // Set by the tool. Opaque to the platform.
                ],
                'keys' => $keys,
                'expected' => [
                    'auth_exception' => lti_exception::class,
                ],
            ],
            'OIDC auth request validation failure - invalid prompt' => [
                'authinfo' => [
                    'auth_success' => true,
                    'auth_user' => new lti_user(
                        id: '234'
                    ),
                ],
                'toolconfig' => (object) [
                    'id' => 123,
                    'lti_clientid' => '123456-abcd',
                    'lti_ltiversion' => '1.3.0',
                    'lti_sendname' => \core_ltix\constants::LTI_SETTING_ALWAYS,
                    'lti_sendemailaddr' => \core_ltix\constants::LTI_SETTING_ALWAYS,
                    'lti_initiatelogin' => 'https://tool.example.com/lti/initiatelogin',
                    'lti_organizationid' => 'https://platform.example.com',
                    'lti_launchcontainer' => \core_ltix\constants::LTI_LAUNCH_CONTAINER_EMBED_NO_BLOCKS,
                    'lti_redirectionuris' => "https://tool.example.com/lti/redirecturi\nhttps://tool.example.com/lti/redirecturi2",
                    'ltixservice_gradesynchronization' => 2,
                    'ltixservice_memberships' => 1,
                    'lti_customparameters' => "idnumber=\$Person.sourcedId\nuser#TIME#zone=\$Person.address.timezone\n".
                        "toollevelparam=test\nsubContainingPII=\$Person.name.full",
                ],
                'authrequestpayload' => [
                    'lti_message_hint' => (new lti_token([
                        'tool_registration_id' => 123, // Matches toolconfig.id.
                        \core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/custom' => [
                            'subContainingPII' => '$Person.name.full'
                        ]
                    ]))->to_jwt(privatekey: $keys['privatekey']['key'], kid: $keys['privatekey']['kid']),
                    'lti_deployment_id' => 123, // Matches toolconfig.id.
                    'scope' => 'openid',
                    'response_type' => 'id_token',
                    'client_id' => '123456-abcd', // Matches toolconfig.lti_clientid.
                    'redirect_uri' => 'https://tool.example.com/lti/redirecturi', // Must match one defined in toolconfig.
                    'login_hint' => '340', // Matches auth_user.id.
                    'response_mode' => 'form_post',
                    'prompt' => 'invalid', // Invalid prompt.
                    'nonce' => 'TOOL-NONCE-abc-123', // Set by the tool. Opaque to the platform.
                    'state' => 'TOOL-STATE-1234', // Set by the tool. Opaque to the platform.
                ],
                'keys' => $keys,
                'expected' => [
                    'auth_exception' => lti_exception::class,
                ],
            ],
            'OIDC auth request validation failure - invalid nonce' => [
                'authinfo' => [
                    'auth_success' => true,
                    'auth_user' => new lti_user(
                        id: '234'
                    ),
                ],
                'toolconfig' => (object) [
                    'id' => 123,
                    'lti_clientid' => '123456-abcd',
                    'lti_ltiversion' => '1.3.0',
                    'lti_sendname' => \core_ltix\constants::LTI_SETTING_ALWAYS,
                    'lti_sendemailaddr' => \core_ltix\constants::LTI_SETTING_ALWAYS,
                    'lti_initiatelogin' => 'https://tool.example.com/lti/initiatelogin',
                    'lti_organizationid' => 'https://platform.example.com',
                    'lti_launchcontainer' => \core_ltix\constants::LTI_LAUNCH_CONTAINER_EMBED_NO_BLOCKS,
                    'lti_redirectionuris' => "https://tool.example.com/lti/redirecturi\nhttps://tool.example.com/lti/redirecturi2",
                    'ltixservice_gradesynchronization' => 2,
                    'ltixservice_memberships' => 1,
                    'lti_customparameters' => "idnumber=\$Person.sourcedId\nuser#TIME#zone=\$Person.address.timezone\n".
                        "toollevelparam=test\nsubContainingPII=\$Person.name.full",
                ],
                'authrequestpayload' => [
                    'lti_message_hint' => (new lti_token([
                        'tool_registration_id' => 123, // Matches toolconfig.id.
                        \core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/custom' => [
                            'subContainingPII' => '$Person.name.full'
                        ]
                    ]))->to_jwt(privatekey: $keys['privatekey']['key'], kid: $keys['privatekey']['kid']),
                    'lti_deployment_id' => 123, // Matches toolconfig.id.
                    'scope' => 'openid',
                    'response_type' => 'id_token',
                    'client_id' => '123456-abcd', // Matches toolconfig.lti_clientid.
                    'redirect_uri' => 'https://tool.example.com/lti/redirecturi', // Must match one defined in toolconfig.
                    'login_hint' => '340', // Matches auth_user.id.
                    'response_mode' => 'form_post',
                    'prompt' => 'none',
                    // Note: Invalid (missing) nonce value.
                    'state' => 'TOOL-STATE-1234', // Set by the tool. Opaque to the platform.
                ],
                'keys' => $keys,
                'expected' => [
                    'auth_exception' => lti_exception::class,
                ],
            ],
            'OIDC auth request validation failure - invalid client_id' => [
                'authinfo' => [
                    'auth_success' => true,
                    'auth_user' => new lti_user(
                        id: '234'
                    ),
                ],
                'toolconfig' => (object) [
                    'id' => 123,
                    'lti_clientid' => '123456-abcd',
                    'lti_ltiversion' => '1.3.0',
                    'lti_sendname' => \core_ltix\constants::LTI_SETTING_ALWAYS,
                    'lti_sendemailaddr' => \core_ltix\constants::LTI_SETTING_ALWAYS,
                    'lti_initiatelogin' => 'https://tool.example.com/lti/initiatelogin',
                    'lti_organizationid' => 'https://platform.example.com',
                    'lti_launchcontainer' => \core_ltix\constants::LTI_LAUNCH_CONTAINER_EMBED_NO_BLOCKS,
                    'lti_redirectionuris' => "https://tool.example.com/lti/redirecturi\nhttps://tool.example.com/lti/redirecturi2",
                    'ltixservice_gradesynchronization' => 2,
                    'ltixservice_memberships' => 1,
                    'lti_customparameters' => "idnumber=\$Person.sourcedId\nuser#TIME#zone=\$Person.address.timezone\n".
                        "toollevelparam=test\nsubContainingPII=\$Person.name.full",
                ],
                'authrequestpayload' => [
                    'lti_message_hint' => (new lti_token([
                        'tool_registration_id' => 123, // Matches toolconfig.id.
                        \core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/custom' => [
                            'subContainingPII' => '$Person.name.full'
                        ]
                    ]))->to_jwt(privatekey: $keys['privatekey']['key'], kid: $keys['privatekey']['kid']),
                    'lti_deployment_id' => 123, // Matches toolconfig.id.
                    'scope' => 'openid',
                    'response_type' => 'id_token',
                    'client_id' => 'fbfb-123d', // Invalid client_id, not matching the toolconfig.lti_clientid.
                    'redirect_uri' => 'https://tool.example.com/lti/redirecturi', // Must match one defined in toolconfig.
                    'login_hint' => '340', // Matches auth_user.id.
                    'response_mode' => 'form_post',
                    'prompt' => 'none',
                    'nonce' => 'TOOL-NONCE-abc-123', // Set by the tool. Opaque to the platform.
                    'state' => 'TOOL-STATE-1234', // Set by the tool. Opaque to the platform.
                ],
                'keys' => $keys,
                'expected' => [
                    'auth_exception' => lti_exception::class,
                ],
            ],
            'OIDC auth request validation failure - invalid redirect_uri' => [
                'authinfo' => [
                    'auth_success' => true,
                    'auth_user' => new lti_user(
                        id: '234'
                    ),
                ],
                'toolconfig' => (object) [
                    'id' => 123,
                    'lti_clientid' => '123456-abcd',
                    'lti_ltiversion' => '1.3.0',
                    'lti_sendname' => \core_ltix\constants::LTI_SETTING_ALWAYS,
                    'lti_sendemailaddr' => \core_ltix\constants::LTI_SETTING_ALWAYS,
                    'lti_initiatelogin' => 'https://tool.example.com/lti/initiatelogin',
                    'lti_organizationid' => 'https://platform.example.com',
                    'lti_launchcontainer' => \core_ltix\constants::LTI_LAUNCH_CONTAINER_EMBED_NO_BLOCKS,
                    'lti_redirectionuris' => "https://tool.example.com/lti/redirecturi\nhttps://tool.example.com/lti/redirecturi2",
                    'ltixservice_gradesynchronization' => 2,
                    'ltixservice_memberships' => 1,
                    'lti_customparameters' => "idnumber=\$Person.sourcedId\nuser#TIME#zone=\$Person.address.timezone\n".
                        "toollevelparam=test\nsubContainingPII=\$Person.name.full",
                ],
                'authrequestpayload' => [
                    'lti_message_hint' => (new lti_token([
                        'tool_registration_id' => 123, // Matches toolconfig.id.
                        \core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/custom' => [
                            'subContainingPII' => '$Person.name.full'
                        ]
                    ]))->to_jwt(privatekey: $keys['privatekey']['key'], kid: $keys['privatekey']['kid']),
                    'lti_deployment_id' => 123, // Matches toolconfig.id.
                    'scope' => 'openid',
                    'response_type' => 'id_token',
                    'client_id' => '123456-abcd', // Matches toolconfig.lti_clientid.
                    'redirect_uri' => 'https://tool.example.com/lti/invalidredirectURI', // Invalid redirect URI (not registered).
                    'login_hint' => '340', // Matches auth_user.id.
                    'response_mode' => 'form_post',
                    'prompt' => 'none',
                    'nonce' => 'TOOL-NONCE-abc-123', // Set by the tool. Opaque to the platform.
                    'state' => 'TOOL-STATE-1234', // Set by the tool. Opaque to the platform.
                ],
                'keys' => $keys,
                'expected' => [
                    'auth_exception' => lti_exception::class,
                    'auth_exception_contains_text' => 'Invalid redirect_uri',
                ],
            ],
            'OIDC auth request validation failure - JWT decoding failure' => [
                'authinfo' => [
                    'auth_success' => true,
                    'auth_user' => new lti_user(
                        id: '234'
                    ),
                ],
                'toolconfig' => (object) [
                    'id' => 123,
                    'lti_clientid' => '123456-abcd',
                    'lti_ltiversion' => '1.3.0',
                    'lti_sendname' => \core_ltix\constants::LTI_SETTING_ALWAYS,
                    'lti_sendemailaddr' => \core_ltix\constants::LTI_SETTING_ALWAYS,
                    'lti_initiatelogin' => 'https://tool.example.com/lti/initiatelogin',
                    'lti_organizationid' => 'https://platform.example.com',
                    'lti_launchcontainer' => \core_ltix\constants::LTI_LAUNCH_CONTAINER_EMBED_NO_BLOCKS,
                    'lti_redirectionuris' => "https://tool.example.com/lti/redirecturi\nhttps://tool.example.com/lti/redirecturi2",
                    'ltixservice_gradesynchronization' => 2,
                    'ltixservice_memberships' => 1,
                    'lti_customparameters' => "idnumber=\$Person.sourcedId\nuser#TIME#zone=\$Person.address.timezone\n".
                        "toollevelparam=test\nsubContainingPII=\$Person.name.full",
                ],
                'authrequestpayload' => [
                    'lti_message_hint' => (new lti_token([
                        'tool_registration_id' => 123, // Matches toolconfig.id.
                        \core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/custom' => [
                            'subContainingPII' => '$Person.name.full'
                        ]
                        ]))->to_jwt(
                            privatekey: $keys['privatekey']['key'],
                            kid: 0 // kid that does not exist in the JWKS, ensuring signature validation fails.
                    ),
                    'lti_deployment_id' => 123, // Matches toolconfig.id.
                    'scope' => 'openid',
                    'response_type' => 'id_token',
                    'client_id' => '123456-abcd', // Matches toolconfig.lti_clientid.
                    'redirect_uri' => 'https://tool.example.com/lti/redirecturi', // Must match one defined in toolconfig.
                    'login_hint' => '340', // Matches auth_user.id.
                    'response_mode' => 'form_post',
                    'prompt' => 'none',
                    'nonce' => 'TOOL-NONCE-abc-123', // Set by the tool. Opaque to the platform.
                    'state' => 'TOOL-STATE-1234', // Set by the tool. Opaque to the platform.
                ],
                'keys' => $keys,
                'expected' => [
                    'auth_exception' => lti_exception::class,
                    'auth_exception_contains_text' => 'Invalid lti_message_hint',
                ],
            ],
        ];
    }
}
