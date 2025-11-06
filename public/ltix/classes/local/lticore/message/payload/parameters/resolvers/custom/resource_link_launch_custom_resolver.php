<?php

namespace core_ltix\local\lticore\message\payload\parameters\resolvers\custom;

use core_ltix\helper;
use core_ltix\local\lticore\message\payload\parameters\resolvers\parameters_resolver;
use core_ltix\local\lticore\models\resource_link;

class resource_link_launch_custom_resolver extends custom_resolver implements parameters_resolver {

    // TODO: the tricky part here is that custom params can be used in deep linking (using just the tool custom params).
    //  or may be included during RLL, using the link's custom params.
    public function __construct(protected \stdClass $toolconfig, protected resource_link $resourcelink) {
    }

    public function resolve(array $parameters): array {
        // Get the tool custom params.
        $parameters = parent::resolve($parameters);

        // The link custom params override the tool-level custom params (specific trumps generic).
        $linkcustomstr = !empty($this->resourcelink->get('customparams')) ? $this->resourcelink->get('customparams') : '';
        $parsedlinkcustom = [];
        if ($linkcustomstr) {
            $linkcustom = helper::split_parameters($linkcustomstr);
            foreach ($linkcustom as $key => $val) {
                $parsedlinkcustom['custom_' . $key] = $val;
            }
        }

        return array_merge($parameters, $parsedlinkcustom);
    }
}
