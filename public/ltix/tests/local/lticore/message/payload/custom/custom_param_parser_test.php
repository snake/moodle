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

namespace local\lticore\message\payload\custom;

use core_ltix\local\lticore\message\payload\custom\custom_param_parser;
use core_ltix\local\lticore\message\payload\lis_vocab_converter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests covering custom_param_parser.
 *
 * @package    core_ltix
 * @copyright  2025 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(custom_param_parser::class)]
class custom_param_parser_test extends \basic_testcase {

    /**
     * Test covering parse().
     *
     * @param array $inputexpectedmap the array containing input values (keys) and expected output values (values).
     * @return void
     */
    #[DataProvider('parse_provider')]
    public function test_parse(array $inputexpectedmap): void {
        $roles = array_keys($inputexpectedmap);
        $expected = array_values($inputexpectedmap);

        $vc = new lis_vocab_converter();
        $convertedroles = $vc->to_v1_roles($roles);

        $this->assertEquals($expected, $convertedroles);
    }

    /**
     * Data provider for testing to_v1_roles().
     *
     * @return array the test data.
     */
    public static function parse_provider(): array {
        return [
            ''
        ];
    }
}
