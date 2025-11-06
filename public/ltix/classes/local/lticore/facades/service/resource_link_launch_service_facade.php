<?php

namespace core_ltix\local\lticore\facades\service;

use core_ltix\local\lticore\token\context;
use core_ltix\local\lticore\models\resource_link;
use core_ltix\local\ltiservice\service_base;

/**
 * Facade for dealing with service plugins during a resource link launch.
 *
 * Simplifies querying the 'service' and 'source' plugins for various things.
 */
// TODO: interface this? can the launch builders just rely on any service facade?
class resource_link_launch_service_facade implements launch_service_facade_interface {

    protected string $messagetype;

    public function __construct(
        protected \stdClass $toolconfig,
        protected \stdClass $course,
        protected int $userid,
        protected resource_link $resourcelink,
    ) {
        $this->messagetype = 'LtiResourceLinkRequest'; // TODO: make const, but also, don't services expect the 1p1 message type?
    }

    public function get_target_link_uri(): string {
        // Call into each of the services, allowing them a chance to change the target_link_uri of the launch.
        $linkurl = $this->resourcelink->get('url');
        $targetlinkuri = $linkurl ?: $this->toolconfig->toolurl;

        /** @var service_base $service */
        foreach (\core_ltix\helper::get_services() as $service) {
            $targetlinkuri = $service->override_target_link_uri(
                toolconfig: $this->toolconfig,
                messagetype: $this->messagetype,
                targetlinkuri: $targetlinkuri,
                course: $this->course,
                userid: $this->userid,
                resourcelink: $this->resourcelink,
            );
        }
        return $targetlinkuri;
    }


    public function get_launch_parameters(): array {
        $params = [];
        /** @var service_base $service */
        foreach (\core_ltix\helper::get_services() as $service) {
            $params = $service->get_launch_params(
                toolconfig: $this->toolconfig,
                messagetype: $this->messagetype,
                targetlinkuri: $this->resourcelink->get('url'),
                course: $this->course,
                userid: $this->userid,
                resourcelink: $this->resourcelink,
            );
        }
        return $params;
    }

    public function parse_custom_param_value(string $value): string {
        $val = $value;
        foreach (\core_ltix\helper::get_services() as $service) {
            $value = $service->parse_value($val);
            if ($val != $value) {
                break;
            }
        }
        return $value;
    }
}
