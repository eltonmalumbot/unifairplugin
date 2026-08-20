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
 * English language strings for mod_unifair.
 *
 * @package     mod_unifair
 * @copyright   2026 BPK PENABUR Jakarta
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'University Fair';
$string['modulename'] = 'University Fair';
$string['modulenameplural'] = 'University Fairs';
$string['modulename_help'] = 'Lets students choose universities (or any quota-limited item) at an education fair.';
$string['pluginadministration'] = 'University Fair administration';

$string['configunifair'] = 'University Fair settings';
$string['maxchoices'] = 'Maximum choices per student';
$string['maxchoicesvalidation'] = 'Maximum choices must be at least 1.';
$string['requiredchoices'] = 'Required choices per student';
$string['requiredchoices_help'] = 'Enter 0 to keep the legacy behaviour: students select exactly one university in every session. Enter a number such as 4 to lock each student to exactly 4 total choices, while still allowing at most one university per session.';
$string['requiredchoicesnonnegative'] = 'Required choices cannot be negative.';
$string['requiredchoicesexceedssessions'] = 'Required choices cannot exceed the number of available sessions ({$a}).';
$string['requiredchoicesbelowexisting'] = 'This limit cannot be lowered because a student already has {$a} choices. Correct the student choices first.';
$string['universitiestext'] = 'Universities (quick bulk setup)';
$string['universitiestext_help'] = 'Optional: quickly add several universities when creating the activity. One per line, format: <br><b>Name|Capacity</b><br><br>Example:<br>Universitas Indonesia|50 (quota of 50)<br>Universitas Terbuka|0 (no quota / unlimited)<br><br>You can add, edit, or delete universities individually at any time afterwards from "Kelola Universitas" (Manage Universities).';
$string['manageuninotice'] = 'Universities are managed from the "Kelola Universitas" (Manage Universities) page, available after saving.';
$string['unifairopen'] = 'Opening time';
$string['unifairclose'] = 'Closing time';
$string['timeopenclosevalidation'] = 'The closing time must be after the opening time.';
$string['viewtab'] = 'Student view';
$string['reporttab'] = 'Teacher report';

// Sessions.
$string['session'] = 'Session';
$string['managesessions'] = 'Manage sessions';
$string['addsession'] = 'Add session';
$string['editsession'] = 'Edit session';
$string['sessionname'] = 'Session name';
$string['sessiondescription'] = 'Description';
$string['defaultsessionname'] = 'Session 1';
$string['migratedsessionname'] = 'Default session (existing data)';
$string['nosessions'] = 'No sessions have been created yet.';
$string['createsessionfirst'] = 'Create at least one session before adding a university.';
$string['sessionhasnouniversities'] = 'This session has no universities yet. Ask the organiser to complete the setup.';
$string['confirmdeletesession'] = 'Are you sure you want to delete session "{$a}"?';
$string['sessiondeleteblocked'] = 'This session cannot be deleted because it still contains {$a} university/universities. Move or delete them first.';
$string['sessiondeleted'] = 'Session "{$a}" was deleted.';
$string['sessionconfigsaved'] = 'Session "{$a}" was saved.';
$string['reorder'] = 'Move';
$string['reorderhelp'] = 'Drag the icon in the Move column to change the session order. The order is saved automatically and can only be changed within the same day.';
$string['dragsession'] = 'Drag to change the session order';
$string['dragsessionname'] = 'Drag session {$a} to change its order';
$string['savingorder'] = 'Saving session order...';
$string['sessionordersaved'] = 'The session order was saved.';
$string['sessionordererror'] = 'The session order could not be saved. The page will reload.';
$string['error_reorderbusy'] = 'Another user is changing the session order. Please try again.';
$string['invalidsession'] = 'Select a valid session.';
$string['sessionchangeblocked'] = 'A university with existing student choices cannot be moved to another session. Remove those choices first.';

// Manage universities.
$string['manageuni'] = 'Kelola Universitas';
$string['adduni'] = 'Tambah Universitas';
$string['edituni'] = 'Ubah Universitas';
$string['uniname'] = 'Nama';
$string['capacity'] = 'Kuota';
$string['capacity_help'] = 'Jumlah maksimum siswa yang dapat memilih item ini. Isi <b>0</b> untuk tanpa batas kuota (unlimited).';
$string['capacityvalidation'] = 'Kuota tidak boleh negatif.';
$string['sortorder'] = 'Urutan';
$string['quotaused'] = 'Terpakai';
$string['remaining'] = 'Sisa';
$string['unlimited'] = 'Tanpa kuota';
$string['actions'] = 'Aksi';
$string['nouniversities'] = 'Belum ada universitas. Klik "Tambah Universitas" untuk menambahkan.';
$string['confirmdeleteuni'] = 'Yakin ingin menghapus "{$a}"? Semua pilihan siswa untuk item ini juga akan terhapus. Tindakan ini tidak dapat dibatalkan.';
$string['unisaved'] = 'Universitas "{$a}" berhasil disimpan.';
$string['unideleted'] = 'Universitas "{$a}" berhasil dihapus.';
$string['backtoactivity'] = '&laquo; Kembali ke aktivitas';

