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

namespace core_ltix\fixtures;

use core_ltix\local\lticore\message\request\builder\v1p3\v1p3_launch_request_builder;

class testable_v1p3_launch_request_builder extends v1p3_launch_request_builder {

    /**
     * Subclass for testing the base functionality provided by the v1p3_launch_request_builder class.
     *
     * @param \stdClass $toolconfig
     * @param string $messagetype
     * @param string $issuer
     * @param string $targetlinkuri
     * @param string $loginhint
     * @param array $roles
     * @param array $extraclaims
     */
    public function __construct(
        protected \stdClass $toolconfig,
        protected string $messagetype,
        protected string $issuer,
        protected string $targetlinkuri,
        protected string $loginhint,
        protected array $roles = [],
        protected array $extraclaims = []
    ) {
        parent::__construct(
            $toolconfig,
            $messagetype,
            $issuer,
            $targetlinkuri,
            $loginhint,
            $roles,
            $extraclaims
        );
    }
}
