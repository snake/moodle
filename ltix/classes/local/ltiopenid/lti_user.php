<?php

namespace core_ltix\local\ltiopenid;

class lti_user {

    public function __construct(
        public readonly string $id,
        public readonly ?string $name = null,
        public readonly ?string $givenname = null,
        public readonly ?string $familyname = null,
        public readonly ?string $email = null,
        public readonly ?string $idnumber = null,
        public readonly ?string $username = null,
    ) {
    }

    public function get_unformatted_userdata(): array {
        return array_filter([
            'user_id' => $this->id,
            'lis_person_name_full' => $this->name,
            'lis_person_name_given' => $this->givenname,
            'lis_person_name_family' => $this->familyname,
            'lis_person_contact_email_primary' => $this->email,
            'lis_person_sourcedid' => $this->idnumber,
            'ext_user_username' => $this->username,
        ]);
    }
}
