<?php

namespace core_ltix\local\lticore\models;

use core\persistent;

class lti_types_config extends persistent {

    /** @var string The table name. */
    public const TABLE = 'lti_types_config';

    protected static function define_properties(): array {
        return [
            'typeid' => [
                'type' => PARAM_INT,
            ],
            'name' => [
                'type' => PARAM_TEXT,
            ],
            'value' => [
                'type' => PARAM_TEXT,
            ]
        ];
    }

    /**
     * Make sure the type exists before saving the type config.
     *
     * @param int $typeid the id of the related lti_types record.
     * @return \lang_string|true
     */
    protected function validate_typeid($typeid) {
        global $DB;
        if (!$DB->record_exists('lti_types', ['id' => $this->get('typeid')])) {
            return new \lang_string('invalidcourseid', 'error');
        }
        return true;
    }
}
