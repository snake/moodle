<?php

namespace core_ltix\local\ltiservice;

use core_ltix\local\lticore\message\context\collection\launch_context;

interface plugin_parameters_service_interface {
    public function get_launch_parameters(launch_context $launchcontext): array;
}
