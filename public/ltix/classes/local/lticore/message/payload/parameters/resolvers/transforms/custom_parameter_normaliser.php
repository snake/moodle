<?php

namespace core_ltix\local\lticore\message\payload\parameters\resolvers\transforms;

use core_ltix\helper;
use core_ltix\local\lticore\message\payload\parameters\resolvers\parameters_resolver;

class custom_parameter_normaliser implements parameters_resolver {

    public function __construct(protected custom_parameter_normalisation_mode $normalisationmode) {
    }

    /**
     * Normalise the KEYS of a custom params array (not values).
     *
     * @param array $customparams assoc array of custom param names => values.
     * @return array the array with all names normalised.
     */
    private function normalise(array $customparams): array {
        $normalisedparams = [];
        foreach ($customparams as $key => $val) {
            $name = substr($key, strlen('custom_'));
            $normalisedname = helper::map_keyname($name);
            $normalisedparams['custom_'.$normalisedname] = $val;
        }
        return $normalisedparams;
    }

    public function resolve(array $parameters): array {
        $customparams = array_filter($parameters, function ($key) {
            return str_starts_with($key, "custom_");
        }, ARRAY_FILTER_USE_KEY);

        switch ($this->normalisationmode) {
            case custom_parameter_normalisation_mode::MODE_NORMALISED_ONLY:
                $normalisedcustomparams = $this->normalise($customparams);
                return array_merge(
                    array_filter($parameters, fn($x) => !str_starts_with($x, 'custom_'), ARRAY_FILTER_USE_KEY),
                    $normalisedcustomparams
                );
            case custom_parameter_normalisation_mode::MODE_BOTH:
                return array_merge($parameters, $this->normalise($customparams));
            default:
                break;
        }
        return $parameters;
    }
}
