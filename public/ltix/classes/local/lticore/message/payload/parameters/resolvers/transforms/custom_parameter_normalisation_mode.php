<?php

namespace core_ltix\local\lticore\message\payload\parameters\resolvers\transforms;

enum custom_parameter_normalisation_mode: int {
    case MODE_NORMALISED_ONLY = 0;
    case MODE_BOTH = 1;
}
