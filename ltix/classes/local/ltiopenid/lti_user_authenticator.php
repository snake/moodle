<?php

namespace core_ltix\local\ltiopenid;

class lti_user_authenticator implements lti_user_authenticator_interface {

    /**
     * Constructor.
     *
     * This assumes that the samesite cookie workaround involving re-POSTing to self has already taken place and that the
     * user $user has the active session which initiated the LTI launch.
     *
     * @param \stdClass $user the user with the active session who we want to auth. Will be verified against loginhint.
     * @param bool $forceanonymousname whether to force an anonymous user auth. Lets clients dictate this for legacy launches.
     * @param bool $forceanonymousemail whether to force an anonymous user auth. Lets clients dictate this for legacy launches.
     */
    public function __construct(
        protected \stdClass $user,
        protected bool $forceanonymousname = false, // TODO privacy object dependency would be more robust.
        protected bool $forceanonymousemail = false, // TODO privacy object dependency would be more robust.
    ) {
    }

    public function authenticate(\stdClass $toolconfig, string $loginhint): lti_auth_result {
        $userid = intval($loginhint);

        // Validate loginhint user against the user being auth'd - they must match.
        if (!$this->user->id === $userid) {
            return new lti_auth_result(false, null);
        }

        // TODO: Other auth checks....
        //  E.g. user can access tool, is permitted to launch that placement, etc. etc
        //  this likely would have been checked prior to initting a launch, however,
        //  it's probably a good idea to prevent auth in such cases.

        $isanonymousname = $this->get_is_anonymous_name($toolconfig);
        $isanonymousemail = $this->get_is_anonymous_email($toolconfig);

        $ltiuser = new lti_user(
            id: $this->user->id,
            name: !$isanonymousname ? fullname($this->user) : null,
            givenname: !$isanonymousname ? $this->user->firstname : null,
            familyname: !$isanonymousname ? $this->user->lastname : null,
            email: !$isanonymousemail ? $this->user->email : null,
            idnumber: $this->user->idnumber ?? null,
            username: !$isanonymousname ? $this->user->username : null,
        );

        return new lti_auth_result(true, $ltiuser);
    }

    /**
     * Since 4.3, anonymous launches are controlled by the tool-registration-level privacy settings, however, support for the
     * legacy 'Delegate to teacher' option is still present in code to support legacy links defined this way; legacy links may
     * have anonymous launches defined at the link-level.
     *
     * @param \stdClass $toolconfig
     * @return bool
     */
    protected function get_is_anonymous_name(\stdClass $toolconfig): bool {
        $toolisanonymousname = $toolconfig->lti_sendname == \core_ltix\constants::LTI_SETTING_NEVER;

        return $toolisanonymousname || $this->forceanonymousname;
    }

    /**
     * Since 4.3, anonymous launches are controlled by the tool-registration-level privacy settings, however, support for the
     * legacy 'Delegate to teacher' option is still present in code to support legacy links defined this way; legacy links may
     * have anonymous launches defined at the link-level.
     *
     * @param \stdClass $toolconfig
     * @return bool
     */
    protected function get_is_anonymous_email(\stdClass $toolconfig): bool {
        $toolisanonymousemail = $toolconfig->lti_sendemailaddr == \core_ltix\constants::LTI_SETTING_NEVER;

        return $toolisanonymousemail || $this->forceanonymousemail;
    }
}
