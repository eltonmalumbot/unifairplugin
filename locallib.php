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
 * Internal helper functions for mod_unifair. Not part of the public plugin
 * API — only used by view.php, manage.php and report.php.
 *
 * @package     mod_unifair
 * @copyright   2026 BPK PENABUR Jakarta
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Sort sessions by day/description first, then by their order inside that day.
 *
 * Imported multi-day events intentionally reuse session sort orders (for
 * example 1-4 on each day). Sorting by sortorder alone interleaves the days as
 * 1, 1, 2, 2. The first-created session fixes the display position of each
 * description/day group, while sortorder controls the sequence within it.
 * Array keys are preserved so callers can still look sessions up by id.
 *
 * @param stdClass[] $sessions session records keyed by id
 * @return stdClass[] sorted session records keyed by id
 */
function unifair_sort_sessions(array $sessions): array {
    if (count($sessions) < 2) {
        return $sessions;
    }

    $byid = $sessions;
    uasort($byid, static function(stdClass $left, stdClass $right): int {
        return (int) $left->id <=> (int) $right->id;
    });

    $grouporder = [];
    foreach ($byid as $session) {
        $groupkey = trim((string) ($session->description ?? ''));
        if (!array_key_exists($groupkey, $grouporder)) {
            $grouporder[$groupkey] = count($grouporder);
        }
    }

    uasort($sessions, static function(stdClass $left, stdClass $right) use ($grouporder): int {
        $leftgroup = $grouporder[trim((string) ($left->description ?? ''))];
        $rightgroup = $grouporder[trim((string) ($right->description ?? ''))];
        $comparison = $leftgroup <=> $rightgroup;
        if ($comparison !== 0) {
            return $comparison;
        }
        $comparison = (int) $left->sortorder <=> (int) $right->sortorder;
        if ($comparison !== 0) {
            return $comparison;
        }
        $comparison = strnatcasecmp((string) $left->name, (string) $right->name);
        return $comparison !== 0 ? $comparison : ((int) $left->id <=> (int) $right->id);
    });

    return $sessions;
}

/**
 * Sort universities for the management table.
 *
 * Session sorting follows the same multi-day order used by the student view.
 * Unlimited capacities are placed after finite values in ascending order.
 * Array keys are preserved so callers may continue looking records up by id.
 *
 * @param stdClass[] $universities university records keyed by id
 * @param stdClass[] $sessions sessions already ordered by unifair_sort_sessions()
 * @param string $sort session, name, capacity, used, remaining, or sortorder
 * @param string $direction asc or desc
 * @return stdClass[] sorted university records keyed by id
 */
function unifair_sort_universities(array $universities, array $sessions,
        string $sort = 'session', string $direction = 'asc'): array {
    $allowedsorts = ['session', 'name', 'capacity', 'used', 'remaining', 'sortorder'];
    if (!in_array($sort, $allowedsorts, true)) {
        $sort = 'session';
    }
    $multiplier = $direction === 'desc' ? -1 : 1;

    $sessionranks = [];
    $rank = 0;
    foreach ($sessions as $session) {
        $sessionranks[(int) $session->id] = $rank++;
    }

    uasort($universities, static function(stdClass $left, stdClass $right) use (
            $sort, $multiplier, $sessionranks): int {
        switch ($sort) {
            case 'name':
                $comparison = strnatcasecmp((string) $left->uniname, (string) $right->uniname);
                break;
            case 'capacity':
                $leftvalue = (int) $left->capacity === 0 ? PHP_INT_MAX : (int) $left->capacity;
                $rightvalue = (int) $right->capacity === 0 ? PHP_INT_MAX : (int) $right->capacity;
                $comparison = $leftvalue <=> $rightvalue;
                break;
            case 'used':
                $comparison = (int) $left->quotaused <=> (int) $right->quotaused;
                break;
            case 'remaining':
                $leftremaining = unifair_get_remaining($left);
                $rightremaining = unifair_get_remaining($right);
                $leftvalue = $leftremaining === -1 ? PHP_INT_MAX : $leftremaining;
                $rightvalue = $rightremaining === -1 ? PHP_INT_MAX : $rightremaining;
                $comparison = $leftvalue <=> $rightvalue;
                break;
            case 'sortorder':
                $comparison = (int) $left->sortorder <=> (int) $right->sortorder;
                break;
            case 'session':
            default:
                $leftrank = $sessionranks[(int) $left->sessionid] ?? PHP_INT_MAX;
                $rightrank = $sessionranks[(int) $right->sessionid] ?? PHP_INT_MAX;
                $comparison = $leftrank <=> $rightrank;
                break;
        }

        if ($comparison !== 0) {
            return $comparison * $multiplier;
        }

        // Keep every sort deterministic. Within a session, the configured
        // university order remains the first tie-breaker.
        $comparison = (int) $left->sortorder <=> (int) $right->sortorder;
        if ($comparison !== 0) {
            return $comparison;
        }
        $comparison = strnatcasecmp((string) $left->uniname, (string) $right->uniname);
        return $comparison !== 0 ? $comparison : ((int) $left->id <=> (int) $right->id);
    });

    return $universities;
}

