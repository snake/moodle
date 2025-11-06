<?php

namespace core_ltix\local\lticore\message\payload\parameters\resolvers\custom;

use core_ltix\helper;
use core_ltix\local\lticore\message\payload\parameters\resolvers\parameters_resolver;

class custom_resolver implements parameters_resolver {

    public function __construct(protected \stdClass $toolconfig) {
    }

    public function resolve(array $parameters): array {
        $toolcustomstr = !empty($this->toolconfig->lti_customparameters) ? $this->toolconfig->lti_customparameters : '';

        if ($toolcustomstr) {
            $parsedtoolcustom = [];
            $toolcustom = helper::split_parameters($toolcustomstr);
            foreach ($toolcustom as $key => $val) {
                $parsedtoolcustom['custom_'.$key] = $val;
            }
            $parameters = array_merge($parameters, $parsedtoolcustom);
        }

        return $parameters;
    }
}
