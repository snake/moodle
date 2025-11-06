<?php

namespace core_ltix\local\lticore\message\payload\parameters\resolvers\service;

use core_ltix\local\lticore\facades\service\launch_service_facade_interface;
use core_ltix\local\lticore\message\payload\parameters\resolvers\parameters_resolver;

class ltixservice_resolver implements parameters_resolver {

    public function __construct(protected launch_service_facade_interface $servicefacade) {
    }

    public function resolve(array $parameters): array {
        $servicecustomdata = [];
        foreach ($this->servicefacade->get_launch_parameters() as $param => $val) {
            $servicecustomdata['custom_'.$param] = $val;
        }

        return array_merge($parameters, $servicecustomdata);
    }
}
