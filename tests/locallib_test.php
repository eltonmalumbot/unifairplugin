<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_unifair;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../locallib.php');

/**
 * Tests for quota-safe choice updates.
 *
 * @package mod_unifair
 * @covers ::unifair_apply_choices
 * @covers ::unifair_get_remaining
 * @covers ::unifair_sort_sessions
 * @covers ::unifair_sort_universities
 */
final class locallib_test extends \advanced_testcase {

    /** Multi-day sessions are grouped by day before applying the per-day order. */
    public function test_sessions_are_sorted_by_day_then_session_order(): void {
        $sessions = [];
        $id = 1;
        foreach (['Jumat|28 Agustus 2026', 'Sabtu|29 Agustus 2026'] as $description) {
            foreach (range(1, 4) as $sortorder) {
                $sessions[$id] = (object) [
                    'id' => $id,
                    'name' => 'Sesi ' . $sortorder,
                    'description' => $description,
                    'sortorder' => $sortorder,
                ];
                $id++;
            }
        }

        // Reproduce the old SQL result: 1,1,2,2,3,3,4,4.
        uasort($sessions, static function(\stdClass $left, \stdClass $right): int {
            return (int) $left->sortorder <=> (int) $right->sortorder;
        });

        $sorted = array_values(unifair_sort_sessions($sessions));
        $this->assertSame([1, 2, 3, 4, 1, 2, 3, 4],
            array_map(static fn(\stdClass $session): int => (int) $session->sortorder, $sorted));
        $this->assertSame([
            'Jumat|28 Agustus 2026', 'Jumat|28 Agustus 2026',
            'Jumat|28 Agustus 2026', 'Jumat|28 Agustus 2026',
            'Sabtu|29 Agustus 2026', 'Sabtu|29 Agustus 2026',
            'Sabtu|29 Agustus 2026', 'Sabtu|29 Agustus 2026',
        ], array_map(static fn(\stdClass $session): string => $session->description, $sorted));
    }

    /** A manually changed sort order controls the Student View sequence. */
    public function test_manual_session_order_is_applied_within_the_day(): void {
        $sessions = [
            1 => (object) ['id' => 1, 'name' => 'Sesi 1', 'description' => 'Jumat', 'sortorder' => 3],
            2 => (object) ['id' => 2, 'name' => 'Sesi 2', 'description' => 'Jumat', 'sortorder' => 1],
            3 => (object) ['id' => 3, 'name' => 'Sesi 3', 'description' => 'Jumat', 'sortorder' => 4],
            4 => (object) ['id' => 4, 'name' => 'Sesi 4', 'description' => 'Jumat', 'sortorder' => 2],
        ];

        $sorted = array_values(unifair_sort_sessions($sessions));
        $this->assertSame([2, 4, 1, 3],
            array_map(static fn(\stdClass $session): int => (int) $session->id, $sorted));
    }

    /** Management-table sorting supports session, text, and numeric columns. */
    public function test_universities_can_be_sorted_by_each_management_column(): void {
        $sessions = [
            11 => (object) ['id' => 11, 'name' => 'Sesi 1', 'description' => 'Jumat', 'sortorder' => 1],
            12 => (object) ['id' => 12, 'name' => 'Sesi 2', 'description' => 'Jumat', 'sortorder' => 2],
        ];
        $universities = [
            1 => (object) ['id' => 1, 'sessionid' => 12, 'uniname' => 'Beta',
                'capacity' => 20, 'quotaused' => 5, 'sortorder' => 2],
            2 => (object) ['id' => 2, 'sessionid' => 11, 'uniname' => 'Alpha',
                'capacity' => 10, 'quotaused' => 7, 'sortorder' => 1],
            3 => (object) ['id' => 3, 'sessionid' => 11, 'uniname' => 'Gamma',
                'capacity' => 0, 'quotaused' => 1, 'sortorder' => 3],
        ];

        $ids = static fn(array $records): array => array_map(
            static fn(\stdClass $record): int => (int) $record->id,
            array_values($records)
        );

        $this->assertSame([2, 3, 1], $ids(unifair_sort_universities($universities, $sessions, 'session', 'asc')));
        $this->assertSame([3, 1, 2], $ids(unifair_sort_universities($universities, $sessions, 'name', 'desc')));
        $this->assertSame([2, 1, 3], $ids(unifair_sort_universities($universities, $sessions, 'capacity', 'asc')));
        $this->assertSame([3, 1, 2], $ids(unifair_sort_universities($universities, $sessions, 'used', 'asc')));
        $this->assertSame([2, 1, 3], $ids(unifair_sort_universities($universities, $sessions, 'remaining', 'asc')));
        $this->assertSame([2, 1, 3], $ids(unifair_sort_universities($universities, $sessions, 'sortorder', 'asc')));
    }

