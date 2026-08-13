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
 * Library of interface functions and constants for mod_unifair.
 *
 * @package     mod_unifair
 * @copyright   2026 BPK PENABUR Jakarta
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * @param string $feature FEATURE_xx constant
 * @return mixed
 */
function unifair_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_GROUPS:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_MOD_PURPOSE:
            return defined('MOD_PURPOSE_COLLABORATION') ? MOD_PURPOSE_COLLABORATION : null;
        default:
            return null;
    }
}

/**
 * Add a new unifair instance, including bulk-parsing the "universities_text"
 * textarea (kept for backward compatibility / quick bulk setup — individual
 * universities can also be added/edited afterwards via manage.php).
 *
 * Accepted line format: "Name|Capacity" (capacity 0 or omitted = unlimited).
 * A legacy third "|1"/"|0" segment from the old use_quota field is silently
 * ignored if present, so previously-saved textarea content still parses.
 *
 * @param stdClass $unifair
 * @param mixed $mform
 * @return int
 */
function unifair_add_instance(stdClass $unifair, $mform = null) {
    global $DB;

    $unifair->timecreated = time();
    $unifair->timemodified = $unifair->timecreated;

    $text = $unifair->universities_text ?? '';
    unset($unifair->universities_text);

    $unifair->id = $DB->insert_record('unifair', $unifair);

    if (trim($text) !== '') {
        // A compatibility session is needed only when universities are entered
        // through the legacy quick-setup textarea. An empty activity must not
        // create a stray "Session 1" before spreadsheet import.
        $now = time();
        $sessionid = $DB->insert_record('unifair_session', (object) [
            'unifairid' => $unifair->id,
            'name' => get_string('defaultsessionname', 'unifair'),
            'description' => '',
            'sortorder' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $lines = explode("\n", $text);
        $sortorder = 0;
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = explode('|', $line);
            $name = trim($parts[0] ?? '');
            if ($name === '') {
                continue;
            }
            $capacity = isset($parts[1]) ? max(0, (int) trim($parts[1])) : 0;

            $sortorder++;
            $now = time();
            $uni = (object) [
                'unifairid' => $unifair->id,
                'sessionid' => $sessionid,
                'uniname' => $name,
                'capacity' => $capacity,
                'quotaused' => 0,
                'sortorder' => $sortorder,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $DB->insert_record('unifair_uni', $uni);
        }
    }

    return $unifair->id;
}

/**
 * @param stdClass $unifair
 * @param mixed $mform
 * @return bool
 */
function unifair_update_instance(stdClass $unifair, $mform = null) {
    global $DB;

    // The settings form no longer bulk-manages universities on edit (that's
    // done on manage.php now), so universities_text is only ever present
    // when this callback fires from the *creation* form. Ignore it here.
    unset($unifair->universities_text);

    $unifair->id = $unifair->instance;
    $unifair->timemodified = time();

    return $DB->update_record('unifair', $unifair);
}

/**
 * @param int $id
 * @return bool
 */
function unifair_delete_instance($id) {
    global $DB;

    if (!$DB->record_exists('unifair', ['id' => $id])) {
        return false;
    }

    $DB->delete_records('unifair_choice', ['unifairid' => $id]);
    $DB->delete_records('unifair_attendance', ['unifairid' => $id]);
    $DB->delete_records('unifair_uni', ['unifairid' => $id]);
    $DB->delete_records('unifair_session', ['unifairid' => $id]);
    $DB->delete_records('unifair', ['id' => $id]);

    return true;
}

/**
 * @param stdClass $coursemodule
 * @return cached_cm_info|null
 */
function unifair_get_coursemodule_info($coursemodule) {
    global $DB;

    $unifair = $DB->get_record('unifair', ['id' => $coursemodule->instance], 'id, name, intro, introformat');
    if (!$unifair) {
        return null;
    }

    $info = new cached_cm_info();
    $info->name = $unifair->name;

    if ($coursemodule->showdescription) {
        $info->content = format_module_intro('unifair', $unifair, $coursemodule->id, false);
    }

    return $info;
}

/**
 * Add a "Kelola Universitas" (manage universities) link to the activity
 * settings navigation, visible to users with mod/unifair:manageuni.
 *
 * @param settings_navigation $settingsnav
 * @param navigation_node $unifairnode
 * @return void
 */
function unifair_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $unifairnode) {
    global $PAGE;

    if (!$PAGE->cm) {
        return;
    }

    $context = $PAGE->cm->context;

    if (has_capability('mod/unifair:manageuni', $context)) {
        $sessionurl = new moodle_url('/mod/unifair/manage_sessions.php', ['id' => $PAGE->cm->id]);
        $unifairnode->add(get_string('managesessions', 'unifair'), $sessionurl,
            navigation_node::TYPE_SETTING, null, 'unifairmanagesessions');
        $url = new moodle_url('/mod/unifair/manage.php', ['id' => $PAGE->cm->id]);
        $unifairnode->add(get_string('manageuni', 'unifair'), $url,
            navigation_node::TYPE_SETTING, null, 'unifairmanage');
    }
}
