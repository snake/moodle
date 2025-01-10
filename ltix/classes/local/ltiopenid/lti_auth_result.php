<?php

namespace core_ltix\local\ltiopenid;

class lti_auth_result {

    public function __construct(
        protected bool $successful,
        protected ?lti_user $ltiuser,
    ) {
    }

    public function get_lti_user(): ?lti_user {
        return $this->ltiuser;
    }

    public function successful(): bool {
        return $this->successful;
    }
}
