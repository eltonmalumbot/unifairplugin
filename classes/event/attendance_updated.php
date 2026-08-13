<?php
namespace mod_unifair\event;

defined('MOODLE_INTERNAL') || die();

/** Attendance audit event. @package mod_unifair */
class attendance_updated extends base {
    public static function get_name(): string {
        return get_string('eventattendanceupdated', 'mod_unifair');
    }
}
