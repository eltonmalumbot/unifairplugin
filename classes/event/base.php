<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_unifair\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Shared implementation for UniFair audit events.
 *
 * @package mod_unifair
 */
abstract class base extends \core\event\base {

    /** Initialise common event data. */
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'unifair';
    }

    /** Human-readable log description. */
    public function get_description(): string {
        $action = $this->other['action'] ?? 'updated UniFair data';
        $related = empty($this->relateduserid) ? '' : " for user '{$this->relateduserid}'";
        return "The user with id '{$this->userid}' {$action}{$related} in the UniFair activity " .
            "with id '{$this->objectid}'.";
    }

    /** Link back to the activity. */
    public function get_url(): \moodle_url {
        return new \moodle_url('/mod/unifair/view.php', ['id' => $this->contextinstanceid]);
    }

    /** Validate the event payload. */
    protected function validate_data(): void {
        parent::validate_data();
        if (empty($this->other['action'])) {
            throw new \coding_exception('The action value must be set in other.');
        }
    }
}
