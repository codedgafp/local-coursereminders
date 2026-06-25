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
 * Tests for the reminder engine.
 *
 * @package    local_coursereminders
 * @category   test
 * @copyright  2026 Enovation Solutions
 * @author     Fabien Dallet <fabien.dallet@enovationsolutions.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_coursereminders\reminder_engine
 */
final class reminder_engine_test extends \advanced_testcase {
    /**
     * Creates a reminder rule on a course.
     *
     * @param int $courseid The course id.
     * @param array $overrides Property values to override.
     * @return rule
     */
    private function create_rule(int $courseid, array $overrides = []): rule {
        $record = array_merge([
            'courseid' => $courseid,
            'type' => rule::TYPE_POSTENROL,
            'delay' => 7,
            'enrolmethods' => rule::ENROLMETHODS_ALL,
            'alertthreshold' => 3,
            'maxcount' => 1,
            'sendertype' => rule::SENDER_NOREPLY,
            'subject' => 'Reminder for [firstname]',
            'body' => '<p>Hello [firstname], it has been [weeks] week(s). Course: [coursename] at [courseurl].</p>',
            'bodyformat' => FORMAT_HTML,
            'enabled' => 1,
        ], $overrides);
        $rule = new rule(0, (object) $record);
        $rule->create();
        return $rule;
    }

    /**
     * Returns a learner role id, as the engine defines learners through the gradebook roles.
     *
     * The role id is read from the configuration instead of hardcoding the "student"
     * shortname, because some sites rename the default roles.
     *
     * @return int
     */
    private function learner_roleid(): int {
        global $CFG;
        $roleids = array_filter(array_map('intval', explode(',', (string) $CFG->gradebookroles)));
        return (int) reset($roleids);
    }

    /**
     * Creates a user enrolled as a learner on a course.
     *
     * @param \stdClass $course The course.
     * @param string $enrol The enrolment method.
     * @param int $timestart The enrolment start time.
     * @param array $userparams User record overrides.
     * @return \stdClass The created user.
     */
    private function create_learner(
        \stdClass $course,
        string $enrol = 'manual',
        int $timestart = 0,
        array $userparams = []
    ): \stdClass {
        $generator = $this->getDataGenerator();
        $user = $generator->create_user($userparams);
        $generator->enrol_user($user->id, $course->id, $this->learner_roleid(), $enrol, $timestart);
        return $user;
    }

    /**
     * Pre-warms the plugin manager disk scan, tolerating broken unrelated plugin checkouts.
     *
     * Sending a message triggers a full plugin disk scan. When an unrelated plugin
     * directory misses its version.php, PHPUnit would convert the resulting include()
     * warning into an error in the middle of message_send(). The scan result is cached on
     * the plugin manager singleton, so scanning once here keeps the test itself unaffected.
     */
    private function warm_plugin_manager(): void {
        set_error_handler(static function (): bool {
            return true;
        }, E_WARNING);
        try {
            \core_plugin_manager::instance()->get_present_plugins('message');
        } finally {
            restore_error_handler();
        }
        $this->resetDebugging();
    }

    /**
     * Builds a learner status object for evaluation.
     *
     * @param array $overrides Values to override.
     * @return \stdClass
     */
    private function status(array $overrides = []): \stdClass {
        return (object) array_merge([
            'refdate' => 0,
            'lastaccess' => 0,
            'completed' => false,
            'sentcount' => 0,
            'lastsent' => 0,
        ], $overrides);
    }

    /**
     * Creates a course with completion configured.
     *
     * @param array $options Extra course options.
     * @return \stdClass
     */
    private function create_completion_course(array $options = []): \stdClass {
        global $CFG;
        $CFG->enablecompletion = 1;
        return $this->getDataGenerator()->create_course(array_merge(['enablecompletion' => 1], $options));
    }

