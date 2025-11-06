<?php

namespace core_ltix\local\lticore\message\payload\parameters\resolvers;

interface parameters_resolver {
    /**
     * @param array $parameters the array of all parameters resolved up to this point.
     * @return array the list of parameters having new parameters added by the resolver.
     */
    public function resolve(array $parameters): array;
}
