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
 * Manually pre-allocate users to choices.
 *
 * @package    mod_ratingallocate
 * @copyright  2021 Catalyst IT
 * @author     David Thompson <david.thompson@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once(dirname(__FILE__) . '/locallib.php');

/**
 * Provides a form to modify a single choice
 */
class preallocate_form extends moodleform {
    /** @var $ratingallocate ratingallocate */
    private $ratingallocate;


    /**
     * Constructor
     * @param mixed $url
     * @param ratingallocate $ratingallocate
     */
    public function __construct($url, ratingallocate $ratingallocate, $choice) {
        $this->ratingallocate = $ratingallocate;
        $this->choice = $choice;
        $url->params(array("action" => "preallocate_choice", "choiceid" => $choice->id));
        parent::__construct($url->out(false));
        $this->definition_after_data();
    }

    /**
     * Define form elements
     */
    public function definition() {
        global $COURSE;

        $mform = $this->_form;

        $mform->addElement('hidden', 'choiceid'); // Save the record's id.
        $mform->setType('choiceid', PARAM_TEXT);
        $mform->setDefault('choiceid', $this->choice->id);


        $element = 'userselector';
        $mform->addElement('static', $element, get_string('preallocate_selectusers', 'mod_ratingallocate'), '*** User selector here');

        $element = 'maxsize';
        $mform->addElement('static', $element, null, get_string('preallocate_maxsize', 'mod_ratingallocate', $this->choice->maxsize));

        $element = 'reason';
        $mform->addElement('text', $element, get_string('preallocate_reason', 'mod_ratingallocate'));

        $mform->addElement('static', 'reasonexplanation', null, get_string('preallocate_reasonexplanation', 'mod_ratingallocate'));

        $this->add_buttons();
    }

    public function add_buttons() {
        $mform =& $this->_form;

        $buttonarray = array();
        $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('save'));
        $buttonarray[] = &$mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $mform->closeHeaderBefore('buttonar');
    }

    /**
     * Returns the forms HTML code.
     * So we don't have to call display().
     */
    public function to_html() {
        $o = '';
        $o .= $this->_form->getValidationScript();
        $o .= $this->_form->toHtml();
        return $o;
    }

}