    /**
     * The post-enrolment reminder fires once after the delay, only when the learner never connected.
     */
    public function test_evaluate_postenrol(): void {
        $now = 1700000000;
        $rule = new rule(0, (object) ['courseid' => 1, 'type' => rule::TYPE_POSTENROL, 'delay' => 7]);
        $course = (object) ['id' => 1, 'enddate' => 0];
        $engine = new reminder_engine();

        // Due once the delay has elapsed since enrolment.
        $this->assertSame(1, $engine->evaluate($rule, $course, $this->status(['refdate' => $now - 8 * DAYSECS]), $now));
        // Not due before the delay.
        $this->assertNull($engine->evaluate($rule, $course, $this->status(['refdate' => $now - 6 * DAYSECS]), $now));
        // Suppressed when the learner connected after enrolling.
        $this->assertNull($engine->evaluate($rule, $course, $this->status([
            'refdate' => $now - 8 * DAYSECS,
            'lastaccess' => $now - 2 * DAYSECS,
        ]), $now));
        // A connection before re-enrolment does not suppress the reminder.
        $this->assertSame(1, $engine->evaluate($rule, $course, $this->status([
            'refdate' => $now - 8 * DAYSECS,
            'lastaccess' => $now - 30 * DAYSECS,
        ]), $now));
        // Sent at most once.
        $this->assertNull($engine->evaluate($rule, $course, $this->status([
            'refdate' => $now - 8 * DAYSECS,
            'sentcount' => 1,
            'lastsent' => $now - DAYSECS,
        ]), $now));
        // Suppressed when the course is completed.
        $this->assertNull($engine->evaluate($rule, $course, $this->status([
            'refdate' => $now - 8 * DAYSECS,
            'completed' => true,
        ]), $now));
    }

    /**
     * The inactivity reminder loops every delay, restarts on access and stops at the maximum.
     */
    public function test_evaluate_inactivity(): void {
        $now = 1700000000;
        $rule = new rule(0, (object) ['courseid' => 1, 'type' => rule::TYPE_INACTIVITY, 'delay' => 7, 'maxcount' => 2]);
        $course = (object) ['id' => 1, 'enddate' => 0];
        $engine = new reminder_engine();

        // A learner who never accessed since enrolment is left to the after-enrolment rule.
        $this->assertNull($engine->evaluate($rule, $course, $this->status(['refdate' => $now - 8 * DAYSECS]), $now));
        // First occurrence after the delay, counted from the access made at enrolment.
        $this->assertSame(1, $engine->evaluate($rule, $course, $this->status([
            'refdate' => $now - 8 * DAYSECS,
            'lastaccess' => $now - 8 * DAYSECS,
        ]), $now));
        // A recent access restarts the countdown.
        $this->assertNull($engine->evaluate($rule, $course, $this->status([
            'refdate' => $now - 20 * DAYSECS,
            'lastaccess' => $now - 3 * DAYSECS,
        ]), $now));
        // The next occurrence is spaced by the delay from the previous send.
        $this->assertNull($engine->evaluate($rule, $course, $this->status([
            'refdate' => $now - 20 * DAYSECS,
            'lastaccess' => $now - 20 * DAYSECS,
            'sentcount' => 1,
            'lastsent' => $now - 3 * DAYSECS,
        ]), $now));
        $this->assertSame(2, $engine->evaluate($rule, $course, $this->status([
            'refdate' => $now - 20 * DAYSECS,
            'lastaccess' => $now - 20 * DAYSECS,
            'sentcount' => 1,
            'lastsent' => $now - 8 * DAYSECS,
        ]), $now));
        // Capped by the per-rule maximum.
        $this->assertNull($engine->evaluate($rule, $course, $this->status([
            'refdate' => $now - 40 * DAYSECS,
            'lastaccess' => $now - 40 * DAYSECS,
            'sentcount' => 2,
            'lastsent' => $now - 10 * DAYSECS,
        ]), $now));
        // The loop stops once the course has ended.
        $endedcourse = (object) ['id' => 1, 'enddate' => $now - DAYSECS];
        $this->assertNull($engine->evaluate($rule, $endedcourse, $this->status([
            'refdate' => $now - 20 * DAYSECS,
            'lastaccess' => $now - 20 * DAYSECS,
        ]), $now));
    }

