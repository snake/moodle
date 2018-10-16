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

/**
 * Testing the repository objects within core_favourites.
 *
 * @package    core_favourites
 * @category   test
 * @copyright  2018 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use \core_favourites\local\favourites_repository;

/**
 * Test class covering the favourites_repository.
 *
 * @copyright  2018 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class favourites_repository_testcase extends advanced_testcase {

    public function setUp() {
        $this->resetAfterTest();
    }

    // Basic setup stuff to be reused in most tests.
    protected function setup_users_and_courses() {
        $user1 = self::getDataGenerator()->create_user();
        $user1context = \context_user::instance($user1->id);
        $user2 = self::getDataGenerator()->create_user();
        $user2context = \context_user::instance($user2->id);
        $course1 = self::getDataGenerator()->create_course();
        $course2 = self::getDataGenerator()->create_course();
        $course1context = context_course::instance($course1->id);
        $course2context = context_course::instance($course2->id);
        return [$user1context, $user2context, $course1context, $course2context];
    }

    /**
     * Verify the basic create operation can create records, and is validated.
     */
    public function test_add() {
        list($user1context, $user2context, $course1context, $course2context) = $this->setup_users_and_courses();

        // Create a favourites repository and favourite a course.
        $favouritesrepo = new favourites_repository($user1context);

        $favcourse = (object)[
            'userid' => $user1context->instanceid,
            'component' => 'core_course',
            'itemtype' => 'course',
            'itemid' => $course1context->instanceid,
            'contextid' => $course1context->id,
        ];
        $timenow = time(); // Reference only, to check that the created item has a time equal to or greater than this.
        $favourite = $favouritesrepo->add($favcourse);

        // Verify we get the record back.
        $this->assertInstanceOf(\stdClass::class, $favourite);
        $this->assertEquals('core_course', $favourite->component);
        $this->assertEquals('course', $favourite->itemtype);

        // Verify the returned object has additional properties, created as part of the add.
        $this->assertObjectHasAttribute('ordering', $favourite);
        $this->assertObjectHasAttribute('timecreated', $favourite);
        $this->assertGreaterThanOrEqual($timenow, $favourite->timecreated);

        // Try to save the same record again and confirm the store throws an exception.
        $this->expectException('dml_write_exception');
        $favouritesrepo->add($favcourse);
    }

    /**
     * Tests that malformed favourites cannot be saved.
     */
    public function test_add_malformed_favourite() {
        list($user1context, $user2context, $course1context, $course2context) = $this->setup_users_and_courses();

        // Create a favourites repository and favourite a course.
        $favouritesrepo = new favourites_repository($user1context);

        $favcourse = (object)[
            'userid' => $user1context->instanceid,
            'component' => 'core_course',
            'itemtype' => 'course',
            'itemid' => $course1context->instanceid,
            'contextid' => $course1context->id,
            'anotherfield' => 'cat'
        ];

        $this->expectException('moodle_exception');
        $favouritesrepo->add($favcourse);
    }

    /**
     * Tests that incomplete favourites cannot be saved.
     */
    public function test_add_incomplete_favourite() {
        list($user1context, $user2context, $course1context, $course2context) = $this->setup_users_and_courses();

        // Create a favourites repository and favourite a course.
        $favouritesrepo = new favourites_repository($user1context);

        $favcourse = (object)[
            'component' => 'core_course',
            'itemtype' => 'course',
            'itemid' => $course1context->instanceid
        ];

        $this->expectException('moodle_exception');
        $favouritesrepo->add($favcourse);
    }

    public function test_add_all_basic() {
        list($user1context, $user2context, $course1context, $course2context) = $this->setup_users_and_courses();

        // Create a favourites repository and favourite several courses.
        $favouritesrepo = new favourites_repository($user1context);
        $favcourses = [];

        $favcourses[] = (object)[
            'userid' => $user1context->instanceid,
            'component' => 'core_course',
            'itemtype' => 'course',
            'itemid' => $course1context->instanceid,
            'contextid' => $course1context->id,
        ];
        $favcourses[] = (object)[
            'userid' => $user1context->instanceid,
            'component' => 'core_course',
            'itemtype' => 'course',
            'itemid' => $course2context->instanceid,
            'contextid' => $course2context->id,
        ];
        $timenow = time(); // Reference only, to check that the created item has a time equal to or greater than this.
        $favourites = $favouritesrepo->add_all($favcourses);

        $this->assertInternalType('array', $favourites);
        $this->assertCount(2, $favourites);
        foreach ($favourites as $favourite) {
            // Verify we get the record back.
            $this->assertEquals('core_course', $favourite->component);
            $this->assertEquals('course', $favourite->itemtype);

            // Verify the returned object has additional properties, created as part of the add.
            $this->assertObjectHasAttribute('ordering', $favourite);
            $this->assertObjectHasAttribute('timecreated', $favourite);
            $this->assertGreaterThanOrEqual($timenow, $favourite->timecreated);
        }

        // Try to save the same record again and confirm the store throws an exception.
        $this->expectException('dml_write_exception');
        $favouritesrepo->add_all($favcourses);
    }

    /**
     * Tests reading from the repository by instance id.
     */
    public function test_find() {
        list($user1context, $user2context, $course1context, $course2context) = $this->setup_users_and_courses();

        // Create a favourites repository and favourite a course.
        $favouritesrepo = new favourites_repository($user1context);
        $favourite = (object) [
            'userid' => $user1context->instanceid,
            'component' => 'core_course',
            'itemtype' => 'course',
            'itemid' => $course1context->instanceid,
            'contextid' => $course1context->id
        ];
        $favourite = $favouritesrepo->add($favourite);

        // Now, from the repo, get the single favourite we just created, by id.
        $userfavourite = $favouritesrepo->find($favourite->id);
        $this->assertInstanceOf(\stdClass::class, $userfavourite);
        $this->assertObjectHasAttribute('timecreated', $userfavourite);

        // Try to get a favourite we know doesn't exist.
        // We expect an exception in this case.
        $this->expectException(dml_exception::class);
        $favouritesrepo->find(1);
    }

    /**
     * Test verifying that find_all() returns all favourites, or an empty array.
     */
    public function test_find_all() {
        list($user1context, $user2context, $course1context, $course2context) = $this->setup_users_and_courses();

        $favouritesrepo = new favourites_repository($user1context);

        // Verify that for an empty repository, find_all returns an empty array.
        $this->assertEquals([], $favouritesrepo->find_all());

        // Save a favourite for 2 courses, in different areas.
        $favourite = (object) [
            'userid' => $user1context->instanceid,
            'component' => 'core_course',
            'itemtype' => 'course',
            'itemid' => $course1context->instanceid,
            'contextid' => $course1context->id
        ];
        $favourite2 = (object) [
            'userid' => $user1context->instanceid,
            'component' => 'core_course',
            'itemtype' => 'anothertype',
            'itemid' => $course2context->instanceid,
            'contextid' => $course2context->id
        ];
        $favouritesrepo->add($favourite);
        $favouritesrepo->add($favourite2);

        // Verify that find_all returns both of our favourites.
        $favourites = $favouritesrepo->find_all();
        $this->assertCount(2, $favourites);
        foreach ($favourites as $fav) {
            $this->assertObjectHasAttribute('id', $fav);
            $this->assertObjectHasAttribute('timecreated', $fav);
        }
    }

    /**
     * Test retrieval of a user's favourites for a given criteria, in this case, area.
     */
    public function test_find_by() {
        list($user1context, $user2context, $course1context, $course2context) = $this->setup_users_and_courses();

        // Create a favourites repository and favourite a course.
        $favouritesrepo = new favourites_repository($user1context);
        $favourite = (object) [
            'userid' => $user1context->instanceid,
            'component' => 'core_course',
            'itemtype' => 'course',
            'itemid' => $course1context->instanceid,
            'contextid' => $course1context->id
        ];
        $favouritesrepo->add($favourite);

        // From the repo, get the list of favourites for the 'core_course/course' area.
        $userfavourites = $favouritesrepo->find_by(['component' => 'core_course', 'itemtype' => 'course']);
        $this->assertInternalType('array', $userfavourites);
        $this->assertCount(1, $userfavourites);

        // Try to get a list of favourites for a non-existent area.
        $userfavourites = $favouritesrepo->find_by(['component' => 'core_cannibalism', 'itemtype' => 'course']);
        $this->assertInternalType('array', $userfavourites);
        $this->assertCount(0, $userfavourites);
    }

    /**
     * Test the count_by() method.
     */
    public function test_count_by() {
        list($user1context, $user2context, $course1context, $course2context) = $this->setup_users_and_courses();

        // Create a favourites repository and add 2 favourites in different areas.
        $favouritesrepo = new favourites_repository($user1context);
        $favourite = (object) [
            'userid' => $user1context->instanceid,
            'component' => 'core_course',
            'itemtype' => 'course',
            'itemid' => $course1context->instanceid,
            'contextid' => $course1context->id
        ];
        $favourite2 = (object) [
            'userid' => $user1context->instanceid,
            'component' => 'core_course',
            'itemtype' => 'anothertype',
            'itemid' => $course2context->instanceid,
            'contextid' => $course2context->id
        ];
        $favouritesrepo->add($favourite);
        $favouritesrepo->add($favourite2);

        // Verify counts can be restricted by criteria.
        $this->assertEquals(1, $favouritesrepo->count_by(['userid' => $user1context->instanceid, 'component' => 'core_course',
                'itemtype' => 'course']));
        $this->assertEquals(1, $favouritesrepo->count_by(['userid' => $user1context->instanceid, 'component' => 'core_course',
            'itemtype' => 'anothertype']));
        $this->assertEquals(0, $favouritesrepo->count_by(['userid' => $user1context->instanceid, 'component' => 'core_course',
            'itemtype' => 'nonexistenttype']));
    }

    public function test_exists() {
        list($user1context, $user2context, $course1context, $course2context) = $this->setup_users_and_courses();

        // Create a favourites repository and favourite a course.
        $favouritesrepo = new favourites_repository($user1context);
        $favourite = (object) [
            'userid' => $user1context->instanceid,
            'component' => 'core_course',
            'itemtype' => 'course',
            'itemid' => $course1context->instanceid,
            'contextid' => $course1context->id
        ];
        $createdfavourite = $favouritesrepo->add($favourite);

        // Verify the existence of the favourite in the repo.
        $this->assertTrue($favouritesrepo->exists($createdfavourite->id));

        // Verify exists returns false for non-existent favourite.
        $this->assertFalse($favouritesrepo->exists(1));
    }

    public function test_exists_by_area() {
        list($user1context, $user2context, $course1context, $course2context) = $this->setup_users_and_courses();

        // Create a favourites repository and favourite two courses, in different areas.
        $favouritesrepo = new favourites_repository($user1context);
        $favourite = (object) [
            'userid' => $user1context->instanceid,
            'component' => 'core_course',
            'itemtype' => 'course',
            'itemid' => $course1context->instanceid,
            'contextid' => $course1context->id
        ];
        $favourite2 = (object) [
            'userid' => $user1context->instanceid,
            'component' => 'core_course',
            'itemtype' => 'anothertype',
            'itemid' => $course2context->instanceid,
            'contextid' => $course2context->id
        ];
        $favourite1 = $favouritesrepo->add($favourite);
        $favourite2 = $favouritesrepo->add($favourite2);

        // Verify the existence of the favourites.
        $this->assertTrue($favouritesrepo->exists_by_area($user1context->instanceid, 'core_course', 'course', $favourite1->itemid,
            $favourite1->contextid));
        $this->assertTrue($favouritesrepo->exists_by_area($user1context->instanceid, 'core_course', 'anothertype',
            $favourite2->itemid, $favourite2->contextid));

        // Verify that we can't find a favourite from one area, in another.
        $this->assertFalse($favouritesrepo->exists_by_area($user1context->instanceid, 'core_course', 'anothertype',
            $favourite1->itemid, $favourite1->contextid));
    }

    /**
     * Test the update() method, by simulating a user changing the ordering of a favourite.
     */
    public function test_update() {
        list($user1context, $user2context, $course1context, $course2context) = $this->setup_users_and_courses();

        // Create a favourites repository and favourite a course.
        $favouritesrepo = new favourites_repository($user1context);
        $favourite = (object) [
            'userid' => $user1context->instanceid,
            'component' => 'core_course',
            'itemtype' => 'course',
            'itemid' => $course1context->instanceid,
            'contextid' => $course1context->id
        ];
        $favourite1 = $favouritesrepo->add($favourite);

        // Verify we can update the ordering for 2 favourites.
        $favourite1->ordering = 1;
        $favourite1 = $favouritesrepo->update($favourite1);
        $this->assertInstanceOf(stdClass::class, $favourite1);
        $this->assertAttributeEquals('1', 'ordering', $favourite1);
    }

    public function test_delete() {
        list($user1context, $user2context, $course1context, $course2context) = $this->setup_users_and_courses();

        // Create a favourites repository and favourite a course.
        $favouritesrepo = new favourites_repository($user1context);
        $favourite = (object) [
            'userid' => $user1context->instanceid,
            'component' => 'core_course',
            'itemtype' => 'course',
            'itemid' => $course1context->instanceid,
            'contextid' => $course1context->id
        ];
        $favourite = $favouritesrepo->add($favourite);

        // Verify the existence of the favourite in the repo.
        $this->assertTrue($favouritesrepo->exists($favourite->id));

        // Now, delete the favourite and confirm it's not retrievable.
        $favouritesrepo->delete($favourite->id);
        $this->assertFalse($favouritesrepo->exists($favourite->id));
    }

    public function test_delete_by_area() {
        list($user1context, $user2context, $course1context, $course2context) = $this->setup_users_and_courses();

        // Create a favourites repository and favourite two courses, in different areas.
        $favouritesrepo = new favourites_repository($user1context);
        $favourite = (object) [
            'userid' => $user1context->instanceid,
            'component' => 'core_course',
            'itemtype' => 'course',
            'itemid' => $course1context->instanceid,
            'contextid' => $course1context->id
        ];
        $favourite2 = (object) [
            'userid' => $user1context->instanceid,
            'component' => 'core_course',
            'itemtype' => 'anothertype',
            'itemid' => $course2context->instanceid,
            'contextid' => $course2context->id
        ];
        $favourite1 = $favouritesrepo->add($favourite);
        $favourite2 = $favouritesrepo->add($favourite2);

        // Verify we have 2 items in the repo.
        $this->assertEquals(2, $favouritesrepo->count());

        // Try to delete by a non-existent area, and confirm it doesn't remove anything.
        $favouritesrepo->delete_by_area($user1context->instanceid, 'core_course', 'donaldduck');
        $this->assertEquals(2, $favouritesrepo->count());

        // Try to delete by a non-existent area, and confirm it doesn't remove anything.
        $favouritesrepo->delete_by_area($user1context->instanceid, 'core_course', 'cat');
        $this->assertEquals(2, $favouritesrepo->count());

        // Delete by area, and confirm we have one record left, from the 'core_course/anothertype' area.
        $favouritesrepo->delete_by_area($user1context->instanceid, 'core_course', 'course');
        $this->assertEquals(1, $favouritesrepo->count());
        $this->assertFalse($favouritesrepo->exists($favourite1->id));
        $this->assertTrue($favouritesrepo->exists($favourite2->id));
    }
}
