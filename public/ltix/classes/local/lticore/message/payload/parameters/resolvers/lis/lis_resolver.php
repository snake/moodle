<?php

namespace core_ltix\local\lticore\message\payload\parameters\resolvers\lis;

use core_ltix\constants;
use core_ltix\local\lticore\message\payload\parameters\resolvers\parameters_resolver;

class lis_resolver implements parameters_resolver {

    public function __construct(protected \stdClass $toolconfig, protected \stdClass $course, protected \stdClass $user) {
    }

    public function resolve(array $parameters): array {

        $parameters['lis_course_section_sourcedid'] = $this->course->idnumber;

        $parameters['lis_person_name_given'] = $this->user->firstname;
        $parameters['lis_person_name_family'] = $this->user->lastname;
        $parameters['lis_person_name_full'] = fullname($this->user);
        $parameters['ext_user_username'] = $this->user->username;
        $parameters['lis_person_sourcedid'] = $this->user->idnumber;
        $parameters['lis_person_contact_email_primary'] = $this->user->email;

        return $parameters;
    }
}
