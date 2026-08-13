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
 * Reports page for mod_unifair.
 *
 * @package     mod_unifair
 * @copyright   2026 BPK PENABUR Jakarta
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/unifair/lib.php');
require_once($CFG->dirroot . '/mod/unifair/locallib.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('unifair', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$unifair = $DB->get_record('unifair', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/unifair:viewreport', $context);

$PAGE->set_url('/mod/unifair/report.php', ['id' => $cm->id]);
$PAGE->set_title(get_string('pluginname', 'unifair') . ' - ' . get_string('reporttab', 'unifair'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$groupmode = groups_get_activity_groupmode($cm);
$groupid = groups_get_activity_group($cm, true);
$nogroupaccess = $groupmode == SEPARATEGROUPS &&
    !has_capability('moodle/site:accessallgroups', $context) && empty($groupid);
$groupjoin = '';
$groupwhere = '';
$groupparams = [];
if ($nogroupaccess) {
    $groupwhere = ' AND 1 = 0';
} else if ($groupid) {
    $groupjoin = ' JOIN {groups_members} gm ON gm.userid = u.id AND gm.groupid = :groupid';
    $groupparams['groupid'] = $groupid;
}

$universities = $DB->get_records_sql(
    "SELECT uu.*, us.name AS sessionname, us.sortorder AS sessionsort
       FROM {unifair_uni} uu
       JOIN {unifair_session} us ON us.id = uu.sessionid
      WHERE uu.unifairid = :unifairid
   ORDER BY us.sortorder, us.name, uu.sortorder, uu.uniname",
    ['unifairid' => $unifair->id]
);
$sql = "SELECT uc.*, u.username, u.firstname, u.lastname, uu.uniname,
               us.id AS sessionid, us.name AS sessionname, us.sortorder AS sessionsort
          FROM {unifair_choice} uc
          JOIN {user} u ON uc.userid = u.id
          $groupjoin
          JOIN {unifair_uni} uu ON uc.uniid = uu.id
          JOIN {unifair_session} us ON uu.sessionid = us.id
         WHERE uc.unifairid = :choiceunifairid $groupwhere
      ORDER BY us.sortorder, us.name, uu.sortorder, uu.uniname, u.lastname";
$choices = $DB->get_records_sql($sql, ['choiceunifairid' => $unifair->id] + $groupparams);

$totals = [];
foreach ($universities as $uni) {
    $totals[$uni->id] = [
        'name' => $uni->uniname,
        'sessionid' => $uni->sessionid,
        'sessionname' => $uni->sessionname,
        'capacity' => (int) $uni->capacity,
        'count' => 0,
    ];
}
foreach ($choices as $choice) {
    if (isset($totals[$choice->uniid])) {
        $totals[$choice->uniid]['count']++;
    }
}

// ---- Excel export using core dataformat (real .xlsx / .csv, not a fake .xls). ----
$export = optional_param('export', '', PARAM_ALPHA);
if ($export === 'xlsx' || $export === 'csv') {
    // Moodle's core Excel dataformat is named "excel", even though the
    // downloaded file it creates uses the .xlsx extension. Passing "xlsx"
    // directly causes core\dataformat to throw an "Invalid dataformat" error.
    $dataformat = ($export === 'xlsx') ? 'excel' : 'csv';

    $columns = ['no', 'fullname', 'username', 'session', 'university', 'timecreated'];
    $rows = [];
    $no = 1;
    $safespreadsheettext = static function(string $value): string {
        return preg_match('/^[=+\-@]/u', $value) ? "'" . $value : $value;
    };
    foreach ($choices as $choice) {
        $rows[] = [
            $no++,
            $safespreadsheettext(fullname($choice)),
            $safespreadsheettext($choice->username),
            $safespreadsheettext($choice->sessionname),
            $safespreadsheettext($choice->uniname),
            userdate($choice->timecreated, '%d/%m/%Y %H:%M'),
        ];
    }

    \core\dataformat::download_data(
        'Laporan_Unifair_' . $unifair->id,
        $dataformat,
        $columns,
        $rows
    );
    exit;
}

echo $OUTPUT->header();

if ($groupmode) {
    groups_print_activity_menu($cm, $PAGE->url);
}

$tabs = [];
$row1 = [];
$row1[] = new tabobject('view', $CFG->wwwroot . '/mod/unifair/view.php?id=' . $cm->id,
    get_string('viewtab', 'unifair'));
$row1[] = new tabobject('report', $CFG->wwwroot . '/mod/unifair/report.php?id=' . $cm->id,
    get_string('reporttab', 'unifair'));
if (has_capability('mod/unifair:manageuni', $context)) {
    $row1[] = new tabobject('manage', $CFG->wwwroot . '/mod/unifair/manage.php?id=' . $cm->id,
        get_string('manageuni', 'unifair'));
    $row1[] = new tabobject('sessions', $CFG->wwwroot . '/mod/unifair/manage_sessions.php?id=' . $cm->id,
        get_string('managesessions', 'unifair'));
}
$tabs[] = $row1;
print_tabs($tabs, 'report');

echo $OUTPUT->heading(format_string($unifair->name) . ' - ' . get_string('reporttab', 'unifair'));

if (has_capability('mod/unifair:managechoices', $context)) {
    echo html_writer::link(new moodle_url('/mod/unifair/manage_choices.php', ['id' => $cm->id]),
        get_string('managechoices', 'unifair'), ['class' => 'btn btn-primary mb-3 mr-2']);
}
echo html_writer::link(new moodle_url('/mod/unifair/attendance.php', ['id' => $cm->id]),
    get_string('attendance', 'unifair'), ['class' => 'btn btn-primary mb-3 mr-2']);

$exportparams = ['id' => $cm->id, 'export' => 'xlsx'];
if ($groupid) {
    $exportparams['group'] = $groupid;
}
$exporturl = new moodle_url('/mod/unifair/report.php', $exportparams);
echo html_writer::link($exporturl, get_string('exportxlsx', 'unifair'), ['class' => 'btn btn-success mb-3']);

$enrolledstudents = $nogroupaccess ? [] : get_enrolled_users($context, 'mod/unifair:choose', $groupid,
    'u.id,u.firstname,u.lastname,u.username', 'u.lastname,u.firstname');
$sessioncount = count($DB->get_records('unifair_session', ['unifairid' => $unifair->id]));
$choicesessions = [];
foreach ($choices as $choice) {
    $choicesessions[$choice->userid][(int) $choice->sessionid] = true;
}
$incomplete = array_filter($enrolledstudents,
    static fn($student) => count($choicesessions[$student->id] ?? []) < $sessioncount);

echo $OUTPUT->box_start('infobox');
echo html_writer::tag('h3', get_string('summarystats', 'unifair'));
echo html_writer::tag('p', html_writer::tag('strong', get_string('totalitems', 'unifair')) . ': ' . count($universities));
echo html_writer::tag('p', html_writer::tag('strong', get_string('totalsessions', 'unifair')) . ': ' .
    $DB->count_records('unifair_session', ['unifairid' => $unifair->id]));
echo html_writer::tag('p', html_writer::tag('strong', get_string('totalchoices', 'unifair')) . ': ' . count($choices));
echo html_writer::tag('p', html_writer::tag('strong', get_string('totalstudents', 'unifair')) . ': ' .
    count(array_unique(array_map(static fn($choice) => $choice->userid, $choices))));
echo html_writer::tag('p', html_writer::tag('strong', get_string('incompletestudents', 'unifair')) . ': ' .
    count($incomplete));
echo $OUTPUT->box_end();

if ($incomplete) {
    echo html_writer::tag('h3', get_string('incompletestudentlist', 'unifair'));
    $items = [];
    foreach ($incomplete as $student) {
        $items[] = fullname($student) . ' (' . count($choicesessions[$student->id] ?? []) . '/' . $sessioncount . ')';
    }
    echo html_writer::alist($items);
}

echo html_writer::tag('h3', get_string('perunibreakdown', 'unifair'), ['style' => 'margin-top: 30px;']);
echo html_writer::start_div('table-responsive');
$table = new html_table();
$table->attributes['class'] = 'table table-bordered table-striped';
$table->head = [
    get_string('session', 'unifair'),
    get_string('uniname', 'unifair'),
    get_string('totalchoices', 'unifair'),
    get_string('capacity', 'unifair'),
    get_string('remaining', 'unifair'),
    get_string('percentagefull', 'unifair'),
    get_string('status', 'unifair'),
];

foreach ($totals as $uni) {
    if ($uni['capacity'] === 0) {
        $remaining = get_string('unlimited', 'unifair');
        $percent = '-';
        $status = html_writer::tag('span', get_string('unlimited', 'unifair'), ['class' => 'badge badge-info']);
    } else {
        $remaining = max(0, $uni['capacity'] - $uni['count']);
        $percent = round(($uni['count'] / $uni['capacity']) * 100, 1) . '%';
        if ($remaining === 0) {
            $status = html_writer::tag('span', get_string('quotafullshort', 'unifair'), ['class' => 'badge badge-danger']);
        } else if ($remaining <= 5) {
            $status = html_writer::tag('span', get_string('almostfull', 'unifair'), ['class' => 'badge badge-warning']);
        } else {
            $status = html_writer::tag('span', get_string('available', 'unifair'), ['class' => 'badge badge-success']);
        }
    }

    $table->data[] = [
        format_string($uni['sessionname']), format_string($uni['name']), $uni['count'],
        $uni['capacity'] === 0 ? get_string('unlimited', 'unifair') : $uni['capacity'],
        $remaining, $percent, $status,
    ];
}
echo html_writer::table($table);
echo html_writer::end_div();

echo html_writer::tag('h3', get_string('perstudentbreakdown', 'unifair'), ['style' => 'margin-top: 40px;']);
echo html_writer::start_div('table-responsive');
$table2 = new html_table();
$table2->attributes['class'] = 'table table-bordered table-striped';
$table2->head = ['No', get_string('fullname'), get_string('username'), get_string('session', 'unifair'),
    get_string('uniname', 'unifair'), get_string('timecreated', 'unifair')];

$no = 1;
foreach ($choices as $choice) {
    $table2->data[] = [
        $no++, fullname($choice), $choice->username, format_string($choice->sessionname),
        format_string($choice->uniname), userdate($choice->timecreated, '%d/%m/%Y %H:%M'),
    ];
}
echo html_writer::table($table2);
echo html_writer::end_div();

echo $OUTPUT->footer();
