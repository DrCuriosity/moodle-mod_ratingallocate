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
 * Dialogue external functions
 *
 * @package    mod_ratingallocate
 * @copyright  2021 Catalyst IT
 * @author     David Thompson <davidthompson@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$functions = [
    'mod_ratingallocate_search_users' => [
        'classname' => 'mod_ratingallocate\external\search_users',
        'classpath' => '',
        'methodname' => 'execute',
        'description' => 'Search rating allocation participants for a choice, excluding preallocations',
        'ajax' => true,
        'type' => 'read',
        'capabilities' => 'moodle/course:viewparticipants',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ],
];