/**
 * Trigger a standard Moodle event so changes appear in the activity log.
 *
 * @param string $eventclass fully-qualified event class
 * @param stdClass $unifair activity record
 * @param string $action stable English action used in the log description
 * @param int|null $relateduserid affected user, when applicable
 * @param array $other additional scalar event data
 */
function unifair_trigger_audit_event(string $eventclass, stdClass $unifair, string $action,
        ?int $relateduserid = null, array $other = []): void {
    $cm = get_coursemodule_from_instance('unifair', $unifair->id, $unifair->course, false, MUST_EXIST);
    $data = [
        'objectid' => $unifair->id,
        'context' => context_module::instance($cm->id),
        'other' => ['action' => $action] + $other,
    ];
    if ($relateduserid !== null) {
        $data['relateduserid'] = $relateduserid;
    }
    $event = $eventclass::create($data);
    $event->add_record_snapshot('unifair', $unifair);
    $event->trigger();
}

/**
 * Whether a university still has room. capacity = 0 always means unlimited.
 *
 * @param stdClass $uni row from unifair_uni
 * @return bool
 */
function unifair_has_capacity(stdClass $uni): bool {
    if ((int) $uni->capacity === 0) {
        return true;
    }
    return (int) $uni->quotaused < (int) $uni->capacity;
}

/**
 * @param stdClass $uni
 * @return int remaining slots, or -1 if unlimited
 */
function unifair_get_remaining(stdClass $uni): int {
    if ((int) $uni->capacity === 0) {
        return -1;
    }
    return max(0, (int) $uni->capacity - (int) $uni->quotaused);
}

/**
 * Atomically reserve one slot on a university, only if capacity remains.
 * This replaces the old "COUNT(*) then INSERT" pattern, which allowed two
 * concurrent submissions to both pass the check and overbook a quota.
 * Must be called inside a delegated transaction alongside the choice insert.
 *
 * @param int $uniid
 * @return bool true if a slot was reserved
 */
function unifair_try_reserve_slot(int $uniid): bool {
    global $DB;

    $uni = $DB->get_record('unifair_uni', ['id' => $uniid], '*', MUST_EXIST);

    if ((int) $uni->capacity === 0) {
        // Unlimited: still increment quotaused for reporting purposes, but
        // never block on it.
        $DB->execute(
            "UPDATE {unifair_uni} SET quotaused = quotaused + 1, timemodified = :now WHERE id = :id",
            ['now' => time(), 'id' => $uniid]
        );
        return true;
    }

    $before = (int) $uni->quotaused;
    if ($before >= (int) $uni->capacity) {
        return false;
    }

    $DB->execute(
        "UPDATE {unifair_uni}
            SET quotaused = quotaused + 1, timemodified = :now
          WHERE id = :id AND quotaused < capacity",
        ['now' => time(), 'id' => $uniid]
    );

    $after = (int) $DB->get_field('unifair_uni', 'quotaused', ['id' => $uniid]);

    return $after > $before;
}

/**
 * Release a previously reserved slot (on cancellation/change of choice).
 *
 * @param int $uniid
 * @return void
 */
function unifair_release_slot(int $uniid): void {
    global $DB;

    $DB->execute(
        "UPDATE {unifair_uni}
            SET quotaused = CASE
                                 WHEN quotaused > 0 THEN quotaused - 1
                                 ELSE 0
                            END,
                timemodified = :now
          WHERE id = :id",
        ['now' => time(), 'id' => $uniid]
    );
}

/**
 * Apply a student's submitted set of choices, race-safely, without
 * destroying their previously confirmed choices if some new picks fail.
 *
 * Only universities that still have capacity are added; universities the
 * student already has and kept selected are left untouched. Anything
 * de-selected is removed and its slot released.
 *
 * @param stdClass $unifair
 * @param int $userid
 * @param int[] $requesteduniids
 * @param int[]|null $targetsessionids sessions being changed, or all sessions
 * @param bool $allowchanges whether an existing saved choice may be replaced
 * @return array{added: int[], failed: int[], removed: int[]}
 */
