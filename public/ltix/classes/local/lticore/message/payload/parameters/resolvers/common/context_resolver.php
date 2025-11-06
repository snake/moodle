<?php

namespace core_ltix\local\lticore\message\payload\parameters\resolvers\common;

use core_ltix\local\lticore\message\payload\parameters\resolvers\parameters_resolver;

class context_resolver implements parameters_resolver {

    public function __construct(protected \stdClass $course) {
    }

    public function resolve(array $parameters): array {
        $contexttype = $this->course->format == 'site' ? 'Group' : 'CourseSection';

        return array_merge(
            $parameters,
            [
                'context_id' => $this->course->id,
                'context_label' => trim(html_to_text($this->course->shortname, 0)),
                'context_title' => trim(html_to_text($this->course->fullname, 0)),
                'context_type' => $contexttype
            ]
        );
    }
}
