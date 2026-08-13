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
 * Restore task definition for mod_unifair.
 *
 * @package     mod_unifair
 * @copyright   2026 BPK PENABUR Jakarta
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/unifair/backup/moodle2/restore_unifair_stepslib.php');

/**
 * Class restore_unifair_activity_task.
 */
class restore_unifair_activity_task extends restore_activity_task {

    /**
     * @return void
     */
    protected function define_my_settings() {
        // No specific settings for this activity.
    }

    /**
     * @return void
     */
    protected function define_my_steps() {
        $this->add_step(new restore_unifair_activity_structure_step('unifair_structure', 'unifair.xml'));
    }

    /**
     * @return array
     */
    static public function define_decode_contents() {
        $contents = [];
        $contents[] = new restore_decode_content('unifair', ['intro'], 'unifair');
        return $contents;
    }

    /**
     * @return array
     */
    static public function define_decode_rules() {
        $rules = [];
        $rules[] = new restore_decode_rule('UNIFAIRVIEWBYID', '/mod/unifair/view.php?id=$1', 'course_module');
        return $rules;
    }

    /**
     * @return array
     */
    public function get_restore_log_rules() {
        return [];
    }
}