    /**
     * The projection mirrors the evaluation gates and returns the next due time.
     */
    public function test_project_next_time(): void {
        $now = 1700000000;
        $engine = new reminder_engine();
        $course = (object) ['id' => 1, 'enddate' => 0];

        // Post-enrolment: projected at the end of the delay, clamped to now when overdue.
        $postenrol = new rule(0, (object) ['courseid' => 1, 'type' => rule::TYPE_POSTENROL, 'delay' => 7]);
        $this->assertSame(
            $now + 3 * DAYSECS,
            $engine->project_next_time($postenrol, $course, $this->status(['refdate' => $now - 4 * DAYSECS]), $now)
        );
        $this->assertSame(
            $now,
            $engine->project_next_time($postenrol, $course, $this->status(['refdate' => $now - 10 * DAYSECS]), $now)
        );
        $this->assertNull($engine->project_next_time($postenrol, $course, $this->status([
            'refdate' => $now - 4 * DAYSECS,
            'lastaccess' => $now - DAYSECS,
        ]), $now));
        $this->assertNull($engine->project_next_time($postenrol, $course, $this->status([
            'refdate' => $now - 4 * DAYSECS,
            'sentcount' => 1,
        ]), $now));

        // Inactivity: spaced from the latest activity or send, never beyond the course end.
        $inactivity = new rule(0, (object) [
            'courseid' => 1,
            'type' => rule::TYPE_INACTIVITY,
            'delay' => 7,
            'maxcount' => 2,
        ]);
        $this->assertSame($now + 4 * DAYSECS, $engine->project_next_time($inactivity, $course, $this->status([
            'refdate' => $now - 20 * DAYSECS,
            'lastaccess' => $now - 20 * DAYSECS,
            'sentcount' => 1,
            'lastsent' => $now - 3 * DAYSECS,
        ]), $now));
        // No projection for a learner who never accessed since enrolment.
        $this->assertNull($engine->project_next_time($inactivity, $course, $this->status([
            'refdate' => $now - 20 * DAYSECS,
            'sentcount' => 1,
            'lastsent' => $now - 3 * DAYSECS,
        ]), $now));
        $this->assertNull($engine->project_next_time($inactivity, $course, $this->status([
            'refdate' => $now - 20 * DAYSECS,
            'lastaccess' => $now - 20 * DAYSECS,
            'sentcount' => 2,
            'lastsent' => $now - 3 * DAYSECS,
        ]), $now));
        $endingcourse = (object) ['id' => 1, 'enddate' => $now + 2 * DAYSECS];
        $this->assertNull($engine->project_next_time($inactivity, $endingcourse, $this->status([
            'refdate' => $now - 20 * DAYSECS,
            'lastaccess' => $now - 20 * DAYSECS,
            'sentcount' => 1,
            'lastsent' => $now - 3 * DAYSECS,
        ]), $now));

        // Pre-course-end: projected at the window opening, clamped to now when inside it.
        $precourseend = new rule(0, (object) ['courseid' => 1, 'type' => rule::TYPE_PRECOURSEEND, 'delay' => 7]);
        $farcourse = (object) ['id' => 1, 'enddate' => $now + 10 * DAYSECS];
        $this->assertSame(
            $now + 3 * DAYSECS,
            $engine->project_next_time($precourseend, $farcourse, $this->status(), $now)
        );
        $closecourse = (object) ['id' => 1, 'enddate' => $now + 5 * DAYSECS];
        $this->assertSame(
            $now,
            $engine->project_next_time($precourseend, $closecourse, $this->status(), $now)
        );
        $this->assertNull($engine->project_next_time($precourseend, $course, $this->status(), $now));
        // Suppressed by completion.
        $this->assertNull($engine->project_next_time(
            $precourseend,
            $farcourse,
            $this->status(['completed' => true]),
            $now
        ));
    }

