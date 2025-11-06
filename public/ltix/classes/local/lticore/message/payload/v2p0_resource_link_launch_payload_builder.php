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

namespace core_ltix\local\lticore\message\payload;

use core_ltix\constants;
use core_ltix\helper;
use core_ltix\local\lticore\message\payload\custom\custom_param_parser;
use core_ltix\local\lticore\message\payload\custom\custom_param_parser_interface;
use core_ltix\local\lticore\message\payload\custom\v2p0_parameter_substitutor;
use core_ltix\local\lticore\models\resource_link;

/**
 * Generates payload data for an LTI 2p0 resource link launch.
 *
 * @package    core_ltix
 * @copyright  2025 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class v2p0_resource_link_launch_payload_builder {

    // TODO: Support and test the various minor version subtleties here, such as:
    // - DONE: LTI1p3 AND LTI2 includes both the to_lowercase() version of custom param keys, as well as the non-normalized version - in helper::split_custom_parameters
    // - DONE: LTI2 adds lti_tool_settings as part of helper::build_custom_parameters()
    // - DONE: LTI2 checks capabilities, via helper::build_request_lti2()
    //   - This SHOULD be handled already by the request builder parent, confirm in an integration test.
    // - DONE: LTI2 unconditionally includes the following in helper::lti_build_request():
    //   - DONE: lis_result_sourcedid
    //   - DONE: lis_outcome_service_url
    //   - DONE: lis_person_name_given (will be done at auth time in 1p3).
    //   - DONE: lis_person_name_family (will be done at auth time in 1p3).
    //   - DONE: lis_person_name_full (will be done at auth time in 1p3).
    //   - DONE: ext_user_username (will be done at auth time in 1p3).
    //   - DONE: lis_person_contact_email_primary (will be done at auth time in 1p3).
    // - All versions include:
    //   - DONE: lis_course_section_sourcedid if it's a course context launch
    //   - DONE: lis_person_sourcedid (will be done at auth time in 1p3).
    // - DONE: Only LTI1p1 and LTI1p3 include launch parameters includes via service::get_launch_parameters(). LTI2 does NOT.
    //    See helper::get_launch_data().
    // DONE: LTi 2p0 user data included here.

    public function __construct(
        protected \stdClass $toolconfig,
        protected resource_link $resourcelink,
        protected \stdClass $user,
        protected v2p0_parameter_substitutor $paramsubstitutor,
    ) {
    }

    public function get_params(): array {
        // Create the payload data common to any RLL.
        $unformattedpayloaddata = [
            'context' => $this->get_context_params(),
            'toolplatform' => $this->get_tool_platform_params(),
            'launchpresentation' => $this->get_launch_presentation_params(),
            'lis' => $this->get_lis_params(),
            'user' => $this->get_user_params(),
            'extension' => $this->get_extension_params(),
        ];
        $unformattedpayloaddata = array_merge(...array_values($unformattedpayloaddata)); // Flatten the above.

        // Add custom param data configured by the tool and link - do NOT substitute yet.
        $linkunformattedpayloaddata = $this->get_custom_params();
        $unformattedpayloaddata = array_merge($linkunformattedpayloaddata, $unformattedpayloaddata);

        // TODO: we need to allow the ltixsource plugins' 'before_launch' plugin callback to be called here?
        //  That would permit augmenting any launch params prior to signing.

        // Perform substitution/variable expansion for custom params.
        return $this->resolve_substitution($unformattedpayloaddata);
    }

    /**
     * Get any extension params.
     *
     * @return array
     */
    protected function get_extension_params(): array {
        return [
            'ext_user_username' => $this->user->username,
            'ext_lms' => 'moodle-2',
        ];
    }

    // TODO: lti 2p0 specific, in that it does include the uppercase custom param keys, like in 1p3, but also includes tool settings and parameters.
    protected function get_custom_params(): array {
        $allcustom = [];

        $toolcustomstr = !empty($this->toolconfig->lti_customparameters) ? $this->toolconfig->lti_customparameters : '';
        if ($toolcustomstr) {
            $toolcustom = helper::split_parameters($toolcustomstr);
            $allcustom['toolcustom'] = $this->prefix_custom_params($toolcustom);
        }

        $linkcustomstr = !empty($this->resourcelink->get('customparams')) ? $this->resourcelink->get('customparams') : '';
        if ($linkcustomstr) {
            $linkcustom = helper::split_parameters($linkcustomstr);
            $allcustom['linkcustom'] = $this->prefix_custom_params($linkcustom);
        }

        // Set any $toolconfig->parameter values as custom params.
        if (!empty($this->toolconfig->parameter)) {
            $parametercustom = helper::split_parameters($this->toolconfig->parameter);
            $allcustom['parametercustom'] = $this->prefix_custom_params($parametercustom);
        }

        // Set any tool system settings values as custom params.
        $systemsettings = \core_ltix\helper::get_tool_settings($this->toolconfig->toolproxyid);
        $allcustom['systemsettingscustom'] = $this->prefix_custom_params($systemsettings);

        // Set any tool context settings values as custom params.
        $linkcontext = \core\context::instance_by_id($this->resourcelink->get('contextid'));
        $coursecontext = $linkcontext->get_course_context(false);
        $courseid = null;
        if ($coursecontext) {
            $courseid = $coursecontext->instanceid;
            $contextsettings = \core_ltix\helper::get_tool_settings($this->toolconfig->toolproxyid, $courseid);
            $allcustom['contextsettingscustom'] = $this->prefix_custom_params($contextsettings);
        }

        // Set any tool link settings values as custom params.
        $linksettings = \core_ltix\helper::get_tool_settings(
            $this->toolconfig->toolproxyid,
            $courseid,
            $this->resourcelink->get('id')
        );
        $allcustom['linksettingscustom'] = $this->prefix_custom_params($linksettings);

        // The link-level custom params override the tool-level custom params (specific trumps generic), which in turn are trumped
        // by tool parameter and settings-derived custom params.
        return array_merge(...array_values($allcustom));
    }

    /**
     * Helper to iterate over an array of [customname => customvalue] params, and return the final, prefixed parameter array.
     *
     * @return array the prefixed param array.
     */
    protected function prefix_custom_params(array $customparams): array {
        $prefixedparams = [];
        foreach ($customparams as $key => $val) {
            $key2 = helper::map_keyname($key);
            $prefixedparams['custom_'.$key2] = $val;
            if ($key != $key2) {
                $prefixedparams['custom_'.$key] = $val;
            }
        }
        return $prefixedparams;
    }

    protected function get_user_params(): array {
        return [
            'user_id' => $this->user->id,
            'lis_person_name_given' => $this->user->firstname,
            'lis_person_name_family' => $this->user->lastname,
            'lis_person_name_full' => fullname($this->user),
            'lis_person_sourcedid' => $this->user->idnumber,
            'lis_person_contact_email_primary' => $this->user->email,
        ];
    }

    // TODO: Note 1p1 and 1p3 difference: the conditional inclusion of the BO lis_payload. LTI 2.0 always includes this.
    protected function get_lis_params(): array {

        // Some lis properties only apply when in course-related contexts.
        $contextid = $this->resourcelink->get('contextid');
        $context = \core\context::instance_by_id($contextid);
        if (($coursecontext = $context->get_course_context(false)) !== false) {
            $course = get_course($coursecontext->instanceid);
            $coursesectionsourcedid = $course->idnumber;
        }

        $lisdata = [
            ...(isset($coursesectionsourcedid) ? ['lis_course_section_sourcedid' => $coursesectionsourcedid] : []),
        ];

        $boclaims = $this->get_basic_outcomes_lis_payload();

        return array_merge($lisdata, $boclaims);
    }

    /**
     * Creates the lis params supporting basic outcomes.
     *
     * @return array the array of payload data.
     */
    protected function get_basic_outcomes_lis_payload(): array {
        global $CFG;
        $legacypayloaddata = [];

        // Basic Outcomes support.
        if ($this->resourcelink->get('gradable') && !empty($this->resourcelink->get('servicesalt'))) {

            $sourcedid = json_encode(
                helper::build_sourcedid(
                    $this->resourcelink->get('id'),
                    $this->user->id,
                    $this->resourcelink->get('servicesalt'),
                    $this->toolconfig->typeid
                )
            );
            $legacypayloaddata['lis_result_sourcedid'] = $sourcedid;

            $serviceurl = new \moodle_url('/ltix/service.php');
            $serviceurl = $serviceurl->out();

            $forcessl = false;
            if (!empty($CFG->ltix_forcessl)) {
                $forcessl = true;
            } else if (!empty($CFG->mod_lti_forcessl)) {
                // TODO: final removal of mod_lti_forcessl in Moodle 6.0.
                debugging('mod_lti_forcessl is deprecated. Please use ltix_forcessl instead.', DEBUG_DEVELOPER);
                $forcessl = true;
            }

            if ((isset($this->toolconfig->lti_forcessl) && ($this->toolconfig->lti_forcessl == '1')) || $forcessl) {
                $serviceurl = helper::ensure_url_is_https($serviceurl);
            }
            $legacypayloaddata['lis_outcome_service_url'] = $serviceurl;
        }

        return $legacypayloaddata;
    }

    // Common payload (common to RLL launches for all versions) herafter:

    protected function resolve_substitution(array $payloaddata): array {
        //  TODO the parameter parser instance should be LTI2 aware, and check capabilities during variable expansion.
        //   substitution should NOT take place for variables that are capabilities which are NOT enabled.
        //   E.g. full name should not be resolved for uses of $Person.name.full unless Person.name.full is in enabledcapability.
        foreach ($payloaddata as $key => $value) {
            // Substitution is only performed for custom params.
            if (str_starts_with($key, 'custom_')) {
                $payloaddata[$key] = $this->paramsubstitutor->substitute($value, $payloaddata);
            }
        }

        return $payloaddata;
    }

    protected function get_launch_presentation_params(): array {
        $contextid = $this->resourcelink->get('contextid');
        $context = \core\context::instance_by_id($contextid);

        $launchcontainer = helper::get_launch_container(
            (object) ['launchcontainer' => $this->resourcelink->get('launchcontainer')],
            ['launchcontainer' => $this->toolconfig->lti_launchcontainer] // Coerce into expected get_type_config() format object.
        );
        $target = '';
        switch($launchcontainer) {
            case constants::LTI_LAUNCH_CONTAINER_EMBED:
            case constants::LTI_LAUNCH_CONTAINER_EMBED_NO_BLOCKS:
                $target = 'iframe';
                break;
            case constants::LTI_LAUNCH_CONTAINER_REPLACE_MOODLE_WINDOW:
                $target = 'frame';
                break;
            case constants::LTI_LAUNCH_CONTAINER_WINDOW:
                $target = 'window';
                break;
        }

        if (($coursecontext = $context->get_course_context(false)) !== false) {
            $course = get_course($coursecontext->instanceid);

            // Add the return URL. We send the launch container along to help us avoid frames-within-frames when the user returns.
            // Note: launch_presentation_return_url is only set for course-context-related launches presently,
            // given the return endpoint (ltix/return.php) is a legacy endpoint and only works in that situation.
            // The 'instanceid' param, which the endpoint supports as an optional param, is deliberately omitted here, since the
            // endpoint expects that to match a legacy 'lti' record (being a legacy endpoint).
            // It's not a required part of the return flow and can be safely omitted.
            $returnurlparams = [
                'course' => $course->id,
                'launch_container' => $launchcontainer,
                'sesskey' => sesskey()
            ];
            $url = new \moodle_url('/ltix/return.php', $returnurlparams);
            $returnurl = $url->out(false);

            if (isset($this->toolconfig->forcessl) && ($this->toolconfig->forcessl == '1')) {
                $returnurl = helper::ensure_url_is_https($returnurl);
            }
        }

        return [
            'launch_presentation_locale' => current_language(),
            'launch_presentation_document_target' => $target,
            ...(isset($returnurl) ? ['launch_presentation_return_url' => $returnurl] : [])
        ];
    }

    protected function get_tool_platform_params(): array {
        global $CFG;
        if (!empty($CFG->ltix_institution_name)) {
            $name = trim(html_to_text($CFG->ltix_institution_name, 0));
        } else if (!empty($CFG->mod_lti_institution_name)) {
            // TODO final removal of the mod_lti_institution_name fallback code in Moodle 6.0.
            debugging('mod_lti_institution_name is deprecated. Please use ltix_institution_name instead.', DEBUG_DEVELOPER);
            $name = trim(html_to_text($CFG->mod_lti_institution_name, 0));
        } else {
            $name = get_site()->shortname;
        }

        return [
            'tool_consumer_info_product_family_code' => 'moodle',
            'tool_consumer_info_version' => strval($CFG->version),
            'tool_consumer_instance_guid' => helper::get_organizationid((array)$this->toolconfig), // TODO this expects get_type_config format and currently won't work.
            'tool_consumer_instance_name' => $name,
            'tool_consumer_instance_description' => trim(html_to_text(get_site()->fullname, 0)),
        ];
    }

    protected function get_context_params(): ?array {
        $contextid = $this->resourcelink->get('contextid');
        $context = \core\context::instance_by_id($contextid);

        // Historically, only course context launches are supported.
        if (($coursecontext = $context->get_course_context(false)) === false) {
            return null;
        }

        $course = get_course($coursecontext->instanceid);
        $contexttype = $course->format == 'site' ? 'Group' : 'CourseSection';

        return [
            'context_id' => $course->id,
            'context_label' => $context->get_context_name(),
            'context_title' => $context->get_context_name(),
            'context_type' => $contexttype,
        ];
    }
}
