<?php
// This file is part of Moodle - http://moodle.org/

/** Session CRUD page for mod_unifair. */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/unifair/locallib.php');

use mod_unifair\form\session_form;

$id = required_param('id', PARAM_INT);
$action = optional_param('action', 'list', PARAM_ALPHA);
$sessionid = optional_param('sessionid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('unifair', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$unifair = $DB->get_record('unifair', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/unifair:manageuni', $context);

$PAGE->set_url('/mod/unifair/manage_sessions.php', ['id' => $cm->id]);
$PAGE->set_title(get_string('managesessions', 'unifair'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$returnurl = new moodle_url('/mod/unifair/manage_sessions.php', ['id' => $cm->id]);

if ($action === 'reorder') {
    if (!data_submitted()) {
        throw new invalid_parameter_exception('Session ordering requires a POST request.');
    }
    require_sesskey();

    $orderedids = array_values(array_unique(array_filter(array_map('intval',
        explode(',', required_param('order', PARAM_SEQUENCE))))));
    if (!$orderedids) {
        throw new invalid_parameter_exception('No sessions were supplied.');
    }

    $sessions = $DB->get_records('unifair_session', ['unifairid' => $unifair->id]);
    foreach ($orderedids as $orderedid) {
        if (!isset($sessions[$orderedid])) {
            throw new invalid_parameter_exception('A session does not belong to this activity.');
        }
    }

    // A day is represented by the session description in the import format.
    // Reordering is deliberately limited to one day so multi-day events can
    // never be mixed accidentally by a drag operation.
    $groupkey = trim((string) $sessions[$orderedids[0]]->description);
    $groupids = [];
    foreach ($sessions as $session) {
        if (trim((string) $session->description) === $groupkey) {
            $groupids[] = (int) $session->id;
        }
    }
    $submittedcheck = $orderedids;
    sort($submittedcheck, SORT_NUMERIC);
    sort($groupids, SORT_NUMERIC);
    if ($submittedcheck !== $groupids) {
        throw new invalid_parameter_exception('The submitted order must contain every session for one day.');
    }

    $lockfactory = \core\lock\lock_config::get_lock_factory('mod_unifair');
    $lock = $lockfactory->get_lock('sessionorder:' . $unifair->id . ':' . sha1($groupkey), 10);
    if (!$lock) {
        throw new moodle_exception('error_reorderbusy', 'unifair');
    }

    $transaction = null;
    try {
        $transaction = $DB->start_delegated_transaction();
        $now = time();
        foreach ($orderedids as $index => $orderedid) {
            $DB->update_record('unifair_session', (object) [
                'id' => $orderedid,
                'sortorder' => $index + 1,
                'timemodified' => $now,
            ]);
        }
        $transaction->allow_commit();
        $transaction = null;
    } catch (Throwable $e) {
        if ($transaction !== null) {
            $transaction->rollback($e);
        }
        throw $e;
    } finally {
        $lock->release();
    }

    unifair_trigger_audit_event(\mod_unifair\event\session_updated::class, $unifair,
        'reordered sessions', null, ['sessioncount' => count($orderedids)]);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'message' => get_string('sessionordersaved', 'unifair'),
    ]);
    exit;
}

