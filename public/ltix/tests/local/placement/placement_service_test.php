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

namespace core_ltix\local\placement;

use core_ltix\constants;
use core_ltix\local\lticore\models\resource_link;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Test class for \core_ltix\local\placement\placement_service.
 *
 * @package    core_ltix
 * @copyright  2025 Muhammad Arnaldo <muhammad.arnaldo@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\core_ltix\local\placement\placement_service::class)]
final class placement_service_test extends \advanced_testcase {
    /**
     * Test getting launch container for a link.
     *
     * @param int|null $toollaunchcontainer Launch container from tool type
     * @param int|null $linklaunchcontainer Launch container from link
     * @param int $expected Expected result
     * @param bool $mobile If test should simulate mobile device
     */
    #[DataProvider('get_launch_container_provider')]
    public function test_get_launch_container_for_link(
        ?int $toollaunchcontainer,
        ?int $linklaunchcontainer,
        int $expected,
        bool $mobile = false
    ): void {
        $this->resetAfterTest();

        if ($mobile) {
            $this->pretend_to_be_mobile_device();
        }

        /** @var \core_ltix_generator $ltigenerator */
        $ltigenerator = $this->getDataGenerator()->get_plugin_generator('core_ltix');
        $typeid = $ltigenerator->create_tool_types([
            'name' => 'Test Tool',
            'baseurl' => 'http://example.com/lti',
            'lti_launchcontainer' => $toollaunchcontainer,
        ]);

        $reslink = new resource_link(0, (object) [
            'typeid' => $typeid,
            'component' => 'some_component',
            'itemtype' => 'some_component:placementtype',
            'itemid' => 999,
            'contextid' => 999,
            'url' => 'stub',
            'title' => 'stub',
            'text' => 'stub',
            'textformat' => 0,
            'gradable' => true,
            'servicesalt' => 'stub',
            ...(!is_null($linklaunchcontainer) ? ['launchcontainer' => $linklaunchcontainer] : []),
        ]);

        $launchcontainer = placement_service::get_launch_container_for_link($reslink);
        $this->assertEquals($expected, $launchcontainer);
    }

    /**
     * Data provider for test_get_launch_container_for_link.
     *
     * @return array the test case data.
     */
    public static function get_launch_container_provider(): array {
        return [
            'Use tool launch container when link launch container is default' => [
                'toollaunchcontainer' => constants::LTI_LAUNCH_CONTAINER_WINDOW,
                'linklaunchcontainer' => constants::LTI_LAUNCH_CONTAINER_DEFAULT,
                'expected' => constants::LTI_LAUNCH_CONTAINER_WINDOW,
            ],
            'Use link launch container when tool launch container is default' => [
                'toollaunchcontainer' => constants::LTI_LAUNCH_CONTAINER_DEFAULT,
                'linklaunchcontainer' => constants::LTI_LAUNCH_CONTAINER_WINDOW,
                'expected' => constants::LTI_LAUNCH_CONTAINER_WINDOW,
            ],
            'Use embed no blocks when both set to default' => [
                'toollaunchcontainer' => constants::LTI_LAUNCH_CONTAINER_DEFAULT,
                'linklaunchcontainer' => constants::LTI_LAUNCH_CONTAINER_DEFAULT,
                'expected' => constants::LTI_LAUNCH_CONTAINER_EMBED_NO_BLOCKS,
            ],
            'Use link launch container when tool launch container is empty' => [
                'toollaunchcontainer' => null,
                'linklaunchcontainer' => constants::LTI_LAUNCH_CONTAINER_WINDOW,
                'expected' => constants::LTI_LAUNCH_CONTAINER_WINDOW,
            ],
            'Use tool launch container when link launch container is empty' => [
                'toollaunchcontainer' => constants::LTI_LAUNCH_CONTAINER_WINDOW,
                'linklaunchcontainer' => null,
                'expected' => constants::LTI_LAUNCH_CONTAINER_WINDOW,
            ],
            'Use embed no blocks when both containers are empty' => [
                'toollaunchcontainer' => null,
                'linklaunchcontainer' => null,
                'expected' => constants::LTI_LAUNCH_CONTAINER_EMBED_NO_BLOCKS,
            ],
            'Mobile device should return replace window' => [
                'toollaunchcontainer' => constants::LTI_LAUNCH_CONTAINER_DEFAULT,
                'linklaunchcontainer' => constants::LTI_LAUNCH_CONTAINER_EMBED_NO_BLOCKS,
                'expected' => constants::LTI_LAUNCH_CONTAINER_REPLACE_MOODLE_WINDOW,
                'mobile' => true,
            ],
        ];
    }

    /**
     * Helper method to simulate mobile device.
     *
     * @return void.
     */
    private function pretend_to_be_mobile_device(): void {
        \core_useragent::instance(true, 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X)');
    }
}
