<?php

namespace core_ltix\local\lticore\message\payload\parameters\resolvers\tool_consumer;

use core_ltix\helper;
use core_ltix\local\lticore\message\payload\parameters\resolvers\parameters_resolver;

class tool_consumer_resolver implements parameters_resolver {

    public function __construct(protected \stdClass $toolconfig) {
    }

    public function resolve(array $parameters): array {
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

        return array_merge(
            $parameters,
            [
                'tool_consumer_info_product_family_code' => 'moodle',
                'tool_consumer_info_version' => strval($CFG->version),
                'tool_consumer_instance_guid' => helper::get_organizationid((array)$this->toolconfig), // TODO this expects get_type_config format and currently won't work.
                'tool_consumer_instance_name' => $name,
                'tool_consumer_instance_description' => trim(html_to_text(get_site()->fullname, 0)),
            ]
        );
    }
}
