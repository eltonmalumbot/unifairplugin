<?php
namespace mod_unifair\event;

defined('MOODLE_INTERNAL') || die();

/** Import audit event. @package mod_unifair */
class data_imported extends base {
    public static function get_name(): string {
        return get_string('eventdataimported', 'mod_unifair');
    }
}
