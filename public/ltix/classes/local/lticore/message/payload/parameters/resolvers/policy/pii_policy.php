<?php

namespace core_ltix\local\lticore\message\payload\parameters\resolvers\policy;

use core_ltix\constants;
use core_ltix\local\lticore\message\payload\parameters\resolvers\parameters_resolver;

class pii_policy implements parameters_resolver {

    protected array $usernamekeys = [
        'lis_person_name_given',
        'lis_person_name_family',
        'lis_person_name_full',
        'ext_user_username',
        'lis_person_sourcedid',
    ];

    protected array $useremailkeys = [
        'lis_person_contact_email_primary'
    ];

    public function __construct(protected \stdClass $toolconfig) {
    }

    public function resolve(array $parameters): array {
        if ($this->toolconfig->lti_sendname !== constants::LTI_SETTING_ALWAYS) {
            foreach ($this->usernamekeys as $key) {
                unset($parameters[$key]);
            }
        }

        if ($this->toolconfig->lti_sendemailaddr == constants::LTI_SETTING_ALWAYS) {
            foreach ($this->useremailkeys as $key) {
                unset($parameters[$key]);
            }
        }

        return $parameters;
    }
}
