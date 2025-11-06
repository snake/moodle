<?php

namespace core_ltix\local\lticore\message\payload\parameters\resolvers\lis;

use core_ltix\constants;
use core_ltix\helper;
use core_ltix\local\lticore\message\payload\parameters\resolvers\parameters_resolver;
use core_ltix\local\lticore\models\resource_link;

class lis_bo_resolver implements parameters_resolver {

    public function __construct(protected \stdClass $toolconfig, protected resource_link $resourcelink, protected \stdClass $user) {
    }

    public function resolve(array $parameters): array {
        global $CFG;

        if ($this->resourcelink->get('gradable') && !empty($this->resourcelink->get('servicesalt')) &&
            ($this->toolconfig->lti_acceptgrades == constants::LTI_SETTING_ALWAYS ||
                ($this->toolconfig->lti_acceptgrades == constants::LTI_SETTING_DELEGATE))) {

            // TODO: since lis_result_sourceid includes user data, should it be generated at auth time too?
            $sourcedid = json_encode(
                helper::build_sourcedid(
                    $this->resourcelink->get('id'),
                    $this->user->id,
                    $this->resourcelink->get('servicesalt'),
                    $this->toolconfig->typeid
                )
            );
            $parameters['lis_result_sourcedid'] = $sourcedid;

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

            if ((isset($this->toolconfig->lti_forcessl) && ($this->toolconfig->lti_forcessl == '1')) or $forcessl) {
                $serviceurl = helper::ensure_url_is_https($serviceurl);
            }
            $parameters['lis_outcome_service_url'] = $serviceurl;
        }

        return $parameters;
    }
}
