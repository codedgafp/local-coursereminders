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

namespace local_coursereminders;

/**
 * Tests for the reminder rule persistent.
 *
 * @package    local_coursereminders
 * @category   test
 * @copyright  2026 Enovation Solutions
 * @author     Fabien Dallet <fabien.dallet@enovationsolutions.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_coursereminders\rule
 */
final class rule_test extends \advanced_testcase {
    /**
     * Creates a rule with sensible defaults overridable per test.
     *
     * @param int $courseid The course id.
     * @param array $overrides Properties to override.
     * @return rule
     */
    private function create_rule(int $courseid, array $overrides = []): rule {
        $data = (object) array_merge([
            'courseid' => $courseid,
            'type' => rule::TYPE_POSTENROL,
            'delay' => 7,
            'enrolmethods' => rule::ENROLMETHODS_ALL,
            'alertthreshold' => 3,
            'maxcount' => 1,
            'sendertype' => rule::SENDER_NOREPLY,
            'subject' => 'Subject',
            'body' => '<p>Body</p>',
            'bodyformat' => FORMAT_HTML,
            'enabled' => 0,
        ], $overrides);
        $rule = new rule(0, $data);
        $rule->create();
        return $rule;
    }

    /**
     * A rule can be created, re-read and deleted.
     */
    public function test_create_read_delete(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $rule = $this->create_rule($course->id, ['delay' => 21, 'enrolmethods' => 'self,manual']);
        $this->assertGreaterThan(0, $rule->get('id'));

        $loaded = new rule($rule->get('id'));
        $this->assertEquals($course->id, $loaded->get('courseid'));
        $this->assertEquals(rule::TYPE_POSTENROL, $loaded->get('type'));
        $this->assertEquals(3, $loaded->get_delay_weeks());
        $this->assertSame(['self', 'manual'], $loaded->get_enrol_methods());

        $loaded->delete();
        $this->assertFalse(rule::record_exists($rule->get('id')));
    }

    /**
     * The delay label uses weeks for whole weeks and days otherwise.
     */
    public function test_get_delay_label(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $weeks = $this->create_rule($course->id, ['delay' => 21]);
        $this->assertSame(get_string('delayweeks', 'local_coursereminders', 3), $weeks->get_delay_label());

        $days = $this->create_rule($course->id, ['delay' => 10]);
        $this->assertSame(get_string('delaydays', 'local_coursereminders', 10), $days->get_delay_label());
    }

    /**
     * The "all" sentinel resolves to an empty list of enrolment methods.
     */
    public function test_get_enrol_methods_all(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $rule = $this->create_rule($course->id, ['enrolmethods' => rule::ENROLMETHODS_ALL]);
        $this->assertSame([], $rule->get_enrol_methods());
    }

    /**
     * The last sent time reflects the most recent matching sent-log row.
     */
    public function test_get_last_sent_time(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $rule = $this->create_rule($course->id);

        $this->assertNull($rule->get_last_sent_time());

        foreach ([1000, 2500, 1500] as $time) {
            $DB->insert_record('local_coursereminders_sent', (object) [
                'ruleid' => $rule->get('id'),
                'courseid' => $course->id,
                'userid' => 7,
                'type' => $rule->get('type'),
                'occurrence' => 1,
                'timesent' => $time,
            ]);
        }
        $this->assertEquals(2500, $rule->get_last_sent_time());
    }

    /**
     * Rules are scoped to their course.
     */
    public function test_get_records_for_course(): void {
        $this->resetAfterTest();
        $coursea = $this->getDataGenerator()->create_course();
        $courseb = $this->getDataGenerator()->create_course();

        $this->create_rule($coursea->id);
        $this->create_rule($coursea->id, ['type' => rule::TYPE_INACTIVITY]);
        $this->create_rule($courseb->id);

        $this->assertCount(2, rule::get_records_for_course($coursea->id));
        $this->assertCount(1, rule::get_records_for_course($courseb->id));
    }

    /**
     * The form checkbox group is converted to the stored value and back.
     */
    public function test_enrolmethods_conversion(): void {
        $this->assertEquals(
            rule::ENROLMETHODS_ALL,
            rule::enrolmethods_from_form(['all' => 1, 'self' => 1, 'manual' => 0, 'other' => 0])
        );
        $this->assertEquals(
            'self,manual',
            rule::enrolmethods_from_form(['all' => 0, 'self' => 1, 'manual' => 1, 'other' => 0])
        );
        $this->assertEquals(
            '',
            rule::enrolmethods_from_form(['all' => 0, 'self' => 0, 'manual' => 0, 'other' => 0])
        );

        $this->assertEquals(
            ['all' => 1, 'self' => 0, 'manual' => 0, 'other' => 0],
            rule::enrolmethods_to_form(rule::ENROLMETHODS_ALL)
        );
        $this->assertEquals(
            ['all' => 0, 'self' => 1, 'manual' => 0, 'other' => 1],
            rule::enrolmethods_to_form('self,other')
        );
        // An empty stored value is treated as "all".
        $this->assertEquals(
            ['all' => 1, 'self' => 0, 'manual' => 0, 'other' => 0],
            rule::enrolmethods_to_form('')
        );
    }

    /**
     * An unknown trigger type is rejected by the persistent validation.
     */
    public function test_invalid_type_is_rejected(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $rule = new rule(0, (object) [
            'courseid' => $course->id,
            'type' => 'bogus',
            'sendertype' => rule::SENDER_NOREPLY,
        ]);
        $this->expectException(\core\invalid_persistent_exception::class);
        $rule->create();
    }
}
