<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace core_ltix\local\lticore\message\payload\custom;

use core_ltix\local\lticore\facades\service\launch_service_facade_interface;

class v1px_parameter_substitutor implements parameter_substitutor_interface {

    public function __construct(
        protected common_parameter_substitutor $commonsubstitutor,
        protected launch_service_facade_interface $servicefacade,
    ) {
    }

    public function substitute(string $customparam, array $sourcedata): string {
        $subd = $this->commonsubstitutor->substitute($customparam, $sourcedata);

        // Additionally, allow services to perform substitution.
        if (str_starts_with($subd, '$')) {
            $subd = $this->servicefacade->parse_custom_param_value($subd);
        }

        return $subd;
    }
}
