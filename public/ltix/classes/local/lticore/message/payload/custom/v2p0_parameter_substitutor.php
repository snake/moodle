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

class v2p0_parameter_substitutor implements parameter_substitutor_interface {

    public function __construct(
        protected common_parameter_substitutor $commonsubstitutor,
        protected \stdClass $toolconfig,
    ) {
    }

    public function substitute(string $customparam, array $sourcedata): string {
        if ($this->can_be_subbed($customparam)) {
            $customparam = $this->commonsubstitutor->substitute($customparam, $sourcedata);
        }

        return $customparam;
    }

    /**
     * Checks whether the custom parameter value, e.g. $User.id, is allowed to be substituted.
     *
     * In LTI 2p0, variable substitution is controlled by LTI capabilities. A variable cannot be substituted if it is controlled
     * by a capability and that capability is not enabled. Otherwise, substitution is permitted.
     *
     * TODO: this method is a candidate to move into a class, and inject, since this same logic will be needed when building the
     *  request message (to filter request params).
     *
     * @param string $customparam
     * @return bool
     */
    protected function can_be_subbed(string $customparam): bool {
        // E.g. of $customparam: $Context.title.
        // E.g. of $allcaps: ['Context.title' => 'context_title'].
        // E.g. of $enabledcaps: ['Context.title', 'Context.id'].

        // Only variables can be sub'd.
        $isvariable = str_starts_with($customparam, '$');
        if (!$isvariable) {
            return false;
        }

        // Only variables corresponding to enabled capabilities can be sub'd.
        $enabledcapabilities = explode("\n", $this->toolconfig->enabledcapability);
        return in_array(substr($customparam, 1), $enabledcapabilities);
    }
}
