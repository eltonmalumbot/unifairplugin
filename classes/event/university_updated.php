<?php
namespace mod_unifair\event;

defined('MOODLE_INTERNAL') || die();

/** University configuration audit event. @package mod_unifair */
class university_updated extends base {
    public static function get_name(): string {
        return get_string('eventuniversityupdated', 'mod_unifair');
    }
}