function unifair_apply_choices(stdClass $unifair, int $userid, array $requesteduniids,
        ?array $targetsessionids = null, bool $allowchanges = true): array {
    global $DB;

    $requesteduniids = array_values(array_unique(array_map('intval', $requesteduniids)));

    $sessions = $DB->get_records('unifair_session', ['unifairid' => $unifair->id], '', 'id');
    if ($targetsessionids === null) {
        $targetsessionids = array_map('intval', array_keys($sessions));
    } else {
        $targetsessionids = array_values(array_unique(array_map('intval', $targetsessionids)));
        if (array_diff($targetsessionids, array_map('intval', array_keys($sessions)))) {
            throw new invalid_parameter_exception('Session does not belong to this activity.');
        }
    }
    $universities = $DB->get_records('unifair_uni', ['unifairid' => $unifair->id], '', 'id, sessionid');
    $bysession = [];
    foreach ($requesteduniids as $uniid) {
        if (!isset($universities[$uniid])) {
            throw new invalid_parameter_exception('University does not belong to this activity.');
        }
        $sessionid = (int) $universities[$uniid]->sessionid;
        if (!in_array($sessionid, $targetsessionids, true)) {
            throw new invalid_parameter_exception('A submitted university belongs to a locked session.');
        }
        if (isset($bysession[$sessionid])) {
            throw new invalid_parameter_exception('Only one university may be selected per session.');
        }
        $bysession[$sessionid] = $uniid;
    }
    if (array_diff($targetsessionids, array_map('intval', array_keys($bysession))) ||
            array_diff(array_map('intval', array_keys($bysession)), $targetsessionids)) {
        throw new invalid_parameter_exception('Exactly one university must be selected in every session.');
    }

    $result = ['added' => [], 'failed' => [], 'removed' => []];

    $lockfactory = \core\lock\lock_config::get_lock_factory('mod_unifair');
    $userlock = $lockfactory->get_lock('choices:' . $unifair->id . ':' . $userid, 10);
    if (!$userlock) {
        throw new moodle_exception('error_choicebusy', 'unifair');
    }

    if (!$targetsessionids) {
        $userlock->release();
        return $result;
    }
    [$sessionsql, $sessionparams] = $DB->get_in_or_equal($targetsessionids, SQL_PARAMS_NAMED, 'ts');
    $existing = $DB->get_records_sql(
        "SELECT c.uniid, c.id
           FROM {unifair_choice} c
           JOIN {unifair_uni} u ON u.id = c.uniid
          WHERE c.unifairid = :unifairid AND c.userid = :userid
                AND u.sessionid $sessionsql",
        ['unifairid' => $unifair->id, 'userid' => $userid] + $sessionparams);
    $existinguniids = array_map('intval', array_keys($existing));
    if (!$allowchanges && $existinguniids &&
            array_diff($existinguniids, $requesteduniids)) {
        $userlock->release();
        throw new moodle_exception('error_choicealreadylocked', 'unifair');
    }
    $toadd = array_diff($requesteduniids, $existinguniids);
    $toremove = array_diff($existinguniids, $requesteduniids);

    $unilocks = [];
    $lockids = array_values(array_unique(array_merge($toadd, $toremove)));
    sort($lockids, SORT_NUMERIC);

    try {
        foreach ($lockids as $uniid) {
            $lock = $lockfactory->get_lock('quota:' . $uniid, 10);
            if (!$lock) {
                throw new moodle_exception('error_choicebusy', 'unifair');
            }
            $unilocks[] = $lock;
        }

        $transaction = $DB->start_delegated_transaction();

        $reserved = [];
        foreach ($toadd as $uniid) {
            if (unifair_try_reserve_slot($uniid)) {
                $reserved[] = $uniid;
            } else {
                $result['failed'][] = $uniid;
            }
        }

        if (!empty($result['failed'])) {
            foreach ($reserved as $uniid) {
                unifair_release_slot($uniid);
            }
            $transaction->allow_commit();
            return $result;
        }

        foreach ($toremove as $uniid) {
            if (isset($existing[$uniid])) {
                $DB->delete_records('unifair_choice', ['id' => $existing[$uniid]->id]);
                unifair_release_slot($uniid);
                $result['removed'][] = $uniid;
            }
        }
        foreach ($reserved as $uniid) {
            $DB->insert_record('unifair_choice', (object) [
                'unifairid' => $unifair->id,
                'uniid' => $uniid,
                'userid' => $userid,
                'timecreated' => time(),
            ]);
            $result['added'][] = $uniid;
        }

        $transaction->allow_commit();
        $transaction = null;
    } catch (Throwable $e) {
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

    unifair_trigger_audit_event(\mod_unifair\event\choice_updated::class, $unifair,
        'updated university choices', $userid, [
            'addedcount' => count($result['added']),
            'removedcount' => count($result['removed']),
        ]);

    return $result;
}
