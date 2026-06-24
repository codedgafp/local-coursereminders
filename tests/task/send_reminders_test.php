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

use local_coursereminders\rule;

/**
 * Tests for the send reminders scheduled task.
 *
 * @package    local_coursereminders
 * @category   test
 * @copyright  2026 Enovation Solutions
 * @author     Fabien Dallet <fabien.dallet@enovationsolutions.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_coursereminders\task\send_reminders
 */
final class send_reminders_test extends \advanced_testcase {
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
     * The scheduled task dispatches due reminders and a same-day re-run sends nothing more.
     */
    public function test_execute_sends_due_reminders_once(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $CFG->enablecompletion = 1;
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, $this->learner_roleid(), 'manual', time() - 10 * DAYSECS);

        $rule = new rule(0, (object) [
            'courseid' => $course->id,
            'type' => rule::TYPE_POSTENROL,
            'delay' => 7,
            'enrolmethods' => rule::ENROLMETHODS_ALL,
            'maxcount' => 1,
            'sendertype' => rule::SENDER_NOREPLY,
            'subject' => 'Task reminder',
            'body' => '<p>Task reminder body</p>',
            'bodyformat' => FORMAT_HTML,
            'enabled' => 1,
        ]);
        $rule->create();

        $this->expectOutputRegex('/reminder\(s\) sent/');
        $this->warm_plugin_manager();

        $sink = $this->redirectMessages();
        $task = new send_reminders();
        $task->execute();

        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertEquals($student->id, $messages[0]->useridto);
        $this->assertEquals(1, $DB->count_records('local_coursereminders_sent', ['ruleid' => $rule->get('id')]));

        // Running the task again the same day must not duplicate the reminder.
        $task->execute();
        $this->assertCount(1, $sink->get_messages());
        $this->assertEquals(1, $DB->count_records('local_coursereminders_sent', ['ruleid' => $rule->get('id')]));
        $sink->close();
    }

    /**
     * The task carries the expected localised name.
     */
    public function test_get_name(): void {
        $task = new send_reminders();
        $this->assertSame(get_string('task:sendreminders', 'local_coursereminders'), $task->get_name());
    }
}
