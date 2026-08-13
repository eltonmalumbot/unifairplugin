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
 * Backup step definition for mod_unifair.
 *
 * @package     mod_unifair
 * @copyright   2026 BPK PENABUR Jakarta
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Class backup_unifair_activity_structure_step.
 */
class backup_unifair_activity_structure_step extends backup_activity_structure_step {

    /**
     * @return backup_nested_element
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $unifair = new backup_nested_element('unifair', ['id'], [
            'name', 'intro', 'introformat', 'maxchoices',
            'timeopen', 'timeclose', 'timecreated', 'timemodified',
        ]);

        $universities = new backup_nested_element('universities');

        $sessions = new backup_nested_element('sessions');

        $session = new backup_nested_element('session', ['id'], [
            'name', 'description', 'sortorder', 'timeopen', 'timeclose', 'timecreated', 'timemodified',
        ]);

        $attendances = new backup_nested_element('attendances');
        $attendance = new backup_nested_element('attendance', ['id'], [
            'sessionid', 'userid', 'status', 'timemodified', 'modifiedby',
        ]);

        $university = new backup_nested_element('university', ['id'], [
            'sessionid', 'uniname', 'capacity', 'quotaused', 'sortorder', 'timecreated', 'timemodified',
        ]);

        $choices = new backup_nested_element('choices');

        $choice = new backup_nested_element('choice', ['id'], [
            'uniid', 'userid', 'timecreated',
        ]);

        $unifair->add_child($sessions);
        $sessions->add_child($session);
        $unifair->add_child($attendances);
        $attendances->add_child($attendance);
        $unifair->add_child($universities);
        $universities->add_child($university);

        $university->add_child($choices);
        $choices->add_child($choice);

        $unifair->set_source_table('unifair', ['id' => backup::VAR_ACTIVITYID]);
        $session->set_source_table('unifair_session', ['unifairid' => backup::VAR_PARENTID]);
        $university->set_source_table('unifair_uni', ['unifairid' => backup::VAR_PARENTID]);

        if ($userinfo) {
            $choice->set_source_table('unifair_choice', ['uniid' => backup::VAR_PARENTID]);
            $attendance->set_source_table('unifair_attendance', ['unifairid' => backup::VAR_PARENTID]);
        }

        $choice->annotate_ids('user', 'userid');
        $attendance->annotate_ids('user', 'userid');
        $attendance->annotate_ids('user', 'modifiedby');

        $unifair->annotate_files('mod_unifair', 'intro', null);

        return $this->prepare_activity_structure($unifair);
    }
}
