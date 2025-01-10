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

namespace core_ltix\local\lticore;

use core_ltix\helper;
use core_ltix\local\lticore\facades\service\launch_service_facade_interface;
use core_ltix\local\lticore\facades\service\resource_link_launch_service_facade;
use core_ltix\local\lticore\message\lti_message_base;
use core_ltix\local\lticore\message\payload\custom\custom_param_parser;
use core_ltix\local\lticore\message\payload\lti_1p3_payload_formatter;
use core_ltix\local\lticore\models\resource_link;

/**
 * Class encapsulating the creation of the launch request for an LtiResourceLinkRequest message type.
 *
 * @package    core_ltix
 * @copyright  2024 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lti_resource_link_launch_request_builder extends lti_launch_request_builder {

    /**
     * Convenience static.
     *
     * @return self a new instance of the class.
     */
    public static function new(): self {
        return new self();
    }

    /**
     * Build a launch request, the first request to send to the tool's initiate login endpoint.
     *
     * Note: For the value of $tool MUST pass in the result of \core_ltix\helper::get_type_type_config
     *
     * @param \stdClass $toolconfig
     * @param resource_link $resourcelink
     * @param string $issuer
     * @param \stdClass $user
     * @param array $extraclaims
     * @return lti_message_base
     */
    public function build_resource_link_launch_request(
        \stdClass $toolconfig,
        resource_link $resourcelink,
        launch_service_facade_interface $servicefacade,
        string $issuer,
        \stdClass $user,
        array $extraclaims = []
    ): lti_message_base {
        // Create the partially complete set of claims. This will be finalised with user claims during the auth request step.
        $claims = $this->get_all_claims_for_launch($toolconfig, $resourcelink, $user, $extraclaims, $servicefacade);

        $targetlinkuri = $servicefacade->get_target_link_uri(); // Allows services to override the target.


        // TODO: do we need to allow the ltixsource plugins' 'before_launch' plugin callback to be called here?
        //  That would permit augmenting any launch params prior to signing.

        return parent::build_launch_request(
            toolconfig: $toolconfig,
            messagetype: 'LtiResourceLinkRequest', // TODO const?
            issuer: $issuer,
            targetlinkuri: $targetlinkuri,
            loginhint: strval($user->id),
            roles: $this->get_roles_for_launch($user->id, $resourcelink),
            extraclaims: $claims,
        );
    }

    protected function get_roles_for_launch(int $userid, resource_link $resourcelink): array {

        // Note: This uses 1p3 vocab so doesn't need translation.
        return helper::get_lti_message_roles($userid, \core\context::instance_by_id($resourcelink->get('contextid')));
    }

    protected function get_all_claims_for_launch(
        \stdClass $toolconfig,
        resource_link $resourcelink,
        \stdClass $user,
        array $extraclaims,
        resource_link_launch_service_facade $servicefacade,
    ): array {

        // Create the payload data common to any RLL.
        $unformattedpayloaddata = [
            'context' => $this->get_unformatted_context_data($resourcelink),
            'resourcelink' => $this->get_unformatted_resource_link_data($resourcelink),
            'toolplatform' => $this->get_unformatted_tool_platform_data($resourcelink, $toolconfig),
            'launchpresentation' => $this->get_unformatted_launch_presentation_data($resourcelink, $toolconfig),
            'lis' => $this->get_unformatted_lis_data($resourcelink, $user),
        ];
        $unformattedpayloaddata = array_merge(...array_values($unformattedpayloaddata)); // Flatten the above.


        // Add custom param data configured by the tool and link - do NOT substitute yet.
        $linkunformattedpayloaddata = $this->get_unformatted_custom_data($resourcelink, $toolconfig);
        $unformattedpayloaddata = array_merge($linkunformattedpayloaddata, $unformattedpayloaddata);

        // Allow services to add claims, again using unformatted payload data because, historically, that's what services speak.
        // Note the ordering. Services add their custom claims to the unformatted data as 'custom_%SERVICECLAIM%', so care
        // must be taken to ensure that tool-level or link-level custom params, which are represented as 'custom_%CUSTOMPARAMNAME%',
        // can't override the service claims.
        $serviceunformattedpayloaddata = $this->get_unformatted_service_custom_data($servicefacade);
        $unformattedpayloaddata = array_merge($unformattedpayloaddata, $serviceunformattedpayloaddata);

        // Perform substitution for custom params.
        // Note: this won't perform subsitution for variables referencing any of the user claims, since user claims aren't
        // yet present in the payload. Substitution needs to be re-run against the unformatted user data at auth time.
        // TODO: get_user call below could be tidied up.
        $unformattedpayloaddata = $this->resolve_substitution($unformattedpayloaddata, $servicefacade, $user);

        // Format the payload for 1p3.
        $formattedclaims = $this->format_payload($unformattedpayloaddata);

        // Formatted claims which don't originate in the generic data (i.e. are 1.3+RLL specific).
        // Other LTI 1.3 formatted claims will be added by the parent class (e.g. deployment id).
        // the additional formatted claims must take precedence over any claims originating above.
        $additionalformattedclaims = [
            \core_ltix\constants::LTI_JWT_CLAIM_PREFIX."/claim/target_link_uri" => $resourcelink->get('url'),
        ];

        $formattedclaims = array_merge($formattedclaims, $additionalformattedclaims);


        // Param ordering is important here. Any extra claims MUST take precedence over the internally managed claims.
        // This is critical to supporting legacy launches, which use a legacy (non-uuid) resource link id, that must be passed in.
        return array_merge($formattedclaims, $extraclaims);
    }

    protected function format_payload(array $unformattedpayloaddata): array {
        // Roughly, the transformation logic is as follows:
        // - get the jwt claim mapping, which includes the mapping for claims handled by services.
        // - process the unformatted data in the context of the above mapping, converting to claims of the names identified in the map.
        $payloadformatter = new lti_1p3_payload_formatter(\core_ltix\oauth_helper::get_jwt_claim_mapping()); // TODO inject?
        return $payloadformatter->format($unformattedpayloaddata);
    }

    protected function resolve_substitution(
        array $payloaddata,
        resource_link_launch_service_facade $servicefacade,
        \stdClass $user,
    ): array {

        $parser = new custom_param_parser(helper::get_capabilities(), $servicefacade, $user);

        // Substitution is only performed in custom params.
        foreach ($payloaddata as $key => $value) {
            if (str_starts_with($key, 'custom_')) {
                $payloaddata[$key] = $parser->parse($value, $payloaddata);
            }
        }

        return $payloaddata;
    }

    protected function get_unformatted_service_custom_data(resource_link_launch_service_facade $servicefacade): array {

        $servicecustomdata = [];
        foreach ($servicefacade->get_launch_parameters() as $param => $val) {
            $servicecustomdata['custom_'.$param] = $val;
        }
        return $servicecustomdata;
    }

    protected function get_unformatted_custom_data(
        resource_link $resourcelink,
        \stdClass $toolconfig,
    ): array {

        $toolcustomstr = !empty($toolconfig->lti_customparameters) ? $toolconfig->lti_customparameters : '';
        $linkcustomstr = !empty($resourcelink->get('customparams')) ? $resourcelink->get('customparams') : '';
        $parsedtoolcustom = [];
        $parsedlinkcustom = [];
        if ($toolcustomstr) {
            $toolcustom = helper::split_parameters($toolcustomstr);
            foreach ($toolcustom as $key => $val) {
                $key2 = helper::map_keyname($key);
                $parsedtoolcustom['custom_'.$key2] = $val;
                // TODO: if we move this into a dependency, make sure it relies on ltiversion and implemented the below check.
                //if (($islti2 || ($tool->ltiversion === LTI_VERSION_1P3)) && ($key != $key2)) {
                    $parsedtoolcustom['custom_'.$key] = $val;
                //}
            }
        }

        // The link custom params override the tool-level custom params (specific trumps generic).
        if ($linkcustomstr) {
            $linkcustom = helper::split_parameters($linkcustomstr);
            foreach ($linkcustom as $key => $val) {
                $key2 = helper::map_keyname($key);
                $parsedlinkcustom['custom_'.$key2] = $val;
                // TODO: if we move this into a dependency, make sure it relies on ltiversion and implemented the below check.
                //if (($islti2 || ($tool->ltiversion === LTI_VERSION_1P3)) && ($key != $key2)) {
                $parsedlinkcustom['custom_'.$key] = $val;
                //}
            }
        }

        return array_merge($parsedtoolcustom, $parsedlinkcustom);
    }

    protected function get_unformatted_resource_link_data(resource_link $resourcelink): array {
        // Description is optional and may be null, in which case it'll be omitted.
        $description = $resourcelink->get('text');
        if (!is_null($description)) {
            $descriptionformat = $resourcelink->get('textformat');
            $description = format_text($description, $descriptionformat);
        }

        return [
            'resource_link_id' => $resourcelink->get('uuid'),
            'resource_link_title' => $resourcelink->get('title'),
            ...(!is_null($description) ? ['resource_link_description' => $description] : []),
        ];
    }

    protected function get_unformatted_lis_data(resource_link $resourcelink, \stdClass $user): array {

        // Some lis properties only apply when in course-related contexts.
        $contextid = $resourcelink->get('contextid');
        $context = \core\context::instance_by_id($contextid);
        if (($coursecontext = $context->get_course_context(false)) !== false) {
            $course = get_course($coursecontext->instanceid);
            $coursesectionsourcedid = $course->idnumber;
        }

        // TODO: user id number can be used at this point in time, however, it might make MORE sense
        //  to delay setting it until auth time, when the lti user is ascertained properly..
        //  In fact, it's probably sensible to delay payload generation for any user-centric payload items until after auth time.
        //
        return [
            'lis_person_sourcedid' => $user->idnumber,
            ...(isset($coursesectionsourcedid) ? ['lis_course_section_sourcedid' => $coursesectionsourcedid] : []),
        ];
    }

    protected function get_unformatted_launch_presentation_data(resource_link $resourcelink, \stdClass $toolconfig): array {
        // Return URL is only set for course-context-related launches presently,
        // given the return endpoint is a legacy endpoint and only works in that situation.
        // The 'instanceid' is deliberately omitted from the return url params, since return.php expects that to match a legacy
        // 'lti' record (being a legacy endpoint). It's not a required part of the return flow, however.
        $contextid = $resourcelink->get('contextid');
        $context = \core\context::instance_by_id($contextid);

        $launchcontainer = helper::get_launch_container(
            (object) ['launchcontainer' => $resourcelink->get('launchcontainer')],
            ['launchcontainer' => $toolconfig->lti_launchcontainer] // Coerce into expected get_type_config() format object.
        );
        $target = '';
        switch($launchcontainer) {
            case \core_ltix\constants::LTI_LAUNCH_CONTAINER_EMBED:
            case \core_ltix\constants::LTI_LAUNCH_CONTAINER_EMBED_NO_BLOCKS:
                $target = 'iframe';
                break;
            case \core_ltix\constants::LTI_LAUNCH_CONTAINER_REPLACE_MOODLE_WINDOW:
                $target = 'frame';
                break;
            case \core_ltix\constants::LTI_LAUNCH_CONTAINER_WINDOW:
                $target = 'window';
                break;
        }

        if (($coursecontext = $context->get_course_context(false)) !== false) {
            $course = get_course($coursecontext->instanceid);

            // Add the return URL. We send the launch container along to help us avoid frames-within-frames when the user returns.
            $returnurlparams = [
                'course' => $course->id,
                'launch_container' => $launchcontainer,
                'sesskey' => sesskey()
            ];
            $url = new \moodle_url('/ltix/return.php', $returnurlparams);
            $returnurl = $url->out(false);

            if (isset($toolconfig->forcessl) && ($toolconfig->forcessl == '1')) {
                $returnurl = helper::ensure_url_is_https($returnurl);
            }
        }

        return [
            'launch_presentation_locale' => current_language(),
            'launch_presentation_document_target' => $target,
            ...(isset($returnurl) ? ['launch_presentation_return_url' => $returnurl] : [])
        ];
    }

    protected function get_unformatted_tool_platform_data(resource_link $resourcelink, \stdClass $toolconfig): array {
        // Name resolution falls back to mod_lti CFG vars, per legacy behaviour.
        // TODO remove the mod_lti_institution_name fallback code in a future MDL for Moodle 5.4.
        global $CFG;
        if (!empty($CFG->ltix_institution_name)) {
            $name = trim(html_to_text($CFG->ltix_institution_name, 0));
        } else if (!empty($CFG->mod_lti_institution_name)) {
            debugging('mod_lti_institution_name is deprecated. Please use ltix_institution_name instead.', DEBUG_DEVELOPER);
            $name = trim(html_to_text($CFG->mod_lti_institution_name, 0));
        } else {
            $name = get_site()->shortname;
        }

        return [
            'tool_consumer_info_product_family_code' => 'moodle',
            'tool_consumer_info_version' => strval($CFG->version),
            'tool_consumer_instance_guid' => \core_ltix\helper::get_organizationid((array)$toolconfig), // TODO this expects get_type_config format..
            'tool_consumer_instance_name' => $name,
            'tool_consumer_instance_description' => trim(html_to_text(get_site()->fullname, 0)),
        ];
    }

    protected function get_unformatted_context_data(resource_link $resourcelink): ?array {
        $contextid = $resourcelink->get('contextid');
        $context = \core\context::instance_by_id($contextid);

        if (($coursecontext = $context->get_course_context(false)) === false) {
            return null;
        }

        $course = get_course($coursecontext->instanceid);
        $contexttype = $course->format == 'site' ? 'Group'
            : 'CourseSection';

        return [
            'context_id' => $course->id,
            'context_label' => $context->get_context_name(),
            'context_title' => $context->get_context_name(),
            'context_type' => $contexttype
        ];
    }
}
