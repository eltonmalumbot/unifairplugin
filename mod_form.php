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
 * The main mod_unifair configuration form.
 *
 * @package     mod_unifair
 * @copyright   2026 BPK PENABUR Jakarta
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Class mod_unifair_mod_form.
 */
class mod_unifair_mod_form extends moodleform_mod {

    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;
        $isupdate = !empty($this->_cm);

        $mform->addElement('text', 'name', get_string('name'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        $mform->addElement('header', 'unifairsettings', get_string('configunifair', 'unifair'));

        if (!$isupdate) {
            // Bulk quick-setup, only offered when first creating the
            // activity. Universities can be added, edited, given any
            // quota (or no quota at all), and deleted afterwards from the
            // "Kelola Universitas" management page.
            $mform->addElement('textarea', 'universities_text',
                get_string('universitiestext', 'unifair'), ['rows' => 15, 'cols' => 60]);
            $mform->setType('universities_text', PARAM_TEXT);
            $mform->addHelpButton('universities_text', 'universitiestext', 'unifair');
        } else {
            $mform->addElement('static', 'manageuninotice', '',
                get_string('manageuninotice', 'unifair'));
        }

        $mform->addElement('date_time_selector', 'timeopen', get_string('unifairopen', 'unifair'),
            ['optional' => true]);
        $mform->addElement('date_time_selector', 'timeclose', get_string('unifairclose', 'unifair'),
            ['optional' => true]);

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (!empty($data['timeopen']) && !empty($data['timeclose']) && $data['timeclose'] <= $data['timeopen']) {
            $errors['timeclose'] = get_string('timeopenclosevalidation', 'unifair');
        }

        return $errors;
    }
}
