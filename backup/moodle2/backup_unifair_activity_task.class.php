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
 * Backup task definition for mod_unifair.
 *
 * @package     mod_unifair
 * @copyright   2026 BPK PENABUR Jakarta
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/unifair/backup/moodle2/backup_unifair_stepslib.php');

/**
 * Class backup_unifair_activity_task.
 */
class backup_unifair_activity_task extends backup_activity_task {

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
        $this->add_step(new backup_unifair_activity_structure_step('unifair_structure', 'unifair.xml'));
    }

    /**
     * @return array
     */
    static public function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, '/');

        $search = "/(" . $base . "\/mod\/unifair\/view.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@UNIFAIRVIEWBYID*$2@$', $content);

        return $content;
    }
}
