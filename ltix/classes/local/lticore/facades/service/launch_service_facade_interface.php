<?php

namespace core_ltix\local\lticore\facades\service;

/**
 * Abstraction of the service API, encapsulating interactions used during a message launch.
 */
interface launch_service_facade_interface {
    public function get_target_link_uri(): string;
    public function get_launch_parameters(): array;
    public function parse_custom_param_value(string $value): string;
}
