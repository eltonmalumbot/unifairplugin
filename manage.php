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
 * Manage universities (add / edit / delete) for a unifair instance.
 *
 * @package     mod_unifair
 * @copyright   2026 BPK PENABUR Jakarta
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/unifair/lib.php');
require_once($CFG->dirroot . '/mod/unifair/locallib.php');

use mod_unifair\form\uni_form;

$id = optional_param('id', 0, PARAM_INT);
if (!$id) {
    $id = optional_param('cmid', 0, PARAM_INT);
}
$action = optional_param('action', 'list', PARAM_ALPHA);
$uniid = optional_param('uniid', 0, PARAM_INT);
$uniids = optional_param('uniids', '', PARAM_SEQUENCE);
$sort = optional_param('sort', 'session', PARAM_ALPHA);
$direction = optional_param('direction', 'asc', PARAM_ALPHA);

$allowedsorts = ['session', 'name', 'capacity', 'used', 'remaining', 'sortorder'];
if (!in_array($sort, $allowedsorts, true)) {
    $sort = 'session';
}
if (!in_array($direction, ['asc', 'desc'], true)) {
    $direction = 'asc';
}

if (empty($id)) {
    // A blank/zero course module id here means the link that brought the
    // user to this page did not carry the "id" query parameter through
    // (e.g. a caching layer stripped it, or the page was reached via a
    // stale bookmark). Fail with a clear, actionable message instead of
    // the generic "Can't find data record in database" error.
    throw new moodle_exception('missingparam', 'error', '', 'id');
}

