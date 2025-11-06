<?php

namespace core_ltix\local\lticore\message\payload\parameters\builders;

use core_ltix\local\lticore\message\payload\parameters\resolvers\parameters_resolver;

class parameters_builder {

    protected array $params = [];

    protected array $resolvers = [];

    public function with(string $name, mixed $value): self {
        $this->params[$name] = $value;
        return $this;
    }

    public function add_resolver(parameters_resolver $resolver): self {
        $this->resolvers[] = $resolver;
        return $this;
    }

    public function build(): array {
        $params = $this->params;

        foreach ($this->resolvers as $resolver) {
            $params = $resolver->resolve($params);
        }
        return $params;
    }
}
