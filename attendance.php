<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/unifair/locallib.php');

$id = required_param('id', PARAM_INT);
$sessionid = optional_param('sessionid', 0, PARAM_INT);
$cm = get_coursemodule_from_id('unifair', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$unifair = $DB->get_record('unifair', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/unifair:viewreport', $context);
$canmanageattendance = has_capability('mod/unifair:manageattendance', $context);
$PAGE->set_url('/mod/unifair/attendance.php', ['id' => $cm->id, 'sessionid' => $sessionid]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('attendance', 'unifair'));
$PAGE->set_heading(format_string($course->fullname));
$sessions = unifair_sort_sessions($DB->get_records('unifair_session',
    ['unifairid' => $unifair->id], 'id ASC'));
if ($sessionid && !isset($sessions[$sessionid])) {
    throw new moodle_exception('invalidsession', 'unifair');
}

$groupmode = groups_get_activity_groupmode($cm);
$groupid = groups_get_activity_group($cm, true);
$nogroupaccess = $groupmode == SEPARATEGROUPS &&
    !has_capability('moodle/site:accessallgroups', $context) && empty($groupid);

$students = [];
if ($sessionid) {
    $groupjoin = '';
    $groupwhere = '';
    $groupparams = [];
    if ($nogroupaccess) {
        $groupwhere = ' AND 1 = 0';
    } else if ($groupid) {
        $groupjoin = ' JOIN {groups_members} gm ON gm.userid = u.id AND gm.groupid = :groupid';
        $groupparams['groupid'] = $groupid;
    }
    $students = $DB->get_records_sql(
        "SELECT u.id, u.firstname, u.lastname, u.username, uu.uniname
           FROM {unifair_choice} c
           JOIN {unifair_uni} uu ON uu.id = c.uniid
           JOIN {user} u ON u.id = c.userid
           $groupjoin
          WHERE c.unifairid = :unifairid AND uu.sessionid = :sessionid $groupwhere
       ORDER BY u.lastname, u.firstname",
        ['unifairid' => $unifair->id, 'sessionid' => $sessionid] + $groupparams);
}
if ($sessionid && data_submitted()) {
    require_capability('mod/unifair:manageattendance', $context);
    require_sesskey();
    $lockfactory = \core\lock\lock_config::get_lock_factory('mod_unifair');
    $attendancelock = $lockfactory->get_lock('attendance:' . $sessionid, 10);
    if (!$attendancelock) {
        throw new moodle_exception('error_attendancebusy', 'unifair');
    }
    $statuses = optional_param_array('status', [], PARAM_ALPHA);
    $allowed = ['unmarked', 'present', 'absent', 'excused'];
    try {
        $transaction = $DB->start_delegated_transaction();
        foreach ($students as $student) {
            $status = $statuses[$student->id] ?? 'unmarked';
            if (!in_array($status, $allowed, true)) {
                continue;
            }
            $record = $DB->get_record('unifair_attendance',
                ['sessionid' => $sessionid, 'userid' => $student->id]);
            $values = (object) ['unifairid' => $unifair->id, 'sessionid' => $sessionid,
                'userid' => $student->id, 'status' => $status, 'timemodified' => time(),
                'modifiedby' => $USER->id];
            if ($record) {
                $values->id = $record->id;
                $DB->update_record('unifair_attendance', $values);
            } else {
                $DB->insert_record('unifair_attendance', $values);
            }
        }
        $transaction->allow_commit();
        $transaction = null;
    } catch (Throwable $e) {
        if (isset($transaction)) {
            $transaction->rollback($e);
        }
        throw $e;
    } finally {
        $attendancelock->release();
    }
    unifair_trigger_audit_event(\mod_unifair\event\attendance_updated::class, $unifair,
        'updated attendance', null, ['sessionid' => $sessionid, 'studentcount' => count($students)]);
    redirect($PAGE->url, get_string('attendancesaved', 'unifair'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('attendance', 'unifair'));
if ($groupmode) {
    groups_print_activity_menu($cm, $PAGE->url);
}
$menu = [0 => get_string('selectsession', 'unifair')];
foreach ($sessions as $session) {
    $menu[$session->id] = format_string($session->name);
}
echo $OUTPUT->single_select(new moodle_url('/mod/unifair/attendance.php', ['id' => $cm->id]),
    'sessionid', $menu, $sessionid);
if ($sessionid) {
    $saved = $DB->get_records_menu('unifair_attendance', ['sessionid' => $sessionid], '', 'userid,status');
    if ($canmanageattendance) {
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url, 'class' => 'mt-3']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    } else {
        echo html_writer::start_div('mt-3');
    }
    $table = new html_table();
    $table->head = [get_string('fullname'), get_string('uniname', 'unifair'), get_string('status', 'unifair')];
    $options = ['unmarked' => get_string('unmarked', 'unifair'), 'present' => get_string('present', 'unifair'),
        'absent' => get_string('absent', 'unifair'), 'excused' => get_string('excused', 'unifair')];
    foreach ($students as $student) {
        $status = $saved[$student->id] ?? 'unmarked';
        $statuscell = $canmanageattendance
            ? html_writer::select($options, 'status[' . $student->id . ']', $status, false)
            : $options[$status];
        $table->data[] = [fullname($student), format_string($student->uniname), $statuscell];
    }
    echo html_writer::table($table);
    if ($canmanageattendance) {
        echo html_writer::tag('button', get_string('savechanges'),
            ['type' => 'submit', 'class' => 'btn btn-primary']);
    }
    echo $canmanageattendance ? html_writer::end_tag('form') : html_writer::end_div();
}
echo $OUTPUT->footer();
