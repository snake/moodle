<?php

namespace core_ltix\local\lticore\message\payload;

class lti_1p3_payload_formatter implements payload_formatter_interface {

    public function __construct(protected array $jwtclaimmapping) {
    }

    public function format(array $unformattedpayload): array {

        $claimmapping = $this->jwtclaimmapping;
        $payload = [];
        foreach ($unformattedpayload as $key => $value) {
            $claim = \core_ltix\constants::LTI_JWT_CLAIM_PREFIX;
            if (array_key_exists($key, $claimmapping)) {
                $mapping = $claimmapping[$key];
                $type = $mapping["type"] ?? "string";
                if ($mapping['isarray']) {
                    $value = explode(',', $value);
                    sort($value);
                } else if ($type == 'boolean') {
                    $value = isset($value) && ($value == 'true');
                }
                if (!empty($mapping['suffix'])) {
                    $claim .= "-{$mapping['suffix']}";
                }
                $claim .= '/claim/';
                if (is_null($mapping['group'])) {
                    $payload[$mapping['claim']] = $value;
                } else if (empty($mapping['group'])) {
                    $payload["{$claim}{$mapping['claim']}"] = $value;
                } else {
                    $claim .= $mapping['group'];
                    $payload[$claim][$mapping['claim']] = $value;
                }
            } else if (strpos($key, 'custom_') === 0) {
                $payload["{$claim}/claim/custom"][substr($key, 7)] = $value;
            } else if (strpos($key, 'ext_') === 0) {
                $payload["{$claim}/claim/ext"][substr($key, 4)] = $value;
            }
        }

        // Format the context claim's type property to use 1p3 vocab.
        if (isset($payload[\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/context']['type'])) {
            $payload[\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/context']['type'] = array_map(function($contexttype) {
                return !str_contains($contexttype, 'http://purl.imsglobal.org/vocab/lis/v2/course#') ?
                    'http://purl.imsglobal.org/vocab/lis/v2/course#'.$contexttype : $contexttype;
            }, $payload[\core_ltix\constants::LTI_JWT_CLAIM_PREFIX.'/claim/context']['type']);
        }

        return $payload;
    }

}
