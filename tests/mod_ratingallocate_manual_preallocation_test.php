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

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/generator/lib.php');
require_once(__DIR__ . '/../locallib.php');

/**
 * Tests restriction of choice availability by group membership.
 *
 * @package    mod_ratingallocate
 * @category   test
 * @group      mod_ratingallocate
 * @copyright  2021 Catalyst IT
 * @author     David Thompson <david.thompson@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_ratingallocate_manual_preallocation_testcase extends advanced_testcase {

    /**
     *  Helper function - Create a range of default choices.
     */
    private function get_choice_data() {
        $choices = array();

        $letters = range('A', 'E');
        foreach ($letters as $key => $letter) {
            $choice = array(
                'title' => "Choice $letter",
                'explanation' => "Explain Choice $letter",
                'maxsize' => 2,
                'active' => true,
            );
            $choices[] = $choice;
        }

        return $choices;
    }

    /**
     * Helper function for populating ratings.
     *
     * @param object $student
     * @param int $choiceid
     * @param int $rating
     */
    private function make_rating($student, $choiceid, $rating) {
        $ratingdata = array($choiceid => array('choiceid' => $choiceid, 'rating' => $rating));
        mod_ratingallocate_generator::save_rating_for_user($this, $this->mod, $student, $ratingdata);
    }

    /**
     * Helper function to make "user -> choice" strings out of allocations.
     *
     * This makes it easier to test that pairs exist.
     *
     * @param object[] $allocationrecords
     * @return array Strings for each allocation pair.
     */
    private function get_allocation_strings($allocationrecords) {
        $allocations = array();
        foreach ($allocationrecords as $allocid => $alloc) {
            $allocations[] = $alloc->userid . " -> " . $alloc->choiceid;
        }

        return $allocations;
    }

    /**
     * Helper function for default choice rating scenario (no manuals)
     */
    private function make_default_ratings() {
        $choicerecords = $this->ratingallocate->get_rateable_choices();
        $choiceids = array_keys($choicerecords);

        // Fill Choice A: 0, 1.
        $this->make_rating($this->students[0], $choiceids[0], 1);
        $this->make_rating($this->students[1], $choiceids[0], 1);
        // Fill Choice B: 2, 3.
        $this->make_rating($this->students[2], $choiceids[1], 1);
        $this->make_rating($this->students[3], $choiceids[1], 1);
        // Fill Choice C: 4, 5.
        $this->make_rating($this->students[4], $choiceids[2], 1);
        $this->make_rating($this->students[5], $choiceids[2], 1);

        // Student 1 cannot choose D.
        $this->make_rating($this->students[1], $choiceids[3], 0);

        // Student 6 has Choice A, but also a D alternate.
        $this->make_rating($this->students[6], $choiceids[0], 1);
        $this->make_rating($this->students[6], $choiceids[3], 1);
    }


    protected function setUp() {
        parent::setUp();

        $generator = $this->getDataGenerator();

        $course = $generator->create_course();
        $this->course = $course;
        $this->teacher = mod_ratingallocate_generator::create_user_and_enrol($this, $course, true);
        $this->setUser($this->teacher);

        $this->studentcount = 10;
        $this->students = array();
        for ($i = 0; $i < $this->studentcount; $i++) {
            $student = mod_ratingallocate_generator::create_user_and_enrol($this, $course);
            $this->students[$i] = $student;
        }

        $this->choicedata = $this->get_choice_data();

        $this->mod = mod_ratingallocate_generator::create_instance_with_choices($this, array('course' => $course),
            $this->choicedata);
        $this->ratingallocate = mod_ratingallocate_generator::get_ratingallocate_for_user($this, $this->mod, $this->teacher);
    }

    /**
     * Base assumptions.
     */
    public function test_setup() {
        $this->resetAfterTest();

        $raters = $this->ratingallocate->get_raters_in_course();
        $this->assertEquals(count($raters), count($this->students), 'All students are raters.');

        $choices = $this->ratingallocate->get_rateable_choices();
        $this->assertEquals(5, count($choices), 'Five default choices available.');

        $this->assertEquals($this->ratingallocate->get_status(), ratingallocate::DISTRIBUTION_STATUS_RATING_IN_PROGRESS,
            'Ensure rating in progress');

        $this->make_rating($this->students[0], array_keys($choices)[4], 1);
        $ratings = $this->ratingallocate->get_ratings_for_rateable_choices();
        $this->assertEquals(1, count($ratings));
        $this->ratingallocate->add_allocation(array_keys($choices)[1], 1, true, 'unit test', $this->teacher->id);
    }

    public function test_without_prealloc() {
        $this->resetAfterTest();

        $choicerecords = $this->ratingallocate->get_rateable_choices();
        $choiceids = array_keys($choicerecords);

        $this->make_default_ratings();

        $usercount = count($this->ratingallocate->get_raters_in_course());
        $ratings = $this->ratingallocate->get_ratings_for_rateable_choices();
        $this->assertEquals(9, count($ratings), 'Loaded all default ratings');

        // Test raw distribution function results.
        shuffle($ratings);
        $distributor = new solver_edmonds_karp();
        $distributions = $distributor->compute_distribution($choicerecords, $ratings, $usercount);

        $this->assertContains($this->students[0]->id, $distributions[$choiceids[0]]);
        $this->assertContains($this->students[1]->id, $distributions[$choiceids[0]]);
        $this->assertContains($this->students[2]->id, $distributions[$choiceids[1]]);
        $this->assertContains($this->students[3]->id, $distributions[$choiceids[1]]);
        $this->assertContains($this->students[4]->id, $distributions[$choiceids[2]]);
        $this->assertContains($this->students[5]->id, $distributions[$choiceids[2]]);
        $this->assertContains($this->students[6]->id, $distributions[$choiceids[3]]);

        // Test wrapped function.
        $distributor->distribute_users($this->ratingallocate);

        $allocationrecords = $this->ratingallocate->db->get_records('ratingallocate_allocations');
        $allocations = $this->get_allocation_strings($allocationrecords);

        $this->assertContains($this->students[0]->id . " -> " . $choiceids[0], $allocations);
        $this->assertContains($this->students[1]->id . " -> " . $choiceids[0], $allocations);
        $this->assertContains($this->students[2]->id . " -> " . $choiceids[1], $allocations);
        $this->assertContains($this->students[3]->id . " -> " . $choiceids[1], $allocations);
        $this->assertContains($this->students[4]->id . " -> " . $choiceids[2], $allocations);
        $this->assertContains($this->students[5]->id . " -> " . $choiceids[2], $allocations);
        $this->assertContains($this->students[6]->id . " -> " . $choiceids[3], $allocations);
    }

    public function test_get_preallocations() {
        $this->resetAfterTest();

        $choices = $this->ratingallocate->get_rateable_choices();
        // Allocate Student 0 to Choice B.
        $mychoice = array_keys($choices)[1];
        $this->ratingallocate->add_allocation($mychoice, $this->students[0]->id, true, 'unit test', $this->teacher->id);
        // Non-manual allocation, should not be returned.
        $this->ratingallocate->add_allocation(array_keys($choices)[0], $this->students[1]->id, false);

        $preallocations = $this->ratingallocate->get_manual_preallocations();
        $this->assertEquals(1, count($preallocations));

        $alloc = current($preallocations);
        $this->assertEquals(true, $alloc->manual, 'Marked as manual');
        $this->assertEquals($this->students[0]->id, $alloc->userid, 'Correct student');
        $this->assertEquals($mychoice, $alloc->choiceid, 'Correct choice');
        $this->assertEquals($this->teacher->id, $alloc->allocatorid, 'Correct allocator');
    }

    public function test_prealloc_preconditions() {
        $this->resetAfterTest();

        $choices = $this->ratingallocate->get_rateable_choices();
        $choiceids = array_keys($choices);

        $this->make_default_ratings();

        // Allocate Student 0 to Choice A.
        $mychoice = $choiceids[0];
        $this->ratingallocate->add_allocation($mychoice, $this->students[0]->id, true, 'unit test', $this->teacher->id);

        // Clear, but preserve manual allocations.
        $this->ratingallocate->clear_all_allocations(true);

        $ratings = $this->ratingallocate->get_ratings_for_rateable_choices();
        $defaultratingcount = 9;
        $this->assertEquals($defaultratingcount, count($ratings), 'All default ratings loaded.');

        /* Not shuffling ratings here, as we want to test the following case:
         * - Student 0 preallocated to Choice A.
         * - Student 1 rates A, -D
         * - Student 6 rates A, D
         *
         * Only Student 1 should be allocated to A, as the choice only has 1 'slot' left.
         */
        $distributor = new solver_edmonds_karp();

        $preallocations = $this->ratingallocate->get_manual_preallocations();
        $this->assertEquals(1, count($preallocations), 'Manual preallocation not cleared.');

        $preallocuserids = array_unique(array_map(function ($obj) { return $obj->userid; }, $preallocations));
        $this->assertTrue(in_array($this->students[0]->id, $preallocuserids), 'Student 0 is preallocated.');

        $filteredratings = $distributor->filter_by_ids($ratings, 'userid', $preallocuserids);
        $this->assertEquals($defaultratingcount - 1, count($filteredratings), '1 rating from preallocated user removed.');

        $raters = $this->ratingallocate->get_raters_in_course();
        $this->assertEquals($this->studentcount, count($raters), 'All students are raters.');

        $filteredraters = $distributor->filter_by_ids($raters, 'id', $preallocuserids);
        $this->assertEquals($this->studentcount - 1, count($filteredraters), 'Preallocated user removed from raters.');

        $this->assertEquals(2, $choices[$mychoice]->maxsize, 'Before adjustment, Choice A maxsize = 2');
        $distributor->adjust_for_preallocations($choices, $preallocations, $filteredratings);
        $this->assertEquals(1, $choices[$mychoice]->maxsize, 'After adjustment, Choice A maxsize = 1');

        $distributions = $distributor->compute_distribution($choices, $filteredratings, count($filteredraters));
        ksort($distributions);

        $this->assertNotContains($this->students[0]->id, $distributions[$choiceids[0]],
            'Preallocated choice not in computed distribution.');
        $this->assertContains($this->students[1]->id, $distributions[$choiceids[0]]);
        $this->assertNotContains($this->students[6]->id, $distributions[$choiceids[0]],
            'No overflow in choice with preallocated user.');

        // Everything else still allocated as before preallocation.
        $this->assertContains($this->students[2]->id, $distributions[$choiceids[1]]);
        $this->assertContains($this->students[3]->id, $distributions[$choiceids[1]]);
        $this->assertContains($this->students[4]->id, $distributions[$choiceids[2]]);
        $this->assertContains($this->students[5]->id, $distributions[$choiceids[2]]);
        $this->assertContains($this->students[6]->id, $distributions[$choiceids[3]]);
    }

    /**
     * Ensure that choices that are "full" from preallocation are not presented to the solver.
     */
    public function test_fill_choices() {
        $this->resetAfterTest();

        $distributor = new solver_edmonds_karp();

        $choices = $this->ratingallocate->get_rateable_choices();
        $choiceids = array_keys($choices);

        $this->make_default_ratings();

        // Pre-allocate both Student 0 and 1 to Choice A.
        $mychoice = $choiceids[0];
        $this->ratingallocate->add_allocation($mychoice, $this->students[0]->id, true, 'unit test', $this->teacher->id);
        $this->ratingallocate->add_allocation($mychoice, $this->students[1]->id, true, 'unit test', $this->teacher->id);
        $this->assertContains($mychoice, array_keys($choices), 'Filled choice exists before adjustment.');

        $preallocations = $this->ratingallocate->get_manual_preallocations();
        $preallocuserids = array_unique(array_map(function ($obj) { return $obj->userid; }, $preallocations));

        // Check with sorted ratings for unit test consistency purposes.
        $ratings = $this->ratingallocate->get_ratings_for_rateable_choices();
        $ratings = $distributor->filter_by_ids($ratings, 'userid', $preallocuserids);
        ksort($ratings);

        $distributor->adjust_for_preallocations($choices, $preallocations, $ratings);

        $this->assertNotContains($mychoice, array_keys($choices), 'Filled choice does not exist after adjustment.');

        $disribution = $distributor->distribute_users($this->ratingallocate);

        $allocationrecords = $this->ratingallocate->db->get_records('ratingallocate_allocations');
        $allocations = $this->get_allocation_strings($allocationrecords);

        $this->assertContains($this->students[0]->id . " -> " . $mychoice, $allocations,
            'Student 1 preallocated to Choice A');
        $this->assertContains($this->students[1]->id . " -> " . $mychoice, $allocations,
            'Student 2 preallocated to Choice A');
        $this->assertNotContains($this->students[6]->id . " -> " . $mychoice, $allocations,
            'Student 7 not allocated to Choice A (already full)');
        $this->assertEquals(7, count($allocations),
            'Allocations made for all students with ratings or pre-allocations.');
    }

    public function test_prealloc_execution() {
        $this->resetAfterTest();

        $choices = $this->ratingallocate->get_rateable_choices();
        $choiceids = array_keys($choices);

        $this->make_default_ratings();

        // Allocate Student 0 to Choice A.
        $mychoice = $choiceids[0];
        $this->ratingallocate->add_allocation($mychoice, $this->students[0]->id, true, 'unit test', $this->teacher->id);

        $distributor = new solver_edmonds_karp();
        $distributor->distribute_users($this->ratingallocate);

        $allocationrecords = $this->ratingallocate->db->get_records('ratingallocate_allocations', array(
            'ratingallocateid' => $this->ratingallocate->ratingallocate->id
        ));
        $allocations = $this->get_allocation_strings($allocationrecords);

        $this->assertContains($this->students[0]->id . " -> " . $mychoice, $allocations,
            'Preallocated choice in allocations.');
        $this->assertContains($this->students[1]->id . " -> " . $mychoice, $allocations);
        $this->assertNotContains($this->students[6]->id . " -> " . $mychoice, $allocations,
            'No overflow in choice with preallocated user.');

        // Everything else still allocated as before preallocation.
        $this->assertContains($this->students[2]->id . " -> " . $choiceids[1], $allocations);
        $this->assertContains($this->students[3]->id . " -> " . $choiceids[1], $allocations);
        $this->assertContains($this->students[4]->id . " -> " . $choiceids[2], $allocations);
        $this->assertContains($this->students[5]->id . " -> " . $choiceids[2], $allocations);
        $this->assertContains($this->students[6]->id . " -> " . $choiceids[3], $allocations);
    }
            /**
             * TODO: Eventually, this ought to:
             * - Once the allocations are all behaving properly, ensure that groups are created from both
             *   the pre-allocated and the solver-allocated members.
             *
             * We could probably do a render test as well, to ensure that the full pre-allocated
             */
}
