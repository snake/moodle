<?php

namespace core_ltix\local\lticore\message\substitition;

use core_ltix\local\lticore\exception\lti_exception;
use core_ltix\local\lticore\facades\service\launch_service_facade_interface;
use core_ltix\local\lticore\facades\service\resource_link_launch_service_facade;
use core_ltix\local\lticore\facades\service\substitution_service_facade;
use core_ltix\local\lticore\lti_version;
use core_ltix\local\lticore\message\payload\custom\custom_param_parser;
use core_ltix\local\lticore\message\substitition\policy\substitute_all_policy;
use core_ltix\local\lticore\message\substitition\resolver\calculated_course_variable_resolver;
use core_ltix\local\lticore\message\substitition\resolver\calculated_user_variable_resolver;
use core_ltix\local\lticore\message\substitition\resolver\composite_chain_resolver;
use core_ltix\local\lticore\message\substitition\resolver\map_variable_resolver;
use core_ltix\local\lticore\message\substitition\resolver\object_property_resolver;
use core_ltix\local\lticore\message\substitition\resolver\service_variable_resolver;
use core_ltix\local\lticore\models\resource_link;
use core_ltix\local\lticore\token\lti_token;
use core_ltix\local\ltiopenid\lti_user;

/**
 * Simple factory for resolving substitutor instances.
 *
 */
class variable_substitutor_factory {


    // What do we ACTUALLY get a substitutor for?
    // - tool messages (tool, message type, version)
    //   - does message type matter? not really.
    // What data do we need to
    public function __construct(protected substitution_service_facade $servicefacade) {
    }

    public function get_for_tool(
        \stdClass $toolconfig,
    ): variable_substitutor {

        $version = lti_version::from($toolconfig->lti_ltiversion);
        switch ($version) {
            case lti_version::LTI_VERSION_1:
            case lti_version::LTI_VERSION_1P3:
                $map = \core_ltix\helper::get_capabilities();
                return new variable_substitutor(
                    // LTI 1px substitutors unconditionally resolve substitution.
                    new substitute_all_policy(),
                    new composite_chain_resolver([
                        new map_variable_resolver($map),
                        new object_property_resolver($map),
                        new calculated_course_variable_resolver(),
                        new calculated_user_variable_resolver(),
                        new service_variable_resolver($this->servicefacade),
                    ]),
                );
            case lti_version::LTI_VERSION_2:
                // LTI 2p0 substitutors are subject to a capability-based resolving policy.
                // Substitution is controlled by tool capabilities.
                // TODO: implement 2p0.
                break;
            default:
                throw new lti_exception("Unable to resolve variable_substitor instance for LTI version: $version. Version not "
                . "mapped");
        }
    }

    /**
     * Gets an instance handling custom parameter substitution at init login time, when the user has not yet auth'd.
     *
     * @return v1px_variable_substitutor
     */
    public function get_substitutor_at_init_login(
        array $sourcedata,
        \core\context $context,
        launch_service_facade_interface $launchservicefacade
    ): v1px_variable_substitutor {

        // Note: User is deliberately not provided, because in terms of OIDC third party login, they haven't yet auth'd.
        // User-based substitution variables must be resolved during auth request processing.
        return new v1px_variable_substitutor(
            $sourcedata,
            $context,
            $launchservicefacade,
        );
    }

    /**
     * Gets a custom param substitutor, for LTI 1p3, for use at auth time.
     *
     * @param \stdClass $toolconfig
     * @param lti_token $launchtoken
     * @param lti_user $ltiuser
     * @return v1px_variable_substitutor
     * @throws lti_exception if a substitutor instance cannot be created.
     */
    public function get_substitutor_from_auth_request(
        \stdClass $toolconfig,
        lti_token $launchtoken,
        lti_user $ltiuser
    ): v1px_variable_substitutor {

        // TODO: below 'LtiResourceLinkRequest' should be replaced with a const.
        if ($launchtoken->get_claim(\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/message_type') === 'LtiResourceLinkRequest') {
            global $USER;
            $user = $USER->id == $ltiuser->id ? $USER : \core_user::get_user($ltiuser->id);

            $context = \core\context\course::instance(
                $launchtoken->get_claim(\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/context')['id']
            );

            // Note: User can now be provided, because in terms of OIDC third party login, the user has now auth'd.
            return new v1px_variable_substitutor(
                [],
                $context,
                new resource_link_launch_service_facade(
                    toolconfig: $toolconfig,
                    context: $context,
                    userid: $ltiuser->id,
                    resourcelink: resource_link::get_record(
                        ['id' => $launchtoken->get_claim(\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/resource_link')['id']]
                    )
                ),
                $user,
            );


//            // If the auth request is for a ResourceLink Launch, create the custom parameter parser using the resource link.
//            return new custom_param_parser(
//                \core_ltix\helper::get_capabilities(),
//                new resource_link_launch_service_facade(
//                    toolconfig: $toolconfig,
//                    context: $context,
//                    userid: $ltiuser->id,
//                    resourcelink: resource_link::get_record(
//                        ['id' => $launchtoken->get_claim(\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/resource_link')['id']]
//                    )
//                ),
//                context: $context,
//                user: $user
//            );
        }
        throw new lti_exception('Could not create custom_param_parser instance for message type: '.
            $launchtoken->get_claim(\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/message_type'));
    }

}
