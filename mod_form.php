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
 * The definition of the mediacms configuration form.
 *
 * @package     mod_mediacms
 * @copyright   2026 JD Park, Pai-Chai University & UNSIA
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');

class mod_mediacms_mod_form extends moodleform_mod {
    public function definition_after_data() {
        parent::definition_after_data();
    }

    public function add_completion_rules() {
        $mform =& $this->_form;

        $group = [];
        $group[] =& $mform->createElement('checkbox', 'completionminviewenabled', '', get_string('completionminview', 'mediacms'));
        $group[] =& $mform->createElement('text', 'completionminview', '', array('size' => 3));
        $group[] =& $mform->createElement('static', 'completionminviewpct', '', '%');

        $mform->addGroup($group, 'completionminviewgroup', get_string('completionminview', 'mediacms'), array(' '), false);
        $mform->setType('completionminview', PARAM_INT);
        $mform->disabledIf('completionminview', 'completionminviewenabled', 'notchecked');

        return array('completionminviewgroup');
    }

    public function completion_rule_enabled($data) {
        return (!empty($data['completionminviewenabled']) && $data['completionminview'] > 0);
    }
    
    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);
        if (isset($defaultvalues['completionminview']) && $defaultvalues['completionminview'] > 0) {
            $defaultvalues['completionminviewenabled'] = 1;
        }
    }

    function definition() {
        global $CFG;

        $mform = $this->_form;

        // General settings
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        // MediaCMS specific settings
        $mform->addElement('header', 'mediacmsheader', get_string('modulename', 'mod_mediacms'));

        $mform->addElement('text', 'mediacmsurl', get_string('mediacmsurl', 'mod_mediacms'), array('size'=>'64'));
        $mform->setType('mediacmsurl', PARAM_URL);
        $mform->addRule('mediacmsurl', null, 'required', null, 'client');
        $mform->addHelpButton('mediacmsurl', 'mediacmsurl', 'mod_mediacms');

        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }
}
