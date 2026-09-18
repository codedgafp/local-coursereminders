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
 * Tests for the sent reminders list export.
 *
 * @package    local_coursereminders
 * @category   test
 * @copyright  2026 Enovation Solutions
 * @author     Fabien Dallet <fabien.dallet@enovationsolutions.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_coursereminders\sent_export
 */
final class sent_export_test extends \advanced_testcase {
    /**
     * Returns a learner role id, as the engine defines learners through the gradebook roles.
     *
     * @return int
     */
    private function learner_roleid(): int {
        global $CFG;
        $roleids = array_filter(array_map('intval', explode(',', (string) $CFG->gradebookroles)));
        return (int) reset($roleids);
    }

    /**
     * Creates a rule on a course.
     *
     * @param int $courseid The course id.
     * @param string $type The trigger type.
     * @param string $subject The message subject.
     * @return rule
     */
    private function create_rule(int $courseid, string $type, string $subject): rule {
        $rule = new rule(0, (object) [
            'courseid' => $courseid,
            'type' => $type,
            'delay' => 7,
            'maxcount' => 5,
            'subject' => $subject,
            'enabled' => 1,
        ]);
        $rule->create();
        return $rule;
    }

    /**
     * Logs a sent reminder.
     *
     * @param rule $rule The rule that produced it.
     * @param int $userid The recipient.
     * @param int $timesent The send date.
     * @return void
     */
    private function log_sent(rule $rule, int $userid, int $timesent): void {
        global $DB;
        $DB->insert_record('local_coursereminders_sent', (object) [
            'ruleid' => $rule->get('id'),
            'courseid' => $rule->get('courseid'),
            'userid' => $userid,
            'type' => $rule->get('type'),
            'occurrence' => 1,
            'timesent' => $timesent,
        ]);
    }

    /**
     * Returns the rows of an export as a plain array.
     *
     * @param sent_export $export The export.
     * @return array
     */
    private function rows(sent_export $export): array {
        return iterator_to_array($export->get_rows(), false);
    }

    /**
     * One row is exported per reminder, grouped by learner and ordered by send date.
     */
    public function test_get_rows_details_every_reminder(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $now = time();

        $adam = $generator->create_user(['firstname' => 'Michelle', 'lastname' => 'Adam']);
        $generator->enrol_user($adam->id, $course->id, $this->learner_roleid());
        $fabre = $generator->create_user(['firstname' => 'Renée', 'lastname' => 'Fabre']);
        $generator->enrol_user($fabre->id, $course->id, $this->learner_roleid());

        $inactivity = $this->create_rule($course->id, rule::TYPE_INACTIVITY, 'Inactivity subject');
        $endofcourse = $this->create_rule($course->id, rule::TYPE_PRECOURSEEND, 'Ending subject');

        $this->log_sent($inactivity, $adam->id, $now - DAYSECS);
        $this->log_sent($endofcourse, $adam->id, $now);
        $this->log_sent($inactivity, $fabre->id, $now - HOURSECS);

        $rows = $this->rows(new sent_export($course));

        $this->assertCount(3, $rows);
        $this->assertSame(['Adam', 'Adam', 'Fabre'], array_column($rows, 'lastname'));
        $this->assertSame(['Michelle', 'Michelle', 'Renée'], array_column($rows, 'firstname'));
        $this->assertSame([$adam->email, $adam->email, $fabre->email], array_column($rows, 'email'));
        $this->assertSame(
            [
                get_string('type:inactivity', 'local_coursereminders'),
                get_string('type:precourseend', 'local_coursereminders'),
                get_string('type:inactivity', 'local_coursereminders'),
            ],
            array_column($rows, 'type')
        );
        $this->assertSame(
            ['Inactivity subject', 'Ending subject', 'Inactivity subject'],
            array_column($rows, 'reminder')
        );
        $dateformat = get_string('strftimedatetimeshort', 'core_langconfig');
        $this->assertSame(userdate($now - DAYSECS, $dateformat), $rows[0]['date']);
    }

    /**
     * The subject is rendered with the recipient's own placeholder values.
     */
    public function test_get_rows_renders_placeholders_per_learner(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['fullname' => 'Cours de test']);
        $learner = $generator->create_user(['firstname' => 'Renée', 'lastname' => 'Fabre']);
        $generator->enrol_user($learner->id, $course->id, $this->learner_roleid());

        $token = get_string('placeholder:firstname', 'local_coursereminders');
        $rule = $this->create_rule($course->id, rule::TYPE_INACTIVITY, "Bonjour {$token}");
        $this->log_sent($rule, $learner->id, time());

        $rows = $this->rows(new sent_export($course));

        $this->assertCount(1, $rows);
        $this->assertSame('Bonjour Renée', $rows[0]['reminder']);
    }

    /**
     * A reminder whose rule was deleted keeps its row, without a subject left to show.
     */
    public function test_get_rows_keeps_reminders_of_deleted_rules(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $learner = $generator->create_user();
        $generator->enrol_user($learner->id, $course->id, $this->learner_roleid());

        $rule = $this->create_rule($course->id, rule::TYPE_INACTIVITY, 'Inactivity subject');
        $this->log_sent($rule, $learner->id, time());
        $rule->delete();

        $rows = $this->rows(new sent_export($course));

        $this->assertCount(1, $rows);
        $this->assertSame('', $rows[0]['reminder']);
        $this->assertSame(get_string('type:inactivity', 'local_coursereminders'), $rows[0]['type']);
    }

    /**
     * The export covers the same learners as the list: other courses, other roles and
     * inactive enrolments are left out.
     */
    public function test_get_rows_covers_the_listed_learners_only(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $othercourse = $generator->create_course();
        $now = time();

        $learner = $generator->create_user(['lastname' => 'Listed']);
        $generator->enrol_user($learner->id, $course->id, $this->learner_roleid());
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $suspended = $generator->create_user(['lastname' => 'Suspended', 'suspended' => 1]);
        $generator->enrol_user($suspended->id, $course->id, $this->learner_roleid());
        $elsewhere = $generator->create_user(['lastname' => 'Elsewhere']);
        $generator->enrol_user($elsewhere->id, $othercourse->id, $this->learner_roleid());

        $rule = $this->create_rule($course->id, rule::TYPE_INACTIVITY, 'Subject');
        $otherrule = $this->create_rule($othercourse->id, rule::TYPE_INACTIVITY, 'Subject');

        $this->log_sent($rule, $learner->id, $now);
        $this->log_sent($rule, $teacher->id, $now);
        $this->log_sent($rule, $suspended->id, $now);
        $this->log_sent($otherrule, $elsewhere->id, $now);

        $rows = $this->rows(new sent_export($course));

        $this->assertSame(['Listed'], array_column($rows, 'lastname'));
    }

    /**
     * A course with no reminder sent exports no row.
     */
    public function test_get_rows_without_any_reminder(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $learner = $generator->create_user();
        $generator->enrol_user($learner->id, $course->id, $this->learner_roleid());

        $this->assertSame([], $this->rows(new sent_export($course)));
    }
}
