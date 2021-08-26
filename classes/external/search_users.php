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
 * This is the external method for getting the list of users for the dialogue conversation form.
 *
 * @package    mod_ratingallocate
 * @copyright  2021 Catalyst IT
 * @author     David Thompson <davidthompson@catalyst.net.nz>
 * @author     Dan Marsden <dan@danmarsden.com> (mod_dialogue)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_ratingallocate\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

use stdClass;
use external_api;
use external_function_parameters;
use external_value;
use external_multiple_structure;
use core_user_external;
use context_course;
use moodle_exception;
use course_enrolment_manager;
use core\session\exception;

/**
 * This is the external method for getting the information needed to present an attempts report.
 *
 * @copyright  2021 Dan Marsden <dan@danmarsden.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_users extends external_api {
    /**
     * Webservice parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'courseid' => new external_value(PARAM_INT, 'course id'),
                'ratingallocateid' => new external_value(PARAM_INT, 'rating allocation record id'),
                'choiceid' => new external_value(PARAM_INT, 'choice id'),
                'search' => new external_value(PARAM_RAW, 'query'),
                'searchanywhere' => new external_value(PARAM_BOOL, 'find a match anywhere, or only at the beginning'),
                'page' => new external_value(PARAM_INT, 'Page number'),
                'perpage' => new external_value(PARAM_INT, 'Number per page'),
            ]
        );
    }

    /**
     * Look up and return user information when pre-allocating users.
     *
     * @param int $courseid Course id
     * @param string $search The query
     * @param bool $searchanywhere Match anywhere in the string
     * @param int $page Page number
     * @param int $perpage Max per page
     * @return array report data
     */
    public static function execute(int $courseid, int $ratingallocateid, int $choiceid, string $search,
        bool $searchanywhere, int $page, int $perpage): array {
        global $PAGE, $CFG, $USER, $DB;

        require_once($CFG->dirroot.'/enrol/locallib.php');
        require_once($CFG->dirroot.'/user/lib.php');

        $params = self::validate_parameters(
            self::execute_parameters(),
            [
                'courseid'       => $courseid,
                'ratingallocateid' => $ratingallocateid,
                'choiceid'       => $choiceid,
                'search'         => $search,
                'searchanywhere' => $searchanywhere,
                'page'           => $page,
                'perpage'        => $perpage
            ]
        );
        $context = context_course::instance($params['courseid']);
        try {
            self::validate_context($context);
        } catch (Exception $e) {
            $exceptionparam = new stdClass();
            $exceptionparam->message = $e->getMessage();
            $exceptionparam->courseid = $params['courseid'];
            throw new moodle_exception('errorcoursecontextnotvalid' , 'webservice', '', $exceptionparam);
        }
        course_require_view_participants($context);

        $course = get_course($params['courseid']);
        $manager = new course_enrolment_manager($PAGE, $course);

        $users = $manager->search_users($params['search'],
            $params['searchanywhere'],
            $params['page'],
            $params['perpage']);

        $results = [];
        // Add also extra user fields.
        $requiredfields = array_merge(
            ['id', 'fullname', 'profileimageurl', 'profileimageurlsmall'],
            get_extra_user_fields($context)
        );

        /* Fetch array of IDs for preallocated users to filter by, so users who
         * are already allocated in this choice can be removed from the
         * autocomplete.
         */
        $preallocations = $DB->get_records('ratingallocate_allocations', array(
            'ratingallocateid' => $ratingallocateid,
            'choiceid' => $choiceid,
            'manual' => 1,
        ));
        $preallocateduserids = array();
        foreach ($preallocations as $allocation) {
            $preallocateduserids[] = $allocation->userid;
        }

        foreach ($users['users'] as $user) {
            // Don't include logged in user as a possible user.
            if ($user->id == $USER->id) {
                continue;
            }
            if (in_array($user->id, $preallocateduserids)) {
                continue;
            }
            if ($userdetails = user_get_user_details($user, $course, $requiredfields)) {
                $results[] = $userdetails;
            }
        }
        return $results;
    }

    /**
     * Returns description of method result value
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        global $CFG;
        require_once($CFG->dirroot . '/user/externallib.php');
        return new external_multiple_structure(core_user_external::user_description());
    }
}