$cm = get_coursemodule_from_id('unifair', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$unifair = $DB->get_record('unifair', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/unifair:manageuni', $context);

$PAGE->set_url('/mod/unifair/manage.php', ['id' => $cm->id]);
$PAGE->set_title(get_string('pluginname', 'unifair') . ' - ' . get_string('manageuni', 'unifair'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$returnurl = new moodle_url('/mod/unifair/manage.php', ['id' => $cm->id]);
$sessionrecords = unifair_sort_sessions($DB->get_records('unifair_session',
    ['unifairid' => $unifair->id], 'id ASC'));
$sessionoptions = [];
foreach ($sessionrecords as $session) {
    $sessionoptions[$session->id] = format_string($session->name);
}

// ---- Delete selected universities after explicit confirmation. ----
if ($action === 'deleteselected') {
    if (!$uniids) {
        $posteduniids = optional_param_array('selectedunis', [], PARAM_INT);
        require_sesskey();
        $uniids = implode(',', array_values(array_unique(array_map('intval', $posteduniids))));
    }

    $selectedids = array_values(array_filter(array_unique(array_map('intval', explode(',', $uniids)))));
    if (empty($selectedids)) {
        redirect($returnurl, get_string('noselectedunis', 'unifair'), null,
            \core\output\notification::NOTIFY_WARNING);
    }

    [$insql, $inparams] = $DB->get_in_or_equal($selectedids, SQL_PARAMS_NAMED);
    $inparams['unifairid'] = $unifair->id;
    $selectedunis = $DB->get_records_select('unifair_uni',
        "unifairid = :unifairid AND id $insql", $inparams, 'id ASC');
    if (empty($selectedunis)) {
        redirect($returnurl, get_string('noselectedunis', 'unifair'), null,
            \core\output\notification::NOTIFY_WARNING);
    }

    if (optional_param('confirm', 0, PARAM_BOOL) && confirm_sesskey()) {
        $lockfactory = \core\lock\lock_config::get_lock_factory('mod_unifair');
        $locks = [];
        try {
            foreach ($selectedunis as $selecteduni) {
                $lock = $lockfactory->get_lock('quota:' . $selecteduni->id, 10);
                if (!$lock) {
                    throw new moodle_exception('error_choicebusy', 'unifair');
                }
                $locks[] = $lock;
            }

            $transaction = $DB->start_delegated_transaction();
            $affected = [];
            foreach ($selectedunis as $selecteduni) {
                $userids = $DB->get_fieldset_select('unifair_choice', 'userid', 'uniid = ?', [$selecteduni->id]);
                foreach ($userids as $affecteduserid) {
                    $affected[$selecteduni->sessionid . ':' . $affecteduserid] = [
                        'sessionid' => $selecteduni->sessionid,
                        'userid' => $affecteduserid,
                    ];
                }
                $DB->delete_records('unifair_choice', ['uniid' => $selecteduni->id]);
            }

            foreach ($affected as $item) {
                $haschoiceinsession = $DB->record_exists_sql(
                    "SELECT 1
                       FROM {unifair_choice} c
                       JOIN {unifair_uni} u ON u.id = c.uniid
                      WHERE c.unifairid = :unifairid AND c.userid = :userid
                            AND u.sessionid = :sessionid",
                    [
                        'unifairid' => $unifair->id,
                        'userid' => $item['userid'],
                        'sessionid' => $item['sessionid'],
                    ]);
                if (!$haschoiceinsession) {
                    $DB->delete_records('unifair_attendance', [
                        'unifairid' => $unifair->id,
                        'sessionid' => $item['sessionid'],
                        'userid' => $item['userid'],
                    ]);
                }
            }

            foreach ($selectedunis as $selecteduni) {
                $DB->delete_records('unifair_uni', ['id' => $selecteduni->id]);
            }
            $transaction->allow_commit();
            $transaction = null;
        } catch (Throwable $e) {
            if (isset($transaction)) {
                $transaction->rollback($e);
            }
            throw $e;
        } finally {
            foreach (array_reverse($locks) as $lock) {
                $lock->release();
            }
        }

        unifair_trigger_audit_event(\mod_unifair\event\university_updated::class, $unifair,
            'deleted selected universities', null, [
                'deletedcount' => count($selectedunis),
                'universityids' => array_keys($selectedunis),
            ]);
        redirect($returnurl, get_string('selectedunisdeleted', 'unifair', count($selectedunis)), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($unifair->name) . ' - ' . get_string('deleteselectedunis', 'unifair'));
    echo $OUTPUT->confirm(
        get_string('confirmdeleteselectedunis', 'unifair', count($selectedunis)),
        new moodle_url('/mod/unifair/manage.php', [
            'id' => $cm->id,
            'action' => 'deleteselected',
            'uniids' => implode(',', array_keys($selectedunis)),
            'confirm' => 1,
            'sesskey' => sesskey(),
        ]),
        $returnurl
    );
    echo $OUTPUT->footer();
    exit;
}

// ---- Delete every university in this activity after explicit confirmation. ----
if ($action === 'deleteall') {
    $allunis = $DB->get_records('unifair_uni', ['unifairid' => $unifair->id], 'id ASC');
    if (optional_param('confirm', 0, PARAM_BOOL) && confirm_sesskey()) {
        $lockfactory = \core\lock\lock_config::get_lock_factory('mod_unifair');
        $locks = [];
        try {
            foreach ($allunis as $uni) {
                $lock = $lockfactory->get_lock('quota:' . $uni->id, 10);
                if (!$lock) {
                    throw new moodle_exception('error_choicebusy', 'unifair');
                }
                $locks[] = $lock;
            }
            $transaction = $DB->start_delegated_transaction();
            $DB->delete_records('unifair_choice', ['unifairid' => $unifair->id]);
            $DB->delete_records('unifair_attendance', ['unifairid' => $unifair->id]);
            $DB->delete_records('unifair_uni', ['unifairid' => $unifair->id]);
            // Sessions imported for these universities are part of the same
            // dataset. Remove them as well so a fresh import cannot match or
            // display stale empty sessions.
            $DB->delete_records('unifair_session', ['unifairid' => $unifair->id]);
            $transaction->allow_commit();
            $transaction = null;
        } catch (Throwable $e) {
            if (isset($transaction)) {
                $transaction->rollback($e);
            }
            throw $e;
        } finally {
            foreach (array_reverse($locks) as $lock) {
                $lock->release();
            }
        }
        unifair_trigger_audit_event(\mod_unifair\event\university_updated::class, $unifair,
        'deleted all universities and sessions', null, ['deletedcount' => count($allunis)]);
        redirect($returnurl, get_string('allunisdeleted', 'unifair', count($allunis)), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($unifair->name) . ' - ' . get_string('deleteallunis', 'unifair'));
    echo $OUTPUT->confirm(
        get_string('confirmdeleteallunis', 'unifair', count($allunis)),
        new moodle_url('/mod/unifair/manage.php', [
            'id' => $cm->id, 'action' => 'deleteall', 'confirm' => 1, 'sesskey' => sesskey(),
        ]),
        $returnurl
    );
    echo $OUTPUT->footer();
    exit;
}

// ---- Handle delete (confirmed via a separate confirmation screen). ----
if ($action === 'delete' && $uniid) {
    $uni = $DB->get_record('unifair_uni', ['id' => $uniid, 'unifairid' => $unifair->id], '*', MUST_EXIST);

    if (optional_param('confirm', 0, PARAM_INT) && confirm_sesskey()) {
        $lockfactory = \core\lock\lock_config::get_lock_factory('mod_unifair');
        $unilock = $lockfactory->get_lock('quota:' . $uniid, 10);
        if (!$unilock) {
            throw new moodle_exception('error_choicebusy', 'unifair');
        }
        try {
            $transaction = $DB->start_delegated_transaction();
            $affectedusers = $DB->get_fieldset_select('unifair_choice', 'userid', 'uniid = ?', [$uniid]);
            $DB->delete_records('unifair_choice', ['uniid' => $uniid]);
            foreach ($affectedusers as $affecteduserid) {
                $haschoiceinsession = $DB->record_exists_sql(
                    "SELECT 1
                       FROM {unifair_choice} c
                       JOIN {unifair_uni} u ON u.id = c.uniid
                      WHERE c.unifairid = :unifairid AND c.userid = :userid
                            AND u.sessionid = :sessionid",
                    [
                        'unifairid' => $unifair->id,
                        'userid' => $affecteduserid,
                        'sessionid' => $uni->sessionid,
                    ]);
                if (!$haschoiceinsession) {
                    $DB->delete_records('unifair_attendance', [
                        'unifairid' => $unifair->id,
                        'sessionid' => $uni->sessionid,
                        'userid' => $affecteduserid,
                    ]);
                }
            }
            $DB->delete_records('unifair_uni', ['id' => $uniid]);
            $transaction->allow_commit();
            $transaction = null;
        } catch (Throwable $e) {
            if (isset($transaction)) {
                $transaction->rollback($e);
            }
            throw $e;
        } finally {
            $unilock->release();
        }

        unifair_trigger_audit_event(\mod_unifair\event\university_updated::class, $unifair,
            'deleted a university', null, ['universityid' => $uniid]);

        redirect($returnurl, get_string('unideleted', 'unifair', format_string($uni->uniname)),
            null, \core\output\notification::NOTIFY_SUCCESS);
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($unifair->name) . ' - ' . get_string('manageuni', 'unifair'));
    echo $OUTPUT->confirm(
        get_string('confirmdeleteuni', 'unifair', format_string($uni->uniname)),
        new moodle_url('/mod/unifair/manage.php', [
            'id' => $cm->id, 'action' => 'delete', 'uniid' => $uniid,
            'confirm' => 1, 'sesskey' => sesskey(),
        ]),
        $returnurl
    );
    echo $OUTPUT->footer();
    exit;
}

// ---- Handle add / edit via the Forms API. ----
if ($action === 'edit' || $action === 'add') {
    if (empty($sessionoptions)) {
        redirect(new moodle_url('/mod/unifair/manage_sessions.php', ['id' => $cm->id]),
            get_string('createsessionfirst', 'unifair'), null, \core\output\notification::NOTIFY_WARNING);
    }
    $existing = null;
    if ($action === 'edit' && $uniid) {
        $existing = $DB->get_record('unifair_uni', ['id' => $uniid, 'unifairid' => $unifair->id], '*', MUST_EXIST);
    }

    $mform = new uni_form(new moodle_url('/mod/unifair/manage.php', [
        'id' => $cm->id, 'action' => $existing ? 'edit' : 'add',
    ]), ['sessions' => $sessionoptions]);

    if ($mform->is_cancelled()) {
        redirect($returnurl);
    } else if ($data = $mform->get_data()) {
        $now = time();

        if (!empty($data->id)) {
            $record = $DB->get_record('unifair_uni', ['id' => $data->id, 'unifairid' => $unifair->id], '*', MUST_EXIST);
            $lockfactory = \core\lock\lock_config::get_lock_factory('mod_unifair');
            $unilock = $lockfactory->get_lock('quota:' . $record->id, 10);
            if (!$unilock) {
                throw new moodle_exception('error_choicebusy', 'unifair');
            }
            $sessionchangeblocked = false;
            try {
                $record = $DB->get_record('unifair_uni',
                    ['id' => $data->id, 'unifairid' => $unifair->id], '*', MUST_EXIST);
                $record->uniname = $data->uniname;
                if ((int) $record->sessionid !== (int) $data->sessionid &&
                        $DB->record_exists('unifair_choice', ['uniid' => $record->id])) {
                    $sessionchangeblocked = true;
                } else {
                    $record->sessionid = (int) $data->sessionid;
                    $record->capacity = max(0, (int) $data->capacity);
                    $record->sortorder = (int) $data->sortorder;
                    $record->timemodified = $now;
                    $DB->update_record('unifair_uni', $record);
                }
            } finally {
                $unilock->release();
            }
            if ($sessionchangeblocked) {
                redirect(new moodle_url('/mod/unifair/manage.php', [
                    'id' => $cm->id, 'action' => 'edit', 'uniid' => $record->id,
                ]), get_string('sessionchangeblocked', 'unifair'), null,
                    \core\output\notification::NOTIFY_ERROR);
            }

            unifair_trigger_audit_event(\mod_unifair\event\university_updated::class, $unifair,
                'updated a university', null, ['universityid' => $record->id]);

            redirect($returnurl, get_string('unisaved', 'unifair', format_string($record->uniname)),
                null, \core\output\notification::NOTIFY_SUCCESS);
        } else {
            $record = (object) [
                'unifairid' => $unifair->id,
                'sessionid' => (int) $data->sessionid,
                'uniname' => $data->uniname,
                'capacity' => max(0, (int) $data->capacity),
                'quotaused' => 0,
                'sortorder' => (int) $data->sortorder,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $record->id = $DB->insert_record('unifair_uni', $record);

            unifair_trigger_audit_event(\mod_unifair\event\university_updated::class, $unifair,
                'created a university', null, ['universityid' => $record->id]);

            redirect($returnurl, get_string('unisaved', 'unifair', format_string($record->uniname)),
                null, \core\output\notification::NOTIFY_SUCCESS);
        }
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($unifair->name) . ' - ' .
        get_string($existing ? 'edituni' : 'adduni', 'unifair'));

    if ($existing) {
        $mform->set_data([
            'id' => $existing->id,
            'unifairid' => $unifair->id,
            'cmid' => $cm->id,
            'uniname' => $existing->uniname,
            'sessionid' => $existing->sessionid,
            'capacity' => $existing->capacity,
            'sortorder' => $existing->sortorder,
        ]);
    } else {
        $mform->set_data(['unifairid' => $unifair->id, 'cmid' => $cm->id, 'id' => 0]);
    }

    $mform->display();
    echo $OUTPUT->footer();
    exit;
}

// ---- Default: list view. ----
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($unifair->name) . ' - ' . get_string('manageuni', 'unifair'));

$sessionsurl = new moodle_url('/mod/unifair/manage_sessions.php', ['id' => $cm->id]);
echo html_writer::link($sessionsurl, get_string('managesessions', 'unifair'),
    ['class' => 'btn btn-secondary mb-3 mr-2']);
$importurl = new moodle_url('/mod/unifair/import.php', ['id' => $cm->id]);
echo html_writer::link($importurl, get_string('importdata', 'unifair'),
    ['class' => 'btn btn-secondary mb-3 mr-2']);
$addurl = new moodle_url('/mod/unifair/manage.php', ['id' => $cm->id, 'action' => 'add']);
echo html_writer::link($addurl, get_string('adduni', 'unifair'), ['class' => 'btn btn-primary mb-3']);

$unis = $DB->get_records('unifair_uni', ['unifairid' => $unifair->id], 'id ASC');
$unis = unifair_sort_universities($unis, $sessionrecords, $sort, $direction);

$sortableheading = static function(string $field, string $label) use ($cm, $sort, $direction): string {
    $isactive = $sort === $field;
    $nextdirection = $isactive && $direction === 'asc' ? 'desc' : 'asc';
    $indicator = '';
    if ($isactive) {
        $indicator = $direction === 'asc' ? ' ↑' : ' ↓';
    }
    $url = new moodle_url('/mod/unifair/manage.php', [
        'id' => $cm->id,
        'sort' => $field,
        'direction' => $nextdirection,
    ]);
    $attributes = [
        'class' => 'unifair-sort-link',
        'title' => get_string($nextdirection === 'asc' ? 'sortascending' : 'sortdescending', 'unifair', $label),
    ];
    if ($isactive) {
        $attributes['aria-current'] = 'true';
    }
    return html_writer::link($url, $label . $indicator, $attributes);
};

$table = new html_table();
$table->head = [
    html_writer::checkbox('selectallunis', 1, false, '', [
        'id' => 'unifair-select-all-unis',
        'title' => get_string('selectallunis', 'unifair'),
        'aria-label' => get_string('selectallunis', 'unifair'),
    ]),
    $sortableheading('session', get_string('session', 'unifair')),
    $sortableheading('name', get_string('uniname', 'unifair')),
    $sortableheading('capacity', get_string('capacity', 'unifair')),
    $sortableheading('used', get_string('quotaused', 'unifair')),
    $sortableheading('remaining', get_string('remaining', 'unifair')),
    $sortableheading('sortorder', get_string('sortorder', 'unifair')),
    get_string('actions', 'unifair'),
];

foreach ($unis as $uni) {
    $remaining = unifair_get_remaining($uni);

    $editurl = new moodle_url('/mod/unifair/manage.php', ['id' => $cm->id, 'action' => 'edit', 'uniid' => $uni->id]);
    $deleteurl = new moodle_url('/mod/unifair/manage.php', ['id' => $cm->id, 'action' => 'delete', 'uniid' => $uni->id]);

    $actions = html_writer::link($editurl, get_string('edit')) . ' | ' .
        html_writer::link($deleteurl, get_string('delete'));

    $table->data[] = [
        html_writer::checkbox('selectedunis[]', $uni->id, false, '', [
            'class' => 'unifair-uni-checkbox',
            'aria-label' => get_string('selectuni', 'unifair', format_string($uni->uniname)),
        ]),
        isset($sessionrecords[$uni->sessionid]) ? format_string($sessionrecords[$uni->sessionid]->name) : '-',
        format_string($uni->uniname),
        (int) $uni->capacity === 0 ? get_string('unlimited', 'unifair') : $uni->capacity,
        $uni->quotaused,
        $remaining === -1 ? get_string('unlimited', 'unifair') : $remaining,
        $uni->sortorder,
        $actions,
    ];
}

if (empty($unis)) {
    echo $OUTPUT->notification(get_string('nouniversities', 'unifair'), 'info');
} else {
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => (new moodle_url('/mod/unifair/manage.php'))->out(false),
        'id' => 'unifair-delete-selected-form',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'deleteselected']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::table($table);
    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'value' => get_string('deleteselectedunis', 'unifair'),
        'class' => 'btn btn-danger mb-3 mr-2',
    ]);
    echo html_writer::end_tag('form');

    $deleteallurl = new moodle_url('/mod/unifair/manage.php', ['id' => $cm->id, 'action' => 'deleteall']);
    echo html_writer::link($deleteallurl, get_string('deleteallunis', 'unifair'),
        ['class' => 'btn btn-danger mb-3']);

    $PAGE->requires->js_init_code("document.getElementById('unifair-select-all-unis').addEventListener('change', function() {
        document.querySelectorAll('.unifair-uni-checkbox').forEach(function(checkbox) {
            checkbox.checked = this.checked;
        }, this);
    });");
}

echo html_writer::link(new moodle_url('/mod/unifair/view.php', ['id' => $cm->id]),
    get_string('backtoactivity', 'unifair'));

echo $OUTPUT->footer();