    /**
     * The per-user status reflects the send log and targeting of one learner.
     */
    public function test_get_user_status(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->create_completion_course();
        $now = time();
        $student = $this->create_learner($course, 'manual', $now - 10 * DAYSECS);
        $outsider = $this->getDataGenerator()->create_user();

        $rule = $this->create_rule($course->id, ['type' => rule::TYPE_INACTIVITY, 'delay' => 7, 'maxcount' => 3]);
        $DB->insert_record('local_coursereminders_sent', (object) [
            'ruleid' => $rule->get('id'),
            'courseid' => $course->id,
            'userid' => $student->id,
            'type' => rule::TYPE_INACTIVITY,
            'occurrence' => 1,
            'timesent' => $now - 2 * DAYSECS,
        ]);

        $engine = new reminder_engine();
        $status = $engine->get_user_status($rule, $course, $student->id, $now);
        $this->assertNotNull($status);
        $this->assertEquals(1, $status->sentcount);
        $this->assertEquals($now - 2 * DAYSECS, $status->lastsent);
        $this->assertFalse($status->completed);

        // A user the rule does not target has no status.
        $this->assertNull($engine->get_user_status($rule, $course, $outsider->id, $now));
    }

    /**
     * Sends made before the current enrolment are ignored, so a re-enrolled learner is reminded afresh.
     */
    public function test_get_user_status_ignores_sends_before_reenrolment(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->create_completion_course();
        $now = time();
        // The learner's current (active) enrolment started three days ago.
        $student = $this->create_learner($course, 'manual', $now - 3 * DAYSECS);

        $rule = $this->create_rule($course->id, ['type' => rule::TYPE_INACTIVITY, 'delay' => 7, 'maxcount' => 3]);
        // A send from a previous enrolment, before the current reference date.
        $DB->insert_record('local_coursereminders_sent', (object) [
            'ruleid' => $rule->get('id'),
            'courseid' => $course->id,
            'userid' => $student->id,
            'type' => rule::TYPE_INACTIVITY,
            'occurrence' => 1,
            'timesent' => $now - 30 * DAYSECS,
        ]);
        // A send made during the current enrolment.
        $DB->insert_record('local_coursereminders_sent', (object) [
            'ruleid' => $rule->get('id'),
            'courseid' => $course->id,
            'userid' => $student->id,
            'type' => rule::TYPE_INACTIVITY,
            'occurrence' => 1,
            'timesent' => $now - DAYSECS,
        ]);

        $engine = new reminder_engine();
        $status = $engine->get_user_status($rule, $course, $student->id, $now);
        $this->assertNotNull($status);
        // Only the send within the current enrolment is counted.
        $this->assertEquals(1, $status->sentcount);
        $this->assertEquals($now - DAYSECS, $status->lastsent);
    }

    /**
     * The pre-course-end reminder fires once inside the window before the course end date.
     */
    public function test_evaluate_precourseend(): void {
        $now = 1700000000;
        $rule = new rule(0, (object) ['courseid' => 1, 'type' => rule::TYPE_PRECOURSEEND, 'delay' => 7]);
        $engine = new reminder_engine();
        $status = $this->status(['refdate' => $now - 30 * DAYSECS]);

        // Due inside the window.
        $course = (object) ['id' => 1, 'enddate' => $now + 5 * DAYSECS];
        $this->assertSame(1, $engine->evaluate($rule, $course, $status, $now));
        // Not due before the window opens.
        $course = (object) ['id' => 1, 'enddate' => $now + 10 * DAYSECS];
        $this->assertNull($engine->evaluate($rule, $course, $status, $now));
        // Not due once the course has ended.
        $course = (object) ['id' => 1, 'enddate' => $now - DAYSECS];
        $this->assertNull($engine->evaluate($rule, $course, $status, $now));
        // Never due without an end date.
        $course = (object) ['id' => 1, 'enddate' => 0];
        $this->assertNull($engine->evaluate($rule, $course, $status, $now));
        // Suppressed when the course is completed.
        $course = (object) ['id' => 1, 'enddate' => $now + 5 * DAYSECS];
        $this->assertNull($engine->evaluate($rule, $course, $this->status(['completed' => true]), $now));
    }