if ($action === 'delete' && $sessionid) {
    $session = $DB->get_record('unifair_session',
        ['id' => $sessionid, 'unifairid' => $unifair->id], '*', MUST_EXIST);
    $unicount = $DB->count_records('unifair_uni', ['sessionid' => $session->id]);
    if ($unicount) {
        redirect($returnurl, get_string('sessiondeleteblocked', 'unifair', $unicount), null,
            \core\output\notification::NOTIFY_ERROR);
    }
    if (optional_param('confirm', 0, PARAM_INT) && confirm_sesskey()) {
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('unifair_attendance', [
            'unifairid' => $unifair->id,
            'sessionid' => $session->id,
        ]);
        $DB->delete_records('unifair_session', ['id' => $session->id]);
        $transaction->allow_commit();
        unifair_trigger_audit_event(\mod_unifair\event\session_updated::class, $unifair,
            'deleted a session', null, ['sessionid' => $session->id]);
        redirect($returnurl, get_string('sessiondeleted', 'unifair', format_string($session->name)), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('confirmdeletesession', 'unifair', format_string($session->name)),
        new moodle_url('/mod/unifair/manage_sessions.php', [
            'id' => $cm->id, 'action' => 'delete', 'sessionid' => $session->id,
            'confirm' => 1, 'sesskey' => sesskey(),
        ]),
        $returnurl
    );
    echo $OUTPUT->footer();
    exit;
}

if ($action === 'add' || $action === 'edit') {
    $existing = null;
    if ($action === 'edit') {
        $existing = $DB->get_record('unifair_session',
            ['id' => $sessionid, 'unifairid' => $unifair->id], '*', MUST_EXIST);
    }
    $formurl = new moodle_url('/mod/unifair/manage_sessions.php', [
        'id' => $cm->id, 'action' => $existing ? 'edit' : 'add',
    ]);
    $mform = new session_form($formurl);
    if ($mform->is_cancelled()) {
        redirect($returnurl);
    } else if ($data = $mform->get_data()) {
        $now = time();
        if (!empty($data->sessionid)) {
            $record = $DB->get_record('unifair_session',
                ['id' => $data->sessionid, 'unifairid' => $unifair->id], '*', MUST_EXIST);
            $record->name = trim($data->name);
            $record->description = trim($data->description ?? '');
            $record->sortorder = (int) $data->sortorder;
            $record->timeopen = (int) $data->timeopen;
            $record->timeclose = (int) $data->timeclose;
            $record->timemodified = $now;
            $DB->update_record('unifair_session', $record);
            $eventaction = 'updated a session';
        } else {
            $record = (object) [
                'unifairid' => $unifair->id,
                'name' => trim($data->name),
                'description' => trim($data->description ?? ''),
                'sortorder' => (int) $data->sortorder,
                'timeopen' => (int) $data->timeopen,
                'timeclose' => (int) $data->timeclose,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $record->id = $DB->insert_record('unifair_session', $record);
            $eventaction = 'created a session';
        }
        unifair_trigger_audit_event(\mod_unifair\event\session_updated::class, $unifair,
            $eventaction, null, ['sessionid' => $record->id]);
        redirect($returnurl, get_string('sessionconfigsaved', 'unifair', format_string($record->name)), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }
    if ($existing) {
        $formdata = clone $existing;
        $formdata->sessionid = $existing->id;
        $mform->set_data($formdata);
    }
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string($existing ? 'editsession' : 'addsession', 'unifair'));
    $mform->display();
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($unifair->name) . ' - ' . get_string('managesessions', 'unifair'));
echo html_writer::link(new moodle_url('/mod/unifair/manage_sessions.php',
    ['id' => $cm->id, 'action' => 'add']), get_string('addsession', 'unifair'),
    ['class' => 'btn btn-primary mb-3']);

$sessions = unifair_sort_sessions($DB->get_records('unifair_session',
    ['unifairid' => $unifair->id], 'id ASC'));
$table = new html_table();
$table->id = 'unifair-session-order-table';
$table->attributes['class'] = 'generaltable unifair-session-order-table';
$table->head = [get_string('reorder', 'unifair'), get_string('sessionname', 'unifair'),
    get_string('sessiondescription', 'unifair'),
    get_string('sortorder', 'unifair'), get_string('availability', 'unifair'),
    get_string('totalitems', 'unifair'), get_string('actions', 'unifair')];
foreach ($sessions as $session) {
    $editurl = new moodle_url('/mod/unifair/manage_sessions.php',
        ['id' => $cm->id, 'action' => 'edit', 'sessionid' => $session->id]);
    $deleteurl = new moodle_url('/mod/unifair/manage_sessions.php',
        ['id' => $cm->id, 'action' => 'delete', 'sessionid' => $session->id]);
    $groupkey = trim((string) $session->description);
    $handle = html_writer::tag('span', '&#x2630;', [
        'class' => 'unifair-session-drag-handle',
        'draggable' => 'true',
        'tabindex' => '0',
        'role' => 'button',
        'title' => get_string('dragsession', 'unifair'),
        'aria-label' => get_string('dragsessionname', 'unifair', format_string($session->name)),
        'data-unifair-drag-handle' => '1',
    ]);
    $row = new html_table_row([$handle, format_string($session->name), format_text($session->description,
        FORMAT_PLAIN), $session->sortorder,
        get_string('sessionwindowdisplay', 'unifair', (object) [
            'open' => $session->timeopen ? userdate($session->timeopen) : get_string('always', 'unifair'),
            'close' => $session->timeclose ? userdate($session->timeclose) : get_string('always', 'unifair'),
        ]),
        $DB->count_records('unifair_uni', ['sessionid' => $session->id]),
        html_writer::link($editurl, get_string('edit')) . ' | ' .
            html_writer::link($deleteurl, get_string('delete'))]);
    $row->attributes = [
        'data-session-id' => $session->id,
        'data-session-group' => sha1($groupkey),
    ];
    $table->data[] = $row;
}
if (empty($sessions)) {
    echo $OUTPUT->notification(get_string('nosessions', 'unifair'), 'info');
} else {
    echo html_writer::div(get_string('reorderhelp', 'unifair'), 'alert alert-info py-2');
    echo html_writer::table($table);
    echo html_writer::div('', 'small text-muted mb-3', [
        'id' => 'unifair-session-order-status',
        'role' => 'status',
        'aria-live' => 'polite',
    ]);
    $PAGE->requires->js_call_amd('mod_unifair/sessionreorder', 'init', [[
        'tableId' => $table->id,
        'statusId' => 'unifair-session-order-status',
        'url' => (new moodle_url('/mod/unifair/manage_sessions.php', ['id' => $cm->id]))->out(false),
        'sesskey' => sesskey(),
        'saving' => get_string('savingorder', 'unifair'),
        'saved' => get_string('sessionordersaved', 'unifair'),
        'error' => get_string('sessionordererror', 'unifair'),
    ]]);
}
echo html_writer::link(new moodle_url('/mod/unifair/manage.php', ['id' => $cm->id]),
    get_string('manageuni', 'unifair'));
echo $OUTPUT->footer();
