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
 * Student-facing view/submission page for mod_unifair.
 *
 * @package     mod_unifair
 * @copyright   2026 BPK PENABUR Jakarta
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/unifair/lib.php');
require_once($CFG->dirroot . '/mod/unifair/locallib.php');

$id = optional_param('id', 0, PARAM_INT);
$u  = optional_param('u', 0, PARAM_INT);

if ($id) {
    $cm = get_coursemodule_from_id('unifair', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $unifair = $DB->get_record('unifair', ['id' => $cm->instance], '*', MUST_EXIST);
} else if ($u) {
    $unifair = $DB->get_record('unifair', ['id' => $u], '*', MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $unifair->course], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('unifair', $unifair->id, $course->id, false, MUST_EXIST);
} else {
    throw new moodle_exception('missingparam', 'error', '', 'id');
}

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/unifair:view', $context);

$PAGE->set_url('/mod/unifair/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($unifair->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$now = time();
$windowopen = (empty($unifair->timeopen) || $now >= $unifair->timeopen)
    && (empty($unifair->timeclose) || $now <= $unifair->timeclose);

// ---- Handle submission. ----
if (data_submitted()) {
    require_capability('mod/unifair:choose', $context);
    require_sesskey();
    if (!$windowopen) {
        redirect($PAGE->url, get_string('error_activityclosed', 'unifair'), null,
            \core\output\notification::NOTIFY_ERROR);
    }
    $targetsessionid = required_param('sessionid', PARAM_INT);
    $choice = required_param('unichoice', PARAM_INT);
    $choices = [$choice];

    $targetsession = $DB->get_record('unifair_session',
        ['id' => $targetsessionid, 'unifairid' => $unifair->id], '*', MUST_EXIST);
    $sessionopen = (empty($targetsession->timeopen) || $now >= $targetsession->timeopen)
        && (empty($targetsession->timeclose) || $now <= $targetsession->timeclose);
    if (!$sessionopen) {
        redirect($PAGE->url, get_string('error_sessionclosed', 'unifair'), null,
            \core\output\notification::NOTIFY_ERROR);
    }

    // Only consider choices that actually belong to this activity, to
    // reject any tampered/foreign uniid values from the submitted form.
    $validuniids = $DB->get_fieldset_select('unifair_uni', 'id', 'unifairid = ?', [$unifair->id]);
    if (!in_array($choice, array_map('intval', $validuniids), true)) {
        redirect($PAGE->url, get_string('error_invalidchoices', 'unifair'), null,
            \core\output\notification::NOTIFY_ERROR);
    }

    $unisessionid = (int) $DB->get_field('unifair_uni', 'sessionid', ['id' => $choice]);
    if ($unisessionid !== $targetsessionid) {
        redirect($PAGE->url, get_string('error_invalidchoices', 'unifair'), null,
            \core\output\notification::NOTIFY_ERROR);
    }

    $result = unifair_apply_choices($unifair, $USER->id, $choices, [$targetsessionid], false);

    if (!empty($result['failed'])) {
        // Fetch the names of the items that failed for a clear message.
        [$insql, $inparams] = $DB->get_in_or_equal($result['failed']);
        $failednames = $DB->get_fieldset_select('unifair_uni', 'uniname', "id $insql", $inparams);

        redirect($PAGE->url,
            get_string('error_someitemsfull', 'unifair', implode(', ', $failednames)),
            null, \core\output\notification::NOTIFY_WARNING);
    }

    redirect($PAGE->url, get_string('sessionsaved', 'unifair', format_string($targetsession->name)),
        null, \core\output\notification::NOTIFY_SUCCESS);
}

$sessions = unifair_sort_sessions($DB->get_records('unifair_session',
    ['unifairid' => $unifair->id], 'id ASC'));
$universities = $DB->get_records('unifair_uni', ['unifairid' => $unifair->id], 'sortorder ASC, uniname ASC');
$universitiesbysession = [];
foreach ($universities as $uni) {
    $universitiesbysession[$uni->sessionid][$uni->id] = $uni;
}
$userchoices = $DB->get_records_menu('unifair_choice',
    ['unifairid' => $unifair->id, 'userid' => $USER->id], '', 'uniid, id');
$lockedbysession = [];
foreach (array_keys($userchoices) as $chosenuniid) {
    if (isset($universities[$chosenuniid])) {
        $lockedbysession[(int) $universities[$chosenuniid]->sessionid] = (int) $chosenuniid;
    }
}

echo $OUTPUT->header();

$tabs = [];
$row1 = [];
$row1[] = new tabobject('view', $CFG->wwwroot . '/mod/unifair/view.php?id=' . $cm->id,
    get_string('viewtab', 'unifair'));
if (has_capability('mod/unifair:viewreport', $context)) {
    $row1[] = new tabobject('report', $CFG->wwwroot . '/mod/unifair/report.php?id=' . $cm->id,
        get_string('reporttab', 'unifair'));
}
if (has_capability('mod/unifair:manageuni', $context)) {
    $row1[] = new tabobject('manage', $CFG->wwwroot . '/mod/unifair/manage.php?id=' . $cm->id,
        get_string('manageuni', 'unifair'));
    $row1[] = new tabobject('sessions', $CFG->wwwroot . '/mod/unifair/manage_sessions.php?id=' . $cm->id,
        get_string('managesessions', 'unifair'));
}
$tabs[] = $row1;
print_tabs($tabs, 'view');

echo $OUTPUT->heading(format_string($unifair->name));

if (!empty($unifair->intro)) {
    echo $OUTPUT->box(format_module_intro('unifair', $unifair, $cm->id), 'generalbox mod_introbox');
}

if (!empty($unifair->timeopen) && $now < $unifair->timeopen) {
    echo $OUTPUT->notification(get_string('notopenyet', 'unifair', userdate($unifair->timeopen)), 'warning');
} else if (!empty($unifair->timeclose) && $now > $unifair->timeclose) {
    echo $OUTPUT->notification(get_string('alreadyclosed', 'unifair', userdate($unifair->timeclose)), 'error');
} else if (empty($sessions)) {
    echo $OUTPUT->notification(get_string('nosessions', 'unifair'), 'info');
} else if (empty($universities)) {
    echo $OUTPUT->notification(get_string('nouniversities', 'unifair'), 'info');
} else if (!has_capability('mod/unifair:choose', $context)) {
    echo $OUTPUT->notification(get_string('nopermissiontochoose', 'unifair'), 'info');
} else {
    echo html_writer::tag('p', html_writer::tag('strong', get_string('pickonepersession', 'unifair')));

    echo html_writer::start_div('unifair-university-list');

    foreach ($sessions as $session) {
        $sessionopen = (empty($session->timeopen) || $now >= $session->timeopen) &&
            (empty($session->timeclose) || $now <= $session->timeclose);
        echo html_writer::start_div('card mb-3 unifair-session');
        echo html_writer::start_div('card-body');
        echo html_writer::tag('h3', format_string($session->name), ['class' => 'h5 card-title']);
        if (!$sessionopen) {
            $status = !empty($session->timeopen) && $now < $session->timeopen
                ? get_string('sessionnotopen', 'unifair', userdate($session->timeopen))
                : get_string('sessionclosed', 'unifair', userdate($session->timeclose));
            echo $OUTPUT->notification($status, 'info');
        }
        if (!empty($session->description)) {
            echo html_writer::tag('p', format_text($session->description, FORMAT_PLAIN), ['class' => 'card-text']);
        }
        $sessionunis = $universitiesbysession[$session->id] ?? [];
        $sessionlocked = isset($lockedbysession[$session->id]);
        if (!$sessionunis) {
            echo $OUTPUT->notification(get_string('sessionhasnouniversities', 'unifair'), 'warning');
        }
        if ($sessionlocked) {
            echo $OUTPUT->notification(get_string('choicelockednotice', 'unifair'), 'success');
        }
        if ($sessionopen && $sessionunis && !$sessionlocked) {
            echo html_writer::start_tag('form', [
                'method' => 'post', 'action' => $PAGE->url, 'class' => 'unifair-choice-form']);
            echo html_writer::empty_tag('input', [
                'type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
            echo html_writer::empty_tag('input', [
                'type' => 'hidden', 'name' => 'sessionid', 'value' => $session->id]);
        }
        $alreadychecked = false;
        foreach ($sessionunis as $uni) {
            $remaining = unifair_get_remaining($uni);
            $isfull = !unifair_has_capacity($uni);
            $ischecked = !$alreadychecked && isset($userchoices[$uni->id]);
            $alreadychecked = $alreadychecked || $ischecked;
            $status = $remaining === -1
                ? html_writer::tag('span', '(' . get_string('unlimited', 'unifair') . ')',
                    ['class' => 'text-primary'])
                : ($isfull ? html_writer::tag('span', '(' . get_string('quotafullshort', 'unifair') . ')',
                    ['class' => 'text-danger'])
                    : html_writer::tag('span', '(' . get_string('remainingplaces', 'unifair', $remaining) . ')',
                        ['class' => 'text-primary']));
            $attrs = [
                'type' => 'radio', 'name' => 'unichoice',
                'value' => $uni->id, 'class' => 'form-check-input',
            ];
            if ($sessionopen) {
                $attrs['required'] = 'required';
            }
            if ($ischecked) {
                $attrs['checked'] = 'checked';
            }
            if (!$sessionopen || $sessionlocked || ($isfull && !$ischecked)) {
                $attrs['disabled'] = 'disabled';
            }
            echo html_writer::start_div('form-check mb-2 unifair-uni-row');
            echo html_writer::start_tag('label', ['class' => 'form-check-label']);
            echo html_writer::empty_tag('input', $attrs);
            echo ' ' . format_string($uni->uniname) . ' ' . $status;
            echo html_writer::end_tag('label');
            echo html_writer::end_div();
        }
        if ($sessionopen && $sessionunis && !$sessionlocked) {
            echo html_writer::tag('button', get_string('savesession', 'unifair'), [
                'type' => 'submit', 'class' => 'btn btn-primary mt-3']);
            echo html_writer::end_tag('form');
        }
        echo html_writer::end_div();
        echo html_writer::end_div();
    }

    echo html_writer::end_div();
}

echo $OUTPUT->footer();
