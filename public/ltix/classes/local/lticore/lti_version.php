<?php

namespace core_ltix\local\lticore;

enum lti_version: string {
    case LTI_VERSION_1 = 'LTI-1p0';
    case LTI_VERSION_2 = 'LTI-2p0';
    case LTI_VERSION_1P3 = '1.3.0';
}