// Student-facing.
$string['pickmax'] = 'Pilih maksimal {$a} Universitas:';
$string['pickonepersession'] = 'Select exactly one university in every session.';
$string['pickrequiredtotal'] = 'Select exactly {$a} universities. Maximum one university per session.';
$string['choiceprogress'] = 'Choice progress: {$a->current}/{$a->required}.';
$string['choicetargetreached'] = 'Your target of {$a} choices is complete. Other sessions are locked.';
$string['savechoices'] = 'Simpan Pilihan Saya';
$string['choicessaved'] = 'Pilihan Anda berhasil disimpan.';
$string['savesession'] = 'Save This Session';
$string['sessionsaved'] = 'Your choice for {$a} has been saved.';
$string['error_sessionclosed'] = 'This session is not open or has already closed.';
$string['error_activityclosed'] = 'This University Fair activity is not open or has already closed.';
$string['quotafullshort'] = 'KUOTA PENUH';
$string['notopenyet'] = 'Pemilihan belum dibuka. Akan dibuka pada {$a}.';
$string['alreadyclosed'] = 'Pemilihan sudah ditutup sejak {$a}.';
$string['nopermissiontochoose'] = 'Anda tidak memiliki izin untuk membuat pilihan pada aktivitas ini.';

// Errors.
$string['error_toomanychoices'] = 'Anda hanya boleh memilih maksimal {$a} universitas.';
$string['error_choicelimit'] = 'You may save only {$a} university choices in this activity.';
$string['error_exactchoices'] = 'Save exactly {$a} university choices.';
$string['error_someitemsfull'] = 'Nothing was changed because these universities became full while you were saving: {$a}. Choose another university and try again.';
$string['error_invalidchoices'] = 'The submitted choices are invalid. Please reload the page and try again.';
$string['error_onepersession'] = 'You may select only one university per session.';
$string['error_allsessionsrequired'] = 'Select exactly one university in every session before saving.';
$string['error_choicebusy'] = 'Your choices are being processed by another request. Please try again.';

// Reports.
$string['summarystats'] = 'Statistik Ringkas';
$string['totalitems'] = 'Total Universitas';
$string['totalsessions'] = 'Total sessions';
$string['totalchoices'] = 'Total Pilihan';
$string['totalstudents'] = 'Total Siswa yang Sudah Memilih';
$string['perunibreakdown'] = 'Detail Pilihan Per Universitas';
$string['perstudentbreakdown'] = 'Detail Pilihan Per Siswa';
$string['percentagefull'] = 'Persentase';
$string['status'] = 'Status';
$string['almostfull'] = 'Hampir Penuh';
$string['available'] = 'Tersedia';
$string['exportxlsx'] = 'Export ke Excel';
$string['timecreated'] = 'Waktu Pilih';

// Capabilities.
$string['unifair:addinstance'] = 'Add a new University Fair activity';
$string['unifair:view'] = 'View University Fair activity';
$string['unifair:choose'] = 'Submit university choices';
$string['unifair:manageuni'] = 'Manage universities (add/edit/delete)';
$string['unifair:viewreport'] = 'View University Fair reports';
$string['unifair:managechoices'] = 'Edit student University Fair choices';
$string['unifair:manageattendance'] = 'Edit University Fair attendance';

$string['privacy:metadata'] = 'The University Fair plugin stores each student\'s university choices.';
$string['privacy:metadata:unifair_choice'] = 'Information about a student\'s university choice.';
$string['privacy:metadata:unifair_choice:uniid'] = 'The university that was chosen.';
$string['privacy:metadata:unifair_choice:userid'] = 'The ID of the user who made the choice.';
$string['privacy:metadata:unifair_choice:timecreated'] = 'The time the choice was made.';

