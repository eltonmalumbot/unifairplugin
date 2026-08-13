<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_unifair\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/** Add/edit form for a UniFair session. */
class session_form extends \moodleform {
    /** Define fields. */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'sessionid');
        $mform->setType('sessionid', PARAM_INT);

        $mform->addElement('text', 'name', get_string('sessionname', 'unifair'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement('textarea', 'description', get_string('sessiondescription', 'unifair'),
            ['rows' => 4, 'cols' => 64]);
        $mform->setType('description', PARAM_TEXT);

        $mform->addElement('text', 'sortorder', get_string('sortorder', 'unifair'), ['size' => 6]);
        $mform->setType('sortorder', PARAM_INT);
        $mform->setDefault('sortorder', 0);

        $mform->addElement('date_time_selector', 'timeopen', get_string('sessiontimeopen', 'unifair'),
            ['optional' => true]);
        $mform->addElement('date_time_selector', 'timeclose', get_string('sessiontimeclose', 'unifair'),
            ['optional' => true]);

        $this->add_action_buttons();
    }

    /** Validate the session availability window. */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (!empty($data['timeopen']) && !empty($data['timeclose']) && $data['timeclose'] <= $data['timeopen']) {
            $errors['timeclose'] = get_string('error_closebeforeopen', 'unifair');
        }
        return $errors;
    }
}
