<?php
namespace mod_unifair\event;

defined('MOODLE_INTERNAL') || die();

/** Session configuration audit event. @package mod_unifair */
class session_updated extends base {
    public static function get_name(): string {
        return get_string('eventsessionupdated', 'mod_unifair');
    }
}
