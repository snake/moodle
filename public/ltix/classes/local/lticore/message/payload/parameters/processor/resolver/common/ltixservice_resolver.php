<?php

namespace core_ltix\local\lticore\message\payload\parameters\processor\resolver\common;

use core_ltix\local\lticore\message\context\collection\launch_context;
use core_ltix\local\lticore\message\payload\parameters\pipeline\core\parameters_processor;
use core_ltix\local\ltiservice\plugin_parameters_service_interface;

final readonly class ltixservice_resolver implements parameters_processor {

    public function __construct(private plugin_parameters_service_interface $pluginparamsservice) {
    }

    public function process(array $parameters, launch_context $launchcontext): array {
        return array_merge(
            $parameters,
            $this->pluginparamsservice->get_launch_parameters($launchcontext)
        );
    }
}
