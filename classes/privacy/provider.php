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

namespace mod_unifair\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider for mod_unifair.
 *
 * @package     mod_unifair
 * @copyright   2026 BPK PENABUR Jakarta
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    core_userlist_provider {

    /**
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('unifair_choice', [
            'uniid' => 'privacy:metadata:unifair_choice:uniid',
            'userid' => 'privacy:metadata:unifair_choice:userid',
            'timecreated' => 'privacy:metadata:unifair_choice:timecreated',
        ], 'privacy:metadata:unifair_choice');
        $collection->add_database_table('unifair_attendance', [
            'userid' => 'privacy:metadata:unifair_attendance:userid',
            'status' => 'privacy:metadata:unifair_attendance:status',
            'timemodified' => 'privacy:metadata:unifair_attendance:timemodified',
            'modifiedby' => 'privacy:metadata:unifair_attendance:modifiedby',
        ], 'privacy:metadata:unifair_attendance');

        return $collection;
    }

    /**
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {unifair_choice} c
                  JOIN {unifair} uf ON uf.id = c.unifairid
                  JOIN {course_modules} cm ON cm.instance = uf.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'unifair'
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel
                 WHERE c.userid = :userid";

        $contextlist->add_from_sql($sql, ['contextlevel' => CONTEXT_MODULE, 'userid' => $userid]);
        $sql = "SELECT ctx.id
                  FROM {unifair_attendance} a
                  JOIN {unifair} uf ON uf.id = a.unifairid
                  JOIN {course_modules} cm ON cm.instance = uf.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'unifair'
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel
                 WHERE a.userid = :userid OR a.modifiedby = :modifierid";
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'userid' => $userid,
            'modifierid' => $userid,
        ]);

        return $contextlist;
    }

    /**
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        $sql = "SELECT c.userid
                  FROM {course_modules} cm
                  JOIN {unifair} uf ON uf.id = cm.instance
                  JOIN {unifair_choice} c ON c.unifairid = uf.id
                 WHERE cm.id = :cmid";

        $userlist->add_from_sql('userid', $sql, ['cmid' => $context->instanceid]);
        $sql = "SELECT a.modifiedby
                  FROM {course_modules} cm
                  JOIN {unifair} uf ON uf.id = cm.instance
                  JOIN {unifair_attendance} a ON a.unifairid = uf.id
                 WHERE cm.id = :cmid AND a.modifiedby <> 0";
        $userlist->add_from_sql('modifiedby', $sql, ['cmid' => $context->instanceid]);
        $sql = "SELECT a.userid
                  FROM {course_modules} cm
                  JOIN {unifair} uf ON uf.id = cm.instance
                  JOIN {unifair_attendance} a ON a.unifairid = uf.id
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, ['cmid' => $context->instanceid]);
    }

    /**
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }

            $cm = get_coursemodule_from_id('unifair', $context->instanceid);
            if (!$cm) {
                continue;
            }

            $sql = "SELECT c.timecreated, u.uniname, s.name AS sessionname
                      FROM {unifair_choice} c
                      JOIN {unifair_uni} u ON u.id = c.uniid
                      JOIN {unifair_session} s ON s.id = u.sessionid
                     WHERE c.unifairid = :unifairid AND c.userid = :userid";

            $choices = $DB->get_records_sql($sql, ['unifairid' => $cm->instance, 'userid' => $user->id]);
            $attendance = $DB->get_records_sql(
                "SELECT a.id, a.status, a.timemodified, a.modifiedby, s.name AS sessionname
                   FROM {unifair_attendance} a
                   JOIN {unifair_session} s ON s.id = a.sessionid
                  WHERE a.unifairid = :unifairid AND a.userid = :userid",
                ['unifairid' => $cm->instance, 'userid' => $user->id]);

            $data = (object) [
                'choices' => array_values(array_map(fn($c) => [
                    'university' => $c->uniname,
                    'session' => $c->sessionname,
                    'timecreated' => \core_privacy\local\request\transform::datetime($c->timecreated),
                ], $choices)),
                'attendance' => array_values(array_map(fn($a) => [
                    'session' => $a->sessionname,
                    'status' => $a->status,
                    'timemodified' => \core_privacy\local\request\transform::datetime($a->timemodified),
                    'modifiedby' => $a->modifiedby,
                ], $attendance)),
                // Do not expose other students' attendance details. For staff,
                // export only a summary of their own audit footprint.
                'attendance_updates_made' => $DB->count_records('unifair_attendance', [
                    'unifairid' => $cm->instance,
                    'modifiedby' => $user->id,
                ]),
            ];

            writer::with_context($context)->export_data([get_string('pluginname', 'mod_unifair')], $data);
        }
    }

    /**
     * @param \context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        $cm = get_coursemodule_from_id('unifair', $context->instanceid);
        if (!$cm) {
            return;
        }

        $DB->delete_records('unifair_choice', ['unifairid' => $cm->instance]);
        $DB->delete_records('unifair_attendance', ['unifairid' => $cm->instance]);
        $DB->execute("UPDATE {unifair_uni} SET quotaused = 0 WHERE unifairid = :id", ['id' => $cm->instance]);
    }

    /**
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }

            $cm = get_coursemodule_from_id('unifair', $context->instanceid);
            if (!$cm) {
                continue;
            }

            self::delete_user_choices($cm->instance, $user->id);
            $DB->set_field('unifair_attendance', 'modifiedby', 0,
                ['unifairid' => $cm->instance, 'modifiedby' => $user->id]);
        }
    }

    /**
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        $cm = get_coursemodule_from_id('unifair', $context->instanceid);
        if (!$cm) {
            return;
        }

        foreach ($userlist->get_userids() as $userid) {
            self::delete_user_choices($cm->instance, $userid);
            $DB->set_field('unifair_attendance', 'modifiedby', 0,
                ['unifairid' => $cm->instance, 'modifiedby' => $userid]);
        }
    }

    /**
     * Delete a single user's choices and release their reserved quota slots.
     *
     * @param int $unifairid
     * @param int $userid
     * @return void
     */
    protected static function delete_user_choices(int $unifairid, int $userid): void {
        global $DB;

        $lockfactory = \core\lock\lock_config::get_lock_factory('mod_unifair');
        $userlock = $lockfactory->get_lock('choices:' . $unifairid . ':' . $userid, 10);
        if (!$userlock) {
            throw new \moodle_exception('error_choicebusy', 'unifair');
        }
        $unilocks = [];
        try {
            $choices = $DB->get_records('unifair_choice',
                ['unifairid' => $unifairid, 'userid' => $userid]);
            $uniids = array_values(array_unique(array_map(
                static fn($choice) => (int) $choice->uniid, $choices)));
            sort($uniids, SORT_NUMERIC);
            foreach ($uniids as $uniid) {
                $lock = $lockfactory->get_lock('quota:' . $uniid, 10);
                if (!$lock) {
                    throw new \moodle_exception('error_choicebusy', 'unifair');
                }
                $unilocks[] = $lock;
            }

            $transaction = $DB->start_delegated_transaction();
            foreach ($choices as $choice) {
                $DB->execute(
                    "UPDATE {unifair_uni}
                        SET quotaused = CASE
                                            WHEN quotaused > 0 THEN quotaused - 1
                                            ELSE 0
                                        END
                      WHERE id = :id",
                    ['id' => $choice->uniid]
                );
            }

            $DB->delete_records('unifair_choice', ['unifairid' => $unifairid, 'userid' => $userid]);
            $DB->delete_records('unifair_attendance', ['unifairid' => $unifairid, 'userid' => $userid]);
            $transaction->allow_commit();
            $transaction = null;
        } catch (\Throwable $e) {
            if (isset($transaction)) {
                $transaction->rollback($e);
            }
            throw $e;
        } finally {
            foreach (array_reverse($unilocks) as $lock) {
                $lock->release();
            }
            $userlock->release();
        }
    }
}
