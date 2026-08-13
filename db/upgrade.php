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
 * Upgrade steps for mod_unifair.
 *
 * @package     mod_unifair
 * @copyright   2026 BPK PENABUR Jakarta
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * @param int $oldversion
 * @return bool
 */
function xmldb_unifair_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026080401) {

        // Add quotaused: an atomically-maintained reservation counter that
        // replaces the old race-prone "COUNT(*) then INSERT" quota check.
        $table = new xmldb_table('unifair_uni');
        $field = new xmldb_field('quotaused', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL, null, '0', 'capacity');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);

            // Backfill quotaused from existing choice counts so quotas
            // already in use are respected immediately after upgrade.
            $sql = "UPDATE {unifair_uni}
                       SET quotaused = COALESCE((
                           SELECT COUNT(*) FROM {unifair_choice} c WHERE c.uniid = {unifair_uni}.id
                       ), 0)";
            $DB->execute($sql);
        }

        // Add sortorder for manual reordering in the new management UI.
        $field = new xmldb_field('sortorder', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL, null, '0', 'quotaused');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add timecreated/timemodified for auditability in the edit UI.
        $field = new xmldb_field('timecreated', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL, null, '0', 'sortorder');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            $DB->execute("UPDATE {unifair_uni} SET timecreated = " . time());
        }

        $field = new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL, null, '0', 'timecreated');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            $DB->execute("UPDATE {unifair_uni} SET timemodified = " . time());
        }

        // Drop the dead 'use_quota' column: it was defined but never read
        // anywhere in the code, and its intended meaning ("0 = unlimited")
        // is now handled directly by capacity = 0.
        $field = new xmldb_field('use_quota');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Add a unique index preventing duplicate choice rows for the same
        // student + university (previously unconstrained at the DB level).
        $choicetable = new xmldb_table('unifair_choice');
        $index = new xmldb_index('uniid_userid_idx', XMLDB_INDEX_UNIQUE, ['uniid', 'userid']);
        if (!$dbman->index_exists($choicetable, $index)) {
            // Defensive de-duplication before adding the unique index, in
            // case earlier race conditions already produced duplicates.
            $dupsql = "SELECT MIN(id) as keepid, uniid, userid
                         FROM {unifair_choice}
                     GROUP BY uniid, userid
                       HAVING COUNT(*) > 1";
            $dups = $DB->get_records_sql($dupsql);
            foreach ($dups as $dup) {
                $DB->delete_records_select('unifair_choice',
                    'uniid = ? AND userid = ? AND id <> ?',
                    [$dup->uniid, $dup->userid, $dup->keepid]);
            }
            $dbman->add_index($choicetable, $index);
        }

        upgrade_mod_savepoint(true, 2026080401, 'unifair');
    }

    if ($oldversion < 2026080600) {
        $sessiontable = new xmldb_table('unifair_session');

        if (!$dbman->table_exists($sessiontable)) {
            $sessiontable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $sessiontable->add_field('unifairid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $sessiontable->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
            $sessiontable->add_field('description', XMLDB_TYPE_TEXT, null, null, null);
            $sessiontable->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $sessiontable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $sessiontable->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $sessiontable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $sessiontable->add_key('unifairid_fk', XMLDB_KEY_FOREIGN, ['unifairid'], 'unifair', ['id']);
            $sessiontable->add_index('unifairid_sortorder_idx', XMLDB_INDEX_NOTUNIQUE,
                ['unifairid', 'sortorder']);
            $dbman->create_table($sessiontable);
        }

        $unitable = new xmldb_table('unifair_uni');
        $sessionfield = new xmldb_field('sessionid', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL, null, '0', 'unifairid');
        $addsessionkey = false;
        if (!$dbman->field_exists($unitable, $sessionfield)) {
            $dbman->add_field($unitable, $sessionfield);
            $addsessionkey = true;
        }

        // Preserve every existing university and choice by assigning them to
        // one migration session per activity. No student data is deleted.
        $now = time();
        $unifairs = $DB->get_records('unifair', null, '', 'id');
        foreach ($unifairs as $unifair) {
            $sessionid = $DB->get_field('unifair_session', 'id', ['unifairid' => $unifair->id]);
            if (!$sessionid) {
                $sessionid = $DB->insert_record('unifair_session', (object) [
                    'unifairid' => $unifair->id,
                    'name' => get_string('migratedsessionname', 'unifair'),
                    'description' => '',
                    'sortorder' => 1,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ]);
            }
            $DB->set_field('unifair_uni', 'sessionid', $sessionid,
                ['unifairid' => $unifair->id, 'sessionid' => 0]);
        }

        // Reconcile counters with the preserved choice rows. This also fixes
        // counters left stale by any duplicate cleanup in older releases.
        $DB->execute("UPDATE {unifair_uni}
                         SET quotaused = COALESCE((
                             SELECT COUNT(*) FROM {unifair_choice} c
                              WHERE c.uniid = {unifair_uni}.id
                         ), 0)");

        $sessionindex = new xmldb_index('sessionid_sortorder_idx', XMLDB_INDEX_NOTUNIQUE,
            ['sessionid', 'sortorder']);
        if (!$dbman->index_exists($unitable, $sessionindex)) {
            $dbman->add_index($unitable, $sessionindex);
        }

        $sessionkey = new xmldb_key('sessionid_fk', XMLDB_KEY_FOREIGN,
            ['sessionid'], 'unifair_session', ['id']);
        if ($addsessionkey) {
            $dbman->add_key($unitable, $sessionkey);
        }

        upgrade_mod_savepoint(true, 2026080600, 'unifair');
    }

    if ($oldversion < 2026080601) {
        $sessiontable = new xmldb_table('unifair_session');
        $field = new xmldb_field('timeopen', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL, null, '0', 'sortorder');
        if (!$dbman->field_exists($sessiontable, $field)) {
            $dbman->add_field($sessiontable, $field);
        }
        $field = new xmldb_field('timeclose', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL, null, '0', 'timeopen');
        if (!$dbman->field_exists($sessiontable, $field)) {
            $dbman->add_field($sessiontable, $field);
        }

        $attendancetable = new xmldb_table('unifair_attendance');
        if (!$dbman->table_exists($attendancetable)) {
            $attendancetable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $attendancetable->add_field('unifairid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $attendancetable->add_field('sessionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $attendancetable->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $attendancetable->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'unmarked');
            $attendancetable->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $attendancetable->add_field('modifiedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $attendancetable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $attendancetable->add_key('unifairid_fk', XMLDB_KEY_FOREIGN, ['unifairid'], 'unifair', ['id']);
            $attendancetable->add_key('sessionid_fk', XMLDB_KEY_FOREIGN, ['sessionid'], 'unifair_session', ['id']);
            $attendancetable->add_key('userid_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $attendancetable->add_index('session_user_uix', XMLDB_INDEX_UNIQUE, ['sessionid', 'userid']);
            $dbman->create_table($attendancetable);
        }

        upgrade_mod_savepoint(true, 2026080601, 'unifair');
    }

    if ($oldversion < 2026080602) {
        // The unifairid foreign key already provides the required index. An
        // explicit index on the same field collides in Moodle's XMLDB layer.
        upgrade_mod_savepoint(true, 2026080602, 'unifair');
    }

    if ($oldversion < 2026080604) {
        // Code-only compatibility fix: quota release now uses portable SQL
        // instead of calling a database method that Moodle does not provide.
        upgrade_mod_savepoint(true, 2026080604, 'unifair');
    }

    if ($oldversion < 2026080606) {
        // Security and production-readiness release. Capability definitions
        // are refreshed automatically when the plugin version changes. The
        // explicit course index was removed from install.xml because the
        // foreign key already supplies it; no destructive schema change is
        // required for existing installations.
        upgrade_mod_savepoint(true, 2026080606, 'unifair');
    }

    if ($oldversion < 2026080607) {
        // Presentation-only release: quota availability is shown in blue
        // parentheses on the student choice screen.
        upgrade_mod_savepoint(true, 2026080607, 'unifair');
    }

    if ($oldversion < 2026080702) {
        // Code-only import and cleanup fix. New empty activities no longer
        // receive a default session; imported session identity now includes
        // description and sort order; delete-all also removes sessions.
        upgrade_mod_savepoint(true, 2026080702, 'unifair');
    }

    if ($oldversion < 2026080704) {
        // v3.3.3 briefly introduced separate event start/end fields. The
        // feature was withdrawn; timeopen/timeclose remain the only session
        // timing fields and continue to control the student selection window.
        $sessiontable = new xmldb_table('unifair_session');
        $field = new xmldb_field('sessionend');
        if ($dbman->field_exists($sessiontable, $field)) {
            $dbman->drop_field($sessiontable, $field);
        }
        $field = new xmldb_field('sessionstart');
        if ($dbman->field_exists($sessiontable, $field)) {
            $dbman->drop_field($sessiontable, $field);
        }

        upgrade_mod_savepoint(true, 2026080704, 'unifair');
    }

    if ($oldversion < 2026080705) {
        // Code-only release: authorised managers can persist session order
        // changes using drag-and-drop on the session management page.
        upgrade_mod_savepoint(true, 2026080705, 'unifair');
    }

    return true;
}
