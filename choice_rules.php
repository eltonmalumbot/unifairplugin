<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Choice rules for configurable per-student selection targets.
 *
 * @package     mod_unifair
 * @copyright   2026 BPK PENABUR Jakarta
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Return the number of selections a student must complete.
 *
 * maxchoices = 0 preserves the session model: one choice in every session.
 * A positive value means exactly that many choices in total, while still
 * allowing at most one choice per session.
 *
 * @param stdClass $unifair
 * @param int|null $sessioncount
 * @return int
 */
function unifair_required_choice_count(stdClass $unifair, ?int $sessioncount = null): int {
    global $DB;

    $configured = max(0, (int) ($unifair->maxchoices ?? 0));
    if ($configured > 0) {
        return $configured;
    }

    if ($sessioncount === null) {
        $sessioncount = $DB->count_records('unifair_session', ['unifairid' => $unifair->id]);
    }
    return max(0, $sessioncount);
}

/**
 * Apply student choices while enforcing the configured total-choice target.
 *
 * This intentionally mirrors the production-safe quota/locking behaviour of
 * unifair_apply_choices(), but supports a positive maxchoices value that may
 * be lower than the total number of sessions (for example 4 choices across an
 * 8-session, two-day event).
 *
 * @param stdClass $unifair
 * @param int $userid
 * @param int[] $requesteduniids
 * @param int[]|null $targetsessionids sessions being changed, or all sessions
 * @param bool $allowchanges whether existing saved choices may be replaced
 * @param bool $requireexacttotal require the resulting total to equal maxchoices
 * @return array{added: int[], failed: int[], removed: int[]}
 */
function unifair_apply_choices_with_limit(stdClass $unifair, int $userid, array $requesteduniids,
        ?array $targetsessionids = null, bool $allowchanges = true,
        bool $requireexacttotal = false): array {
    global $DB;

    $requesteduniids = array_values(array_unique(array_map('intval', $requesteduniids)));
    $configuredlimit = max(0, (int) ($unifair->maxchoices ?? 0));

    $sessions = $DB->get_records('unifair_session', ['unifairid' => $unifair->id], '', 'id');
    $allsessionids = array_map('intval', array_keys($sessions));
    if ($targetsessionids === null) {
        $targetsessionids = $allsessionids;
    } else {
        $targetsessionids = array_values(array_unique(array_map('intval', $targetsessionids)));
        if (array_diff($targetsessionids, $allsessionids)) {
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
            throw new moodle_exception('error_onepersession', 'unifair');
        }
        $bysession[$sessionid] = $uniid;
    }

    if ($configuredlimit === 0) {
        // Legacy/session mode: every targeted session must have exactly one choice.
        if (array_diff($targetsessionids, array_map('intval', array_keys($bysession))) ||
                array_diff(array_map('intval', array_keys($bysession)), $targetsessionids)) {
            throw new moodle_exception('error_allsessionsrequired', 'unifair');
        }
    } else {
        if (count($requesteduniids) > $configuredlimit) {
            throw new moodle_exception('error_choicelimit', 'unifair', '', $configuredlimit);
        }
        if ($requireexacttotal && count($targetsessionids) > 1 && count($requesteduniids) !== $configuredlimit) {
            throw new moodle_exception('error_exactchoices', 'unifair', '', $configuredlimit);
        }
        // A per-session student submission still needs one university.
        if (count($targetsessionids) === 1 && count($requesteduniids) !== 1) {
            throw new moodle_exception('error_onepersession', 'unifair');
        }
    }

    $result = ['added' => [], 'failed' => [], 'removed' => []];
    $lockfactory = \core\lock\lock_config::get_lock_factory('mod_unifair');
    // Use the same lock key as the original choice engine so old and new code
    // paths cannot race one another during an upgrade window.
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

    if (!$allowchanges && $existinguniids && array_diff($existinguniids, $requesteduniids)) {
        $userlock->release();
        throw new moodle_exception('error_choicealreadylocked', 'unifair');
    }

    if ($configuredlimit > 0) {
        $currenttotal = $DB->count_records('unifair_choice', [
            'unifairid' => $unifair->id,
            'userid' => $userid,
        ]);
        $prospectivetotal = $currenttotal - count($existinguniids) + count($requesteduniids);
        if ($prospectivetotal > $configuredlimit) {
            $userlock->release();
            throw new moodle_exception('error_choicelimit', 'unifair', '', $configuredlimit);
        }
        if ($requireexacttotal && $prospectivetotal !== $configuredlimit) {
            $userlock->release();
            throw new moodle_exception('error_exactchoices', 'unifair', '', $configuredlimit);
        }
    }

    $toadd = array_values(array_diff($requesteduniids, $existinguniids));
    $toremove = array_values(array_diff($existinguniids, $requesteduniids));
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
            $transaction = null;
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
            'requiredchoices' => $configuredlimit,
        ]);

    return $result;
}
