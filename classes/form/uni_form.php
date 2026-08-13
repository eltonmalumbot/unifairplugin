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

namespace mod_unifair\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Add/edit form for a single university (or any other item type this
 * activity is repurposed for — workshop, seminar, etc.).
 *
 * @package     mod_unifair
 * @copyright   2026 BPK PENABUR Jakarta
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class uni_form extends \moodleform {

    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'unifairid');
        $mform->setType('unifairid', PARAM_INT);

        $mform->addElement('hidden', 'cmid');
        $mform->setType('cmid', PARAM_INT);

        $mform->addElement('text', 'uniname', get_string('uniname', 'unifair'), ['size' => '64']);
        $mform->setType('uniname', PARAM_TEXT);
        $mform->addRule('uniname', null, 'required', null, 'client');

        $sessions = $this->_customdata['sessions'] ?? [];
        $mform->addElement('select', 'sessionid', get_string('session', 'unifair'), $sessions);
        $mform->setType('sessionid', PARAM_INT);
        $mform->addRule('sessionid', null, 'required', null, 'client');

        $mform->addElement('text', 'capacity', get_string('capacity', 'unifair'), ['size' => '6']);
        $mform->setType('capacity', PARAM_INT);
        $mform->setDefault('capacity', 0);
        $mform->addRule('capacity', null, 'numeric', null, 'client');
        $mform->addHelpButton('capacity', 'capacity', 'unifair');

        $mform->addElement('text', 'sortorder', get_string('sortorder', 'unifair'), ['size' => '6']);
        $mform->setType('sortorder', PARAM_INT);
        $mform->setDefault('sortorder', 0);

        $mform->disable_form_change_checker();

        $this->add_action_buttons();
    }

    /**
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (isset($data['capacity']) && (int) $data['capacity'] < 0) {
            $errors['capacity'] = get_string('capacityvalidation', 'unifair');
        }

        if (empty($data['sessionid']) || !array_key_exists((int) $data['sessionid'],
                $this->_customdata['sessions'] ?? [])) {
            $errors['sessionid'] = get_string('invalidsession', 'unifair');
        }

        return $errors;
    }
}
