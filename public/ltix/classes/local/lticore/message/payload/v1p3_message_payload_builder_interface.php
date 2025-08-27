<?php

namespace core_ltix\local\lticore\message\payload;

interface v1p3_message_payload_builder_interface {

    // TODO: this may be better suited to an abstract class, if we have v1p3 specific things that MUST be done to the payload.
    //  e.g. custom claims need to be normalised
    //  Also, it could include claims that are common to all 1p3 messages...are there any?

    public function get_claims(): array;
}
