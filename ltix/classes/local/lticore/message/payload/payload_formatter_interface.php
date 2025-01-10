<?php

namespace core_ltix\local\lticore\message\payload;

/**
 * Describes objects that format LTI payloads.
 */
interface payload_formatter_interface {

    /**
     * Converts unformatted payload data into formatted payload data.
     *
     * @param array $unformattedpayload the unformatted payload data.
     * @return array
     */
    public function format(array $unformattedpayload): array;
}
