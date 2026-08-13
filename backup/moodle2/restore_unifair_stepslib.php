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
 * Restore step definition for mod_unifair.
 *
 * @package     mod_unifair
 * @copyright   2026 BPK PENABUR Jakarta
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Class restore_unifair_activity_structure_step.
 */
class restore_unifair_activity_structure_step extends restore_activity_structure_step {

    /** @var int fallback session for restoring pre-session backups. */
    private $fallbacksessionid = 0;

    /** @var int restored activity instance id. */
    private $unifairid = 0;

    /**
     * @return array
     */
    protected function define_structure() {
        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('unifair', '/activity/unifair');
        $paths[] = new restore_path_element('unifair_session', '/activity/unifair/sessions/session');
        $paths[] = new restore_path_element('unifair_university', '/activity/unifair/universities/university');

        if ($userinfo) {
            $paths[] = new restore_path_element('unifair_choice',
                '/activity/unifair/universities/university/choices/choice');
            $paths[] = new restore_path_element('unifair_attendance',
                '/activity/unifair/attendances/attendance');
        }

        return $this->prepare_activity_structure($paths);
    }

    /** Restore one session. */
    protected function process_unifair_session($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->unifairid = $this->get_new_parentid('unifair');
        $data->timeopen = $this->apply_date_offset($data->timeopen ?? 0);
        $data->timeclose = $this->apply_date_offset($data->timeclose ?? 0);
        $newitemid = $DB->insert_record('unifair_session', $data);
        $this->set_mapping('unifair_session', $oldid, $newitemid);
    }

    /**
     * @param array $data
     * @return void
     */
    protected function process_unifair($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->timeopen = $this->apply_date_offset($data->timeopen);
        $data->timeclose = $this->apply_date_offset($data->timeclose);

        $newitemid = $DB->insert_record('unifair', $data);
        $this->unifairid = $newitemid;
        $this->apply_activity_instance($newitemid);
    }

    /**
     * @param array $data
     * @return void
     */
    protected function process_unifair_university($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->unifairid = $this->get_new_parentid('unifair');
        $newsessionid = !empty($data->sessionid) ?
            $this->get_mappingid('unifair_session', $data->sessionid) : 0;
        if (!$newsessionid) {
            if (!$this->fallbacksessionid) {
                $now = time();
                $this->fallbacksessionid = $DB->insert_record('unifair_session', (object) [
                    'unifairid' => $data->unifairid,
                    'name' => get_string('migratedsessionname', 'unifair'),
                    'description' => '',
                    'sortorder' => 1,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ]);
            }
            $newsessionid = $this->fallbacksessionid;
        }
        $data->sessionid = $newsessionid;

        $newitemid = $DB->insert_record('unifair_uni', $data);
        $this->set_mapping('unifair_university', $oldid, $newitemid);
    }

    /**
     * @param array $data
     * @return void
     */
    protected function process_unifair_choice($data) {
        global $DB;

        $data = (object) $data;
        $data->unifairid = $this->get_new_parentid('unifair');
        $data->uniid = $this->get_new_parentid('unifair_university');
        $data->userid = $this->get_mappingid('user', $data->userid);

        if ($data->userid) {
            $DB->insert_record('unifair_choice', $data);
        }
    }

    /** Restore attendance after its user and session mappings exist. */
    protected function process_unifair_attendance($data) {
        global $DB;
        $data = (object) $data;
        $data->unifairid = $this->get_new_parentid('unifair');
        $data->sessionid = $this->get_mappingid('unifair_session', $data->sessionid);
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->modifiedby = $this->get_mappingid('user', $data->modifiedby);
        if ($data->sessionid && $data->userid) {
            $DB->insert_record('unifair_attendance', $data);
        }
    }

    /**
     * @return void
     */
    protected function after_execute() {
        global $DB;

        $unifairid = $this->unifairid;
        if (!$DB->record_exists('unifair_session', ['unifairid' => $unifairid])) {
            $now = time();
            $DB->insert_record('unifair_session', (object) [
                'unifairid' => $unifairid,
                'name' => get_string('migratedsessionname', 'unifair'),
                'description' => '',
                'sortorder' => 1,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }
        // A backup without user data must not retain stale reservation
        // counters. Recalculate from the choice rows that were actually
        // restored, which also protects partial-user restores.
        $DB->execute("UPDATE {unifair_uni}
                         SET quotaused = COALESCE((
                             SELECT COUNT(*) FROM {unifair_choice} c
                              WHERE c.uniid = {unifair_uni}.id
                         ), 0)
                       WHERE unifairid = :unifairid", ['unifairid' => $unifairid]);
        $this->add_related_files('mod_unifair', 'intro', null);
    }
}