    /** Create a session explicitly; empty activities no longer create one. */
    private function create_session(int $unifairid): \stdClass {
        global $DB;

        $now = time();
        $record = (object) [
            'unifairid' => $unifairid,
            'name' => 'Session 1',
            'description' => '',
            'sortorder' => 1,
            'timeopen' => 0,
            'timeclose' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $record->id = $DB->insert_record('unifair_session', $record);
        return $record;
    }

    /** A student cannot replace a confirmed session choice. */
    public function test_student_choice_is_locked_after_save(): void {
        global $DB;

        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('unifair', [
            'course' => $course->id,
            'name' => 'Lock test',
        ]);
        $session = $this->create_session($activity->id);
        $user = $this->getDataGenerator()->create_user();
        $now = time();
        $ids = [];
        foreach (['University A', 'University B'] as $sortorder => $name) {
            $ids[] = $DB->insert_record('unifair_uni', (object) [
                'unifairid' => $activity->id, 'sessionid' => $session->id, 'uniname' => $name,
                'capacity' => 10, 'quotaused' => 0, 'sortorder' => $sortorder,
                'timecreated' => $now, 'timemodified' => $now,
            ]);
        }

        unifair_apply_choices($activity, $user->id, [$ids[0]], [$session->id], false);
        $this->expectException(\moodle_exception::class);
        unifair_apply_choices($activity, $user->id, [$ids[1]], [$session->id], false);
    }

    /** A failed replacement must preserve the student's confirmed choice. */
    public function test_full_replacement_preserves_existing_choice(): void {
        global $DB;

        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('unifair', [
            'course' => $course->id,
            'name' => 'Quota test',
        ]);
        $session = $this->create_session($activity->id);
        $now = time();
        $firstid = $DB->insert_record('unifair_uni', (object) [
            'unifairid' => $activity->id,
            'sessionid' => $session->id,
            'uniname' => 'Full university',
            'capacity' => 1,
            'quotaused' => 0,
            'sortorder' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $secondid = $DB->insert_record('unifair_uni', (object) [
            'unifairid' => $activity->id,
            'sessionid' => $session->id,
            'uniname' => 'Existing university',
            'capacity' => 1,
            'quotaused' => 0,
            'sortorder' => 2,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        unifair_apply_choices($activity, $user1->id, [$firstid], [$session->id]);
        unifair_apply_choices($activity, $user2->id, [$secondid], [$session->id]);
        $result = unifair_apply_choices($activity, $user2->id, [$firstid], [$session->id]);

        $this->assertSame([$firstid], array_values($result['failed']));
        $this->assertTrue($DB->record_exists('unifair_choice', [
            'unifairid' => $activity->id,
            'userid' => $user2->id,
            'uniid' => $secondid,
        ]));
        $this->assertSame(1, (int) $DB->get_field('unifair_uni', 'quotaused', ['id' => $firstid]));
        $this->assertSame(1, (int) $DB->get_field('unifair_uni', 'quotaused', ['id' => $secondid]));
    }

    /** Multiple choices in one session are rejected server-side. */
    public function test_rejects_two_choices_in_one_session(): void {
        global $DB;

        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('unifair', [
            'course' => $course->id,
            'name' => 'Validation test',
        ]);
        $session = $this->create_session($activity->id);
        $now = time();
        $ids = [];
        foreach (['One', 'Two'] as $sortorder => $name) {
            $ids[] = $DB->insert_record('unifair_uni', (object) [
                'unifairid' => $activity->id,
                'sessionid' => $session->id,
                'uniname' => $name,
                'capacity' => 0,
                'quotaused' => 0,
                'sortorder' => $sortorder,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }

        $this->expectException(\invalid_parameter_exception::class);
        unifair_apply_choices($activity, 2, $ids, [$session->id]);
    }
}
