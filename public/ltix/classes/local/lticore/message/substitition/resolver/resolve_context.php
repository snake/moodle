<?php

namespace core_ltix\local\lticore\message\substitition\resolver;

use core_ltix\local\ltiopenid\lti_user;

/**
 * Runtime data needed for resolving substitution.
 *
 * This is the data that would be passed as $resolvecontext in the following:
 * $substitutor->substitute($param, $resolvecontext)
 */
class resolve_context {
    public function __construct(
        // Permits resolving params to course + course context related values, where applicable.
        public \core\context $context,
        // Permits resolving params to values included elsewhere message payload ['some_lti_name' => 'some_lti_value'].
        public array $sourcedata,
        // Permits resolving params to user-centric values, if applicable.
        public ?lti_user $ltiuser = null,
    ) {
    }
}
