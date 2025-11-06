<?php

namespace core_ltix\local\lticore\facades\service;

/**
 * Class wrapping the internals of custom parameter resolution by 'ltixservice' plugins.
 *
 * @internal
 */
class substitution_service_facade {


    public function __construct() {
    }

    public function substitute(string $paramstr): string {
        $val = $paramstr;
        foreach (\core_ltix\helper::get_services() as $service) {
            $value = $service->parse_value($val);
            if ($val != $value) {
                break;
            }
        }
        return $value;
    }
}
