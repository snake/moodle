<?php

namespace core_ltix\local\lticore\message\payload\parameters\processor\resolver\common;

use core_ltix\local\lticore\message\context\collection\launch_context;
use core_ltix\local\lticore\message\context\item\user_context;
use core_ltix\local\lticore\message\payload\parameters\pipeline\core\parameters_processor;

class user_resolver implements parameters_processor {

    public function process(array $parameters, launch_context $data): array {

        $user = $data->require(user_context::class)->user;

        $parameters['user_id'] = strval($user->id);

        return $parameters;
    }
}
