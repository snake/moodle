<?php

namespace core_ltix\local\lticore\message\payload;

/**
 * Describes objects that format LTI payloads.
 */
interface v1px_payload_converter_interface {

    /**
     * Converts legacy 1p1 payload data into 1p3 claims data.
     *
     * @param array $params the 1p1 payload data.
     * @return array the array of 1p3 claims.
     */
    public function params_to_claims(array $params): array;


    public function claims_to_params(array $claims): array;
}
