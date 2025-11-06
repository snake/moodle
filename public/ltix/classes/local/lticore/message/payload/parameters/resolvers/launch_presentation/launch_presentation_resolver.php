<?php

namespace core_ltix\local\lticore\message\payload\parameters\resolvers\launch_presentation;

use core_ltix\constants;
use core_ltix\helper;
use core_ltix\local\lticore\message\payload\parameters\resolvers\parameters_resolver;
use core_ltix\local\lticore\models\resource_link;

class launch_presentation_resolver implements parameters_resolver {

    public function __construct(protected \stdClass $toolconfig, protected resource_link $resourcelink) {
    }

    public function resolve(array $parameters): array {

        $launchcontainer = helper::get_launch_container(
            (object) ['launchcontainer' => $this->resourcelink->get('launchcontainer')],
            ['launchcontainer' => $this->toolconfig->lti_launchcontainer] // Coerce into expected get_type_config() format object.
        );
        $target = '';
        switch($launchcontainer) {
            case constants::LTI_LAUNCH_CONTAINER_EMBED:
            case constants::LTI_LAUNCH_CONTAINER_EMBED_NO_BLOCKS:
                $target = 'iframe';
                break;
            case constants::LTI_LAUNCH_CONTAINER_REPLACE_MOODLE_WINDOW:
                $target = 'frame';
                break;
            case constants::LTI_LAUNCH_CONTAINER_WINDOW:
                $target = 'window';
                break;
        }

        // Add the return URL. We send the launch container along to help us avoid frames-within-frames when the user returns.
        // Note: launch_presentation_return_url is only set for course-context-related launches presently,
        // given the return endpoint (ltix/return.php) is a legacy endpoint and only works in that situation.
        // The 'instanceid' param, which the endpoint supports as an optional param, is deliberately omitted here, since the
        // endpoint expects that to match a legacy 'lti' record (being a legacy endpoint).
        // It's not a required part of the return flow and can be safely omitted.
        $returnurlparams = [
            'course' => $this->course->id,
            'launch_container' => $launchcontainer,
            'sesskey' => sesskey()
        ];
        $url = new \moodle_url('/ltix/return.php', $returnurlparams);
        $returnurl = $url->out(false);

        if (isset($this->toolconfig->forcessl) && ($this->toolconfig->forcessl == '1')) {
            $returnurl = helper::ensure_url_is_https($returnurl);
        }


        return array_merge(
            $parameters,
            [
                'launch_presentation_locale' => current_language(),
                'launch_presentation_document_target' => $target,
                ...(isset($returnurl) ? ['launch_presentation_return_url' => $returnurl] : [])
            ]
        );
    }
}
