<?php

namespace core_ltix\local\lticore\message\payload\custom\factory;

use core_ltix\local\lticore\facades\service\resource_link_launch_service_facade;
use core_ltix\local\lticore\message\payload\custom\custom_param_parser;
use core_ltix\local\lticore\models\resource_link;
use core_ltix\local\lticore\token\lti_token;
use core_ltix\local\ltiopenid\lti_user;

/**
 * Simple factory for resolving custom_param_parser instances.
 *
 */
class custom_param_parser_factory {
    /**
     * Gets a custom param parser for 1.3, based at auth time.
     *
     * @param \stdClass $toolconfig
     * @param lti_token $launchtoken
     * @param lti_user $ltiuser
     * @return custom_param_parser
     */
    public function get_parser_from_auth_request(\stdClass $toolconfig, lti_token $launchtoken, lti_user $ltiuser): custom_param_parser {

        // TODO: below 'LtiResourceLinkRequest' should be replaced with a const.
        if ($launchtoken->get_claim(\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/message_type') === 'LtiResourceLinkRequest') {
            // If the auth request is for a ResourceLink Launch, create the custom parameter parser using the resource link.
            return new custom_param_parser(
                \core_ltix\helper::get_capabilities(),
                new resource_link_launch_service_facade(
                    toolconfig: $toolconfig,
                    context: \core\context\course::instance(
                        $launchtoken->get_claim(\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/context')['id']
                    ),
                    userid: $ltiuser->id,
                    resourcelink: resource_link::get_by_uuid(
                        $launchtoken->get_claim(\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/resource_link')['id']
                    )
                ),
                user: \core_user::get_user($ltiuser->id) // TODO: get_user hits the DB every time.
            );
        }
    }

}