    /**
     * Recipient resolution honours the enrolment-method targeting and excludes teachers.
     */
    public function test_get_recipients_enrol_methods(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->create_completion_course();
        $generator = $this->getDataGenerator();
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_ENABLED, ['courseid' => $course->id, 'enrol' => 'self']);

        $manualstudent = $this->create_learner($course);
        $selfstudent = $this->create_learner($course, 'self');
        $teacher = $generator->create_and_enrol($course, 'editingteacher');

        $engine = new reminder_engine();
        $now = time();

        $allrule = $this->create_rule($course->id);
        $recipients = $engine->get_recipients($allrule, $course, $now);
        $this->assertEqualsCanonicalizing([$manualstudent->id, $selfstudent->id], array_keys($recipients));
        $this->assertArrayNotHasKey($teacher->id, $recipients);

        $selfrule = $this->create_rule($course->id, ['enrolmethods' => 'self']);
        $this->assertEquals([$selfstudent->id], array_keys($engine->get_recipients($selfrule, $course, $now)));

        $manualrule = $this->create_rule($course->id, ['enrolmethods' => 'manual']);
        $this->assertEquals([$manualstudent->id], array_keys($engine->get_recipients($manualrule, $course, $now)));

        $otherrule = $this->create_rule($course->id, ['enrolmethods' => 'other']);
        $this->assertSame([], $engine->get_recipients($otherrule, $course, $now));
    }

    /**
     * Recipient resolution honours the rule group restriction and the enrolment state.
     */
    public function test_get_recipients_group_and_enrolment_state(): void {
        $this->resetAfterTest();

        $course = $this->create_completion_course();
        $generator = $this->getDataGenerator();

        $ingroup = $this->create_learner($course);
        $outofgroup = $this->create_learner($course);
        $suspendedenrolment = $generator->create_user();
        $generator->enrol_user($suspendedenrolment->id, $course->id, $this->learner_roleid(), 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $suspendeduser = $this->create_learner($course, 'manual', 0, ['suspended' => 1]);

        $group = $generator->create_group(['courseid' => $course->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $ingroup->id]);

        $engine = new reminder_engine();
        $now = time();

        $grouprule = $this->create_rule($course->id, ['groupid' => $group->id]);
        $this->assertEquals([$ingroup->id], array_keys($engine->get_recipients($grouprule, $course, $now)));

        $allrule = $this->create_rule($course->id);
        $recipients = $engine->get_recipients($allrule, $course, $now);
        $this->assertEqualsCanonicalizing([$ingroup->id, $outofgroup->id], array_keys($recipients));
    }

    /**
     * A due post-enrolment rule sends once, logs the send, and never duplicates on re-run.
     */
    public function test_process_rule_postenrol_sends_once(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $course = $this->create_completion_course();
        $now = time();

        $inactive = $this->create_learner($course, 'manual', $now - 10 * DAYSECS);
        $active = $this->create_learner($course, 'manual', $now - 10 * DAYSECS);
        $DB->insert_record('user_lastaccess', (object) [
            'userid' => $active->id,
            'courseid' => $course->id,
            'timeaccess' => $now - 2 * DAYSECS,
        ]);

        $rule = $this->create_rule($course->id, ['type' => rule::TYPE_POSTENROL, 'delay' => 7]);
        $engine = new reminder_engine();
        $this->warm_plugin_manager();

        $sink = $this->redirectMessages();
        $this->assertSame(1, $engine->process_rule($rule, $course, $now));

        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertEquals($inactive->id, $messages[0]->useridto);
        $this->assertSame('local_coursereminders', $messages[0]->component);
        $this->assertSame('reminder', $messages[0]->eventtype);

        $log = $DB->get_record('local_coursereminders_sent', ['ruleid' => $rule->get('id'), 'userid' => $inactive->id]);
        $this->assertNotFalse($log);
        $this->assertEquals(1, $log->occurrence);
        $this->assertEquals($course->id, $log->courseid);
        $this->assertSame(rule::TYPE_POSTENROL, $log->type);

        // Re-running the same day is a no-op.
        $this->assertSame(0, $engine->process_rule($rule, $course, $now));
        $this->assertCount(1, $sink->get_messages());
        $sink->close();
    }

    /**
     * The inactivity rule loops with the configured spacing and stops at the maximum count.
     */
    public function test_process_rule_inactivity_progression(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $course = $this->create_completion_course();
        $now = time();

        $student = $this->create_learner($course, 'manual', $now - 8 * DAYSECS);
        // The learner accessed the course at enrolment, so inactivity applies to them.
        $DB->insert_record('user_lastaccess', (object) [
            'userid' => $student->id,
            'courseid' => $course->id,
            'timeaccess' => $now - 8 * DAYSECS,
        ]);

        $rule = $this->create_rule($course->id, ['type' => rule::TYPE_INACTIVITY, 'delay' => 7, 'maxcount' => 2]);
        $engine = new reminder_engine();
        $this->warm_plugin_manager();
        $sink = $this->redirectMessages();

        // First occurrence is due, and not again the same day.
        $this->assertSame(1, $engine->process_rule($rule, $course, $now));
        $this->assertSame(0, $engine->process_rule($rule, $course, $now));

        // The second occurrence becomes due one delay after the first send.
        $this->assertSame(0, $engine->process_rule($rule, $course, $now + 6 * DAYSECS));
        $this->assertSame(1, $engine->process_rule($rule, $course, $now + 8 * DAYSECS));

        // The maximum count stops the loop for good.
        $this->assertSame(0, $engine->process_rule($rule, $course, $now + 16 * DAYSECS));

        $occurrences = $DB->get_fieldset_select(
            'local_coursereminders_sent',
            'occurrence',
            'ruleid = :ruleid AND userid = :userid ORDER BY occurrence',
            ['ruleid' => $rule->get('id'), 'userid' => $student->id]
        );
        $this->assertEquals([1, 2], $occurrences);
        $sink->close();
    }

    /**
     * The pre-course-end rule skips learners who completed the course.
     */
    public function test_process_rule_precourseend_skips_completed(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $now = time();
        $course = $this->create_completion_course(['enddate' => $now + 5 * DAYSECS]);

        $pending = $this->create_learner($course);
        $completed = $this->create_learner($course);
        $DB->insert_record('course_completions', (object) [
            'userid' => $completed->id,
            'course' => $course->id,
            'timeenrolled' => $now - 10 * DAYSECS,
            'timestarted' => $now - 10 * DAYSECS,
            'timecompleted' => $now - DAYSECS,
        ]);

        $rule = $this->create_rule($course->id, ['type' => rule::TYPE_PRECOURSEEND, 'delay' => 7]);
        $engine = new reminder_engine();
        $this->warm_plugin_manager();

        $sink = $this->redirectMessages();
        $this->assertSame(1, $engine->process_rule($rule, $course, $now));
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertEquals($pending->id, $messages[0]->useridto);
        $sink->close();
    }

    /**
     * The full run skips courses where completion is not configured.
     */
    public function test_run_skips_course_without_completion(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $CFG->enablecompletion = 1;
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 0]);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, $this->learner_roleid(), 'manual', time() - 10 * DAYSECS);
        $this->create_rule($course->id, ['type' => rule::TYPE_POSTENROL, 'delay' => 7]);

        $this->expectOutputRegex('/completion is not configured/');

        $sink = $this->redirectMessages();
        $engine = new reminder_engine();
        $this->assertSame(0, $engine->run(time()));
        $this->assertCount(0, $sink->get_messages());
        $sink->close();
    }

    /**
     * The message placeholders are replaced in the sent subject and body.
     */
    public function test_send_replaces_placeholders(): void {
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $now = time();
        $courseend = $now + 30 * DAYSECS;
        $course = $this->create_completion_course(['fullname' => 'Reminder course', 'enddate' => $courseend]);
        $enrolstart = $now - 15 * DAYSECS;

        $student = $this->create_learner($course, 'manual', $enrolstart, [
            'firstname' => 'Amélie',
            'lastname' => 'Durand',
        ]);

        $rule = $this->create_rule($course->id, [
            'type' => rule::TYPE_POSTENROL,
            'delay' => 14,
            'subject' => 'Hello [firstname], about [coursename]',
            'body' => '<p>[firstname] [lastname], you enrolled [delay] ago ([weeks] week(s)) in [coursename]: [courseurl]. '
                . 'Enrolled on [enroldate], the course ends on [courseenddate].</p>',
        ]);
        $engine = new reminder_engine();
        $this->warm_plugin_manager();

        $sink = $this->redirectMessages();
        $this->assertSame(1, $engine->process_rule($rule, $course, $now));
        $message = $sink->get_messages()[0];
        $sink->close();

        $this->assertSame('Hello Amélie, about Reminder course', $message->subject);
        // The [delay] placeholder renders as a localised label, [weeks] as a bare number.
        $this->assertStringContainsString('Amélie Durand, you enrolled 2 week(s) ago (2 week(s))', $message->fullmessagehtml);
        $this->assertStringContainsString('Reminder course', $message->fullmessagehtml);
        $courseurl = (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
        $this->assertStringContainsString($courseurl, $message->fullmessagehtml);
        // The enrolment date and the course end date are rendered as localised dates.
        $datefmt = get_string('strftimedate', 'core_langconfig');
        $this->assertStringContainsString('Enrolled on ' . userdate($enrolstart, $datefmt), $message->fullmessagehtml);
        $this->assertStringContainsString('ends on ' . userdate($courseend, $datefmt), $message->fullmessagehtml);
    }

    /**
     * The sender resolution maps the configured identity, with a no-reply fallback.
     */
    public function test_get_sender(): void {
        $this->resetAfterTest();

        $engine = new reminder_engine();
        $course = $this->getDataGenerator()->create_course();

        $editingroles = get_archetype_roles('editingteacher');
        $editingteacherrole = reset($editingroles);

        $noreply = new rule(0, (object) ['courseid' => $course->id, 'sendertype' => rule::SENDER_NOREPLY]);
        $this->assertEquals(\core_user::get_noreply_user()->id, $engine->get_sender($noreply)->id);

        $support = new rule(0, (object) ['courseid' => $course->id, 'sendertype' => rule::SENDER_SUPPORT]);
        $this->assertEquals(\core_user::get_support_user()->id, $engine->get_sender($support)->id);

        // With no teacher in the course, the teacher sender type falls back to no-reply.
        $orphan = new rule(0, (object) [
            'courseid' => $course->id,
            'sendertype' => rule::SENDER_TEACHER,
            'senderid' => 0,
        ]);
        $this->assertEquals(\core_user::get_noreply_user()->id, $engine->get_sender($orphan)->id);

        // The rule creator is preferred while they still teach the course.
        $creator = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($creator->id, $course->id, $editingteacherrole->id);
        $fromcreator = new rule(0, (object) [
            'courseid' => $course->id,
            'sendertype' => rule::SENDER_TEACHER,
            'senderid' => $creator->id,
        ]);
        $this->assertEquals($creator->id, $engine->get_sender($fromcreator)->id);

        // A creator who no longer teaches the course falls back to a current course teacher.
        $exteacher = $this->getDataGenerator()->create_user();
        $fromexteacher = new rule(0, (object) [
            'courseid' => $course->id,
            'sendertype' => rule::SENDER_TEACHER,
            'senderid' => $exteacher->id,
        ]);
        $sender = $engine->get_sender($fromexteacher);
        $this->assertEquals($creator->id, $sender->id);
        $this->assertNotEquals($exteacher->id, $sender->id);
    }
}
