<?php

namespace core_ltix\local\lticore\message\payload\custom;

interface parameter_substitutor_interface {
    public function substitute(string $customparamvalue, array $sourcedata): string;
}