// Version 3.1 features.
$string['sessiontimeopen'] = 'Session opens';
$string['sessiontimeclose'] = 'Session closes';
$string['error_closebeforeopen'] = 'The closing time must be later than the opening time.';
$string['availability'] = 'Availability';
$string['always'] = 'No limit';
$string['sessionwindowdisplay'] = 'Open: {$a->open}; Close: {$a->close}';
$string['sessionnotopen'] = 'This session opens on {$a}.';
$string['sessionclosed'] = 'This session closed on {$a}.';
$string['remainingplaces'] = '{$a} places remaining';
$string['managechoices'] = 'Edit student choices';
$string['selectstudent'] = 'Select a student';
$string['teacherchoicesaved'] = 'The student choices were saved.';
$string['teacherrequiredchoicesnotice'] = 'Target mode is active: the student must have exactly {$a} total choices. Use "No choice in this session" for sessions that are not used.';
$string['noselectionforsession'] = 'No choice in this session';
$string['attendance'] = 'Attendance';
$string['selectsession'] = 'Select a session';
$string['unmarked'] = 'Not marked';
$string['present'] = 'Present';
$string['absent'] = 'Absent';
$string['excused'] = 'Excused';
$string['attendancesaved'] = 'Attendance was saved.';
$string['error_attendancebusy'] = 'Attendance is being updated by another request. Try again shortly.';
$string['incompletestudents'] = 'Students with incomplete choices';
$string['incompletestudentlist'] = 'Students who have not selected every session';
$string['incompletestudentlisttarget'] = 'Students who have not reached the {$a}-choice target';
$string['importdata'] = 'Import sessions and universities';
$string['importfile'] = 'CSV or XLSX file';
$string['import'] = 'Import';
$string['downloadtemplate'] = 'Download CSV template';
$string['importinstructions'] = 'Required columns: session, university, capacity. Optional: session_description, session_sortorder, timeopen, timeclose, university_sortorder. Every university row is created as a separate option, including duplicate names. Use DD/MM/YYYY HH:MM or YYYY-MM-DD HH:MM for dates.';
$string['importsummary'] = 'Import complete: {$a->createdsessions} sessions created, {$a->createdunis} universities created, {$a->updatedunis} universities updated.';
$string['importrowerror'] = 'Row {$a}: session, university, or capacity is invalid.';
$string['importrowtimeerror'] = 'Row {$a}: closing time must be later than opening time.';
$string['importrowdateerror'] = 'Row {$a}: invalid date format. Use DD/MM/YYYY HH:MM or YYYY-MM-DD HH:MM.';
$string['error_importheaders'] = 'The import file is missing a required header: session, university, or capacity.';
$string['error_invalidspreadsheet'] = 'The XLSX file could not be read.';
$string['error_invalidfiletype'] = 'Upload a CSV or XLSX file.';
$string['error_importtoomanyrows'] = 'The import is limited to 5,000 data rows.';
$string['error_xlsxunavailable'] = 'XLSX import is unavailable on this server. Use the CSV template instead.';
$string['error_importbusy'] = 'Another import is running for this activity. Try again shortly.';
$string['deleteallunis'] = 'Delete All Universities';
$string['confirmdeleteallunis'] = 'Are you sure you want to delete all {$a} universities? All sessions, student choices, and attendance data in this activity will also be deleted. The activity description is preserved. This cannot be undone.';
$string['allunisdeleted'] = 'All {$a} universities, sessions, and related data were deleted.';
$string['selectallunis'] = 'Select all universities';
$string['selectuni'] = 'Select {$a}';
$string['deleteselectedunis'] = 'Delete selected';
$string['noselectedunis'] = 'Select at least one university to delete.';
$string['confirmdeleteselectedunis'] = 'Are you sure you want to delete the {$a} selected universities? Related student choices and attendance data will also be deleted. This cannot be undone.';
$string['selectedunisdeleted'] = '{$a} selected universities and their related choices were deleted.';
$string['sortascending'] = 'Sort {$a} ascending';
$string['sortdescending'] = 'Sort {$a} descending';
$string['choicelockednotice'] = 'This session choice has been saved and locked. Contact a teacher if it needs correction.';
$string['error_choicealreadylocked'] = 'This session choice has already been saved and cannot be changed by the student. Contact a teacher if it needs correction.';
$string['eventchoiceupdated'] = 'University choice updated';
$string['eventattendanceupdated'] = 'Attendance updated';
$string['eventsessionupdated'] = 'Session configuration updated';
$string['eventuniversityupdated'] = 'University configuration updated';
$string['eventdataimported'] = 'Sessions and universities imported';
$string['privacy:metadata:unifair_attendance'] = 'A student attendance record for a session.';
$string['privacy:metadata:unifair_attendance:userid'] = 'The student whose attendance was recorded.';
$string['privacy:metadata:unifair_attendance:status'] = 'The attendance status.';
$string['privacy:metadata:unifair_attendance:timemodified'] = 'The time the attendance status was changed.';
$string['privacy:metadata:unifair_attendance:modifiedby'] = 'The ID of the user who last changed the attendance status.';
