<?php
namespace mod_unifair\form;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

/** Upload form for bulk session/university import. */
class import_form extends \moodleform {
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('filepicker', 'importfile', get_string('importfile', 'unifair'), null,
            ['accepted_types' => ['.csv', '.xlsx'], 'maxbytes' => 5242880]);
        $mform->addRule('importfile', null, 'required');
        $this->add_action_buttons(true, get_string('import', 'unifair'));
    }
}
