<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/mod/unifair/locallib.php');

use mod_unifair\form\import_form;

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('unifair', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$unifair = $DB->get_record('unifair', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/unifair:manageuni', $context);

$PAGE->set_url('/mod/unifair/import.php', ['id' => $cm->id]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('importdata', 'unifair'));
$PAGE->set_heading(format_string($course->fullname));

$form = new import_form($PAGE->url);
if ($form->is_cancelled()) {
    redirect(new moodle_url('/mod/unifair/manage.php', ['id' => $cm->id]));
}
$result = null;
if ($data = $form->get_data()) {
    $filename = $form->get_new_filename('importfile');
    $content = $form->get_file_content('importfile');
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'xlsx'], true)) {
        throw new moodle_exception('error_invalidfiletype', 'unifair');
    }
    $rows = [];
    if ($ext === 'csv') {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
            if (count($rows) > 5001) {
                throw new moodle_exception('error_importtoomanyrows', 'unifair');
            }
        }
        fclose($handle);
    } else {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\Reader\Xlsx::class)) {
            throw new moodle_exception('error_xlsxunavailable', 'unifair');
        }
        $temp = make_request_directory() . '/unifair-import.xlsx';
        file_put_contents($temp, $content);
        try {
            // Reject highly-compressed XLSX bombs before PhpSpreadsheet expands them.
            if (class_exists('ZipArchive')) {
                $zip = new ZipArchive();
                if ($zip->open($temp) !== true) {
                    throw new RuntimeException('Invalid XLSX container.');
                }
                $expandedsize = 0;
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $stat = $zip->statIndex($i);
                    $expandedsize += (int) ($stat['size'] ?? 0);
                    if ($expandedsize > 26214400) {
                        $zip->close();
                        throw new RuntimeException('Expanded XLSX is too large.');
                    }
                }
                $zip->close();
            }
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);
            $sheet = $reader->load($temp)->getActiveSheet();
            $columncount = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString(
                $sheet->getHighestDataColumn());
            if ($sheet->getHighestDataRow() > 5001 || $columncount > 20) {
                throw new RuntimeException('Spreadsheet dimensions exceed the import limit.');
            }
            $rows = $sheet->toArray(null, true, true, false);
        } catch (Throwable $e) {
            throw new moodle_exception('error_invalidspreadsheet', 'unifair', '', null, $e->getMessage());
        }
    }

    $headers = array_map(static fn($v) => strtolower(trim((string) $v)), array_shift($rows) ?: []);
    if ($headers) {
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
    }
    $required = ['session', 'university', 'capacity'];
    if (array_diff($required, $headers) || count($headers) !== count(array_unique($headers))) {
        throw new moodle_exception('error_importheaders', 'unifair');
    }
    $isxlsx = $ext === 'xlsx';
    $parsedate = static function($value) use ($isxlsx): int {
        if ($value === null || $value === '') {
            return 0;
        }
        if ($isxlsx && is_numeric($value)) {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp((float) $value);
        }
        $value = trim((string) $value);
        $timezone = new DateTimeZone(date_default_timezone_get());
        $formats = ['!d/m/Y H:i:s', '!d/m/Y H:i', '!d/m/Y', '!Y-m-d H:i:s', '!Y-m-d H:i', '!Y-m-d'];
        foreach ($formats as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value, $timezone);
            $errors = DateTimeImmutable::getLastErrors();
            if ($date && ($errors === false || (!$errors['warning_count'] && !$errors['error_count']))) {
                return $date->getTimestamp();
            }
        }
        return strtotime($value) ?: 0;
    };
    $result = ['createdsessions' => 0, 'createdunis' => 0, 'updatedunis' => 0, 'errors' => []];
    $lockfactory = \core\lock\lock_config::get_lock_factory('mod_unifair');
    $importlock = $lockfactory->get_lock('import:' . $unifair->id, 10);
    if (!$importlock) {
        throw new moodle_exception('error_importbusy', 'unifair');
    }
    try {
        $transaction = $DB->start_delegated_transaction();
        foreach ($rows as $index => $values) {
            $rownum = $index + 2;
            $row = array_combine($headers,
                array_slice(array_pad($values, count($headers), ''), 0, count($headers)));
            $sessionname = trim((string) ($row['session'] ?? ''));
            $sessiondescription = trim((string) ($row['session_description'] ?? ''));
            $sessionsortorder = (int) ($row['session_sortorder'] ?? 0);
            $uniname = trim((string) ($row['university'] ?? ''));
            $capacity = filter_var($row['capacity'] ?? null, FILTER_VALIDATE_INT);
            if ($sessionname === '' || $uniname === '' || core_text::strlen($sessionname) > 255 ||
                    core_text::strlen($uniname) > 255 || $capacity === false || $capacity < 0) {
                $result['errors'][] = get_string('importrowerror', 'unifair', $rownum);
                continue;
            }
            $rawtimeopen = trim((string) ($row['timeopen'] ?? ''));
            $rawtimeclose = trim((string) ($row['timeclose'] ?? ''));
            $timeopen = $parsedate($rawtimeopen);
            $timeclose = $parsedate($rawtimeclose);
            if (($rawtimeopen !== '' && !$timeopen) || ($rawtimeclose !== '' && !$timeclose)) {
                $result['errors'][] = get_string('importrowdateerror', 'unifair', $rownum);
                continue;
            }
            $matching = $DB->get_records('unifair_session', [
                'unifairid' => $unifair->id,
                'name' => $sessionname,
                'timeopen' => $timeopen,
                'timeclose' => $timeclose,
            ], 'id ASC');
            $session = false;
            foreach ($matching as $candidate) {
                if ((string) $candidate->description === $sessiondescription &&
                        (int) $candidate->sortorder === $sessionsortorder) {
                    $session = $candidate;
                    break;
                }
            }
            if (!$session) {
                $now = time();
                $session = (object) [
                    'unifairid' => $unifair->id, 'name' => $sessionname,
                    'description' => $sessiondescription,
                    'sortorder' => $sessionsortorder,
                    'timeopen' => $timeopen,
                    'timeclose' => $timeclose,
                    'timecreated' => $now, 'timemodified' => $now,
                ];
                if ($session->timeopen && $session->timeclose && $session->timeclose <= $session->timeopen) {
                    $result['errors'][] = get_string('importrowtimeerror', 'unifair', $rownum);
                    continue;
                }
                $session->id = $DB->insert_record('unifair_session', $session);
                $result['createdsessions']++;
            }
            // Every spreadsheet row is a separate option. This deliberately permits
            // the same university name to appear more than once in one session.
            $DB->insert_record('unifair_uni', (object) [
                'unifairid' => $unifair->id, 'sessionid' => $session->id, 'uniname' => $uniname,
                'capacity' => $capacity, 'quotaused' => 0,
                'sortorder' => (int) ($row['university_sortorder'] ?? 0),
                'timecreated' => time(), 'timemodified' => time(),
            ]);
            $result['createdunis']++;
        }
        $transaction->allow_commit();
        $transaction = null;
        unifair_trigger_audit_event(\mod_unifair\event\data_imported::class, $unifair,
            'imported sessions and universities', null, [
                'createdsessions' => $result['createdsessions'],
                'createdunis' => $result['createdunis'],
                'updatedunis' => $result['updatedunis'],
                'errorcount' => count($result['errors']),
            ]);
    } catch (Throwable $e) {
        if (isset($transaction)) {
            $transaction->rollback($e);
        }
        throw $e;
    } finally {
        $importlock->release();
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('importdata', 'unifair'));
echo $OUTPUT->notification(get_string('importinstructions', 'unifair'), 'info');
echo html_writer::link(new moodle_url('/mod/unifair/import_template.csv'), get_string('downloadtemplate', 'unifair'),
    ['class' => 'btn btn-secondary mb-3']);
if ($result) {
    echo $OUTPUT->notification(get_string('importsummary', 'unifair', (object) $result),
        empty($result['errors']) ? 'success' : 'warning');
    if ($result['errors']) {
        echo html_writer::alist($result['errors']);
    }
}
$form->display();
echo $OUTPUT->footer();
