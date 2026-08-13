<?php
namespace mod_unifair\event;

defined('MOODLE_INTERNAL') || die();

/** Student choice audit event. @package mod_unifair */
class choice_updated extends base {
    public static function get_name(): string {
        return get_string('eventchoiceupdated', 'mod_unifair');
    }
}
