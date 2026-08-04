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

namespace local_coursereminders\task;

use local_coursereminders\reminder_engine;
use local_coursereminders\rule;

/**
 * Tests for the batch sending mode and its ad hoc task.
 *
 * @package    local_coursereminders
 * @category   test
 * @copyright  2026 Enovation Solutions
 * @author     Fabien Dallet <fabien.dallet@enovationsolutions.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_coursereminders\task\send_reminder_batch
 * @covers     \local_coursereminders\reminder_engine
 */
final class send_reminder_batch_test extends \advanced_testcase {
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
     * Creates a completion-enabled course, a due post-enrolment rule and enrolled learners.
     *
     * @param int $learnercount The number of learners to enrol, all due for a reminder.
     * @return array{0: \stdClass, 1: rule, 2: \stdClass[]} The course, the rule and the learners.
     */
    private function create_due_setup(int $learnercount): array {
        global $CFG;

        $CFG->enablecompletion = 1;
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        $learners = [];
        for ($i = 0; $i < $learnercount; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user(
                $user->id,
                $course->id,
                $this->learner_roleid(),
                'manual',
                time() - 10 * DAYSECS
            );
            $learners[] = $user;
        }

        $rule = new rule(0, (object) [
            'courseid' => $course->id,
            'type' => rule::TYPE_POSTENROL,
            'delay' => 7,
            'enrolmethods' => rule::ENROLMETHODS_ALL,
            'maxcount' => 1,
            'sendertype' => rule::SENDER_NOREPLY,
            'subject' => 'Batch reminder',
            'body' => '<p>Batch reminder body</p>',
            'bodyformat' => FORMAT_HTML,
            'enabled' => 1,
        ]);
        $rule->create();

        return [$course, $rule, $learners];
    }

    /**
     * Returns the queued reminder batch ad hoc tasks.
     *
     * @return send_reminder_batch[]
     */
    private function get_queued_batches(): array {
        return \core\task\manager::get_adhoc_tasks(send_reminder_batch::class);
    }

    /**
     * With batch sending enabled the task queues chunked ad hoc tasks and sends nothing itself.
     */
    public function test_run_queues_batches_instead_of_sending(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();

        set_config('batchsending', 1, 'local_coursereminders');
        set_config('batchsize', 2, 'local_coursereminders');
        [, $rule, $learners] = $this->create_due_setup(3);

        $this->expectOutputRegex('/3 reminder\(s\) queued/');

        $sink = $this->redirectMessages();
        $engine = new reminder_engine();
        $this->assertSame(3, $engine->run());

        $this->assertCount(0, $sink->get_messages());
        $this->assertEquals(0, $DB->count_records('local_coursereminders_sent', ['ruleid' => $rule->get('id')]));

        $batches = $this->get_queued_batches();
        $this->assertCount(2, $batches);
        $queueduserids = [];
        foreach ($batches as $batch) {
            $data = $batch->get_custom_data();
            $this->assertEquals($rule->get('id'), $data->ruleid);
            $queueduserids = array_merge($queueduserids, array_map('intval', (array) $data->userids));
        }
        $expected = array_map(static function (\stdClass $user): int {
            return (int) $user->id;
        }, $learners);
        sort($queueduserids);
        sort($expected);
        $this->assertSame($expected, $queueduserids);
        $sink->close();
    }

    /**
     * Executing the queued batches sends the reminders once, and a re-run queues nothing more.
     */
    public function test_execute_sends_queued_reminders_once(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();

        set_config('batchsending', 1, 'local_coursereminders');
        [$course, $rule, ] = $this->create_due_setup(2);

        $this->expectOutputRegex('/2 of 2 queued reminder\(s\) sent/');
        $this->warm_plugin_manager();

        $engine = new reminder_engine();
        $this->assertSame(2, $engine->process_rule($rule, $course, time()));

        $batches = $this->get_queued_batches();
        $this->assertCount(1, $batches);

        $sink = $this->redirectMessages();
        foreach ($batches as $batch) {
            $batch->execute();
        }

        $this->assertCount(2, $sink->get_messages());
        $this->assertEquals(2, $DB->count_records('local_coursereminders_sent', ['ruleid' => $rule->get('id')]));

        // The reminders are recorded as sent, so a new evaluation queues nothing more.
        $this->assertSame(0, $engine->process_rule($rule, $course, time()));
        $this->assertCount(1, $this->get_queued_batches());
        $sink->close();
    }

    /**
     * A learner who completed the course after the batch was queued is skipped at send time.
     */
    public function test_execute_skips_user_completed_after_queueing(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();

        set_config('batchsending', 1, 'local_coursereminders');
        [$course, $rule, $learners] = $this->create_due_setup(2);

        $this->expectOutputRegex('/1 of 2 queued reminder\(s\) sent/');
        $this->warm_plugin_manager();

        $engine = new reminder_engine();
        $this->assertSame(2, $engine->process_rule($rule, $course, time()));

        $DB->insert_record('course_completions', (object) [
            'userid' => $learners[0]->id,
            'course' => $course->id,
            'timeenrolled' => time() - 10 * DAYSECS,
            'timestarted' => time() - 10 * DAYSECS,
            'timecompleted' => time(),
        ]);

        $sink = $this->redirectMessages();
        foreach ($this->get_queued_batches() as $batch) {
            $batch->execute();
        }

        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertEquals($learners[1]->id, $messages[0]->useridto);
        $this->assertEquals(1, $DB->count_records('local_coursereminders_sent', ['ruleid' => $rule->get('id')]));
        $sink->close();
    }

    /**
     * A batch whose rule was disabled after queueing is dropped without sending.
     */
    public function test_execute_drops_disabled_rule(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();

        set_config('batchsending', 1, 'local_coursereminders');
        [$course, $rule, ] = $this->create_due_setup(1);

        $this->expectOutputRegex('/dropping the batch/');

        $engine = new reminder_engine();
        $this->assertSame(1, $engine->process_rule($rule, $course, time()));

        $rule->set('enabled', 0);
        $rule->update();

        $sink = $this->redirectMessages();
        foreach ($this->get_queued_batches() as $batch) {
            $batch->execute();
        }

        $this->assertCount(0, $sink->get_messages());
        $this->assertEquals(0, $DB->count_records('local_coursereminders_sent', ['ruleid' => $rule->get('id')]));
        $sink->close();
    }

    /**
     * The ad hoc task carries the expected localised name.
     */
    public function test_get_name(): void {
        $task = new send_reminder_batch();
        $this->assertSame(get_string('task:sendreminderbatch', 'local_coursereminders'), $task->get_name());
    }
}
