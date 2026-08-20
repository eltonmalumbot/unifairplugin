<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/unifair/locallib.php');
require_once($CFG->dirroot . '/mod/unifair/choice_rules.php');

$id = required_param('id', PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$cm = get_coursemodule_from_id('unifair', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$unifair = $DB->get_record('unifair', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/unifair:managechoices', $context);
$PAGE->set_url('/mod/unifair/manage_choices.php', ['id' => $cm->id, 'userid' => $userid]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('managechoices', 'unifair'));
$PAGE->set_heading(format_string($course->fullname));

$groupmode = groups_get_activity_groupmode($cm);
$groupid = groups_get_activity_group($cm, true);
$nogroupaccess = $groupmode == SEPARATEGROUPS &&
    !has_capability('moodle/site:accessallgroups', $context) && empty($groupid);
$students = $nogroupaccess ? [] : get_enrolled_users($context, 'mod/unifair:choose', $groupid,
    'u.id,u.firstname,u.lastname,u.username', 'u.lastname,u.firstname');
if ($userid && !isset($students[$userid])) {
    throw new moodle_exception('invaliduser');
}
$sessions = unifair_sort_sessions($DB->get_records('unifair_session',
    ['unifairid' => $unifair->id], 'id ASC'));
$requiredchoices = unifair_required_choice_count($unifair, count($sessions));
$hasconfiguredlimit = (int) ($unifair->maxchoices ?? 0) > 0;
$unis = $DB->get_records('unifair_uni', ['unifairid' => $unifair->id], 'sortorder,uniname');
$bysession = [];
foreach ($unis as $uni) {
    $bysession[$uni->sessionid][$uni->id] = $uni;
}

if ($userid && data_submitted()) {
    require_sesskey();
    $submitted = optional_param_array('unichoice', [], PARAM_INT);
    $choices = array_values(array_filter(array_map('intval', $submitted), static fn($id) => $id > 0));
    try {
        $result = unifair_apply_choices_with_limit($unifair, $userid, $choices,
            array_map('intval', array_keys($sessions)), true, $hasconfiguredlimit);
        if ($result['failed']) {
            redirect($PAGE->url, get_string('error_someitemsfull', 'unifair', ''), null,
                \core\output\notification::NOTIFY_ERROR);
        }
        redirect($PAGE->url, get_string('teacherchoicesaved', 'unifair'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    } catch (Throwable $e) {
        redirect($PAGE->url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managechoices', 'unifair'));
if ($groupmode) {
    groups_print_activity_menu($cm, $PAGE->url);
}
if ($hasconfiguredlimit) {
    echo $OUTPUT->notification(get_string('teacherrequiredchoicesnotice', 'unifair', $requiredchoices), 'info');
}
$menu = [0 => get_string('selectstudent', 'unifair')];
foreach ($students as $student) {
    $menu[$student->id] = fullname($student) . ' (' . $student->username . ')';
}
echo $OUTPUT->single_select(new moodle_url('/mod/unifair/manage_choices.php', ['id' => $cm->id]),
    'userid', $menu, $userid);
if ($userid) {
    $existing = $DB->get_records_menu('unifair_choice',
        ['unifairid' => $unifair->id, 'userid' => $userid], '', 'uniid,id');
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url, 'class' => 'mt-3']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    foreach ($sessions as $session) {
        echo html_writer::tag('h3', format_string($session->name), ['class' => 'h5 mt-3']);
        $sessionhaschoice = false;
        foreach ($bysession[$session->id] ?? [] as $uni) {
            if (isset($existing[$uni->id])) {
                $sessionhaschoice = true;
                break;
            }
        }
        if ($hasconfiguredlimit) {
            $noneattrs = [
                'type' => 'radio',
                'name' => 'unichoice[' . $session->id . ']',
                'value' => 0,
            ];
            if (!$sessionhaschoice) {
                $noneattrs['checked'] = 'checked';
            }
            echo html_writer::tag('label',
                html_writer::empty_tag('input', $noneattrs) . ' ' . get_string('noselectionforsession', 'unifair'),
                ['class' => 'd-block mb-2 text-muted']);
        }
        foreach ($bysession[$session->id] ?? [] as $uni) {
            $attrs = [
                'type' => 'radio',
                'name' => 'unichoice[' . $session->id . ']',
                'value' => $uni->id,
            ];
            if (!$hasconfiguredlimit) {
                $attrs['required'] = 'required';
            }
            if (isset($existing[$uni->id])) {
                $attrs['checked'] = 'checked';
            }
            $remaining = unifair_get_remaining($uni);
            if ($remaining === 0 && !isset($existing[$uni->id])) {
                $attrs['disabled'] = 'disabled';
            }
            $label = format_string($uni->uniname) . ' — ' . ($remaining < 0
                ? get_string('unlimited', 'unifair') : get_string('remainingplaces', 'unifair', $remaining));
            echo html_writer::tag('label', html_writer::empty_tag('input', $attrs) . ' ' . $label,
                ['class' => 'd-block mb-2']);
        }
        if (empty($bysession[$session->id])) {
            echo $OUTPUT->notification(get_string('sessionhasnouniversities', 'unifair'), 'warning');
        }
    }
    echo html_writer::tag('button', get_string('savechanges'), ['class' => 'btn btn-primary', 'type' => 'submit']);
    echo html_writer::end_tag('form');
}
echo $OUTPUT->footer();
