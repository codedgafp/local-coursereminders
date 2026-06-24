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

namespace local_coursereminders\external;

use core_external\external_api;
use local_coursereminders\rule;

/**
 * Tests for the get_reminder_log external function.
 *
 * @package    local_coursereminders
 * @category   test
 * @copyright  2026 Enovation Solutions
 * @author     Fabien Dallet <fabien.dallet@enovationsolutions.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_coursereminders\external\get_reminder_log
 */
final class get_reminder_log_test extends \advanced_testcase {
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
     * The log lists the sent reminders and the projected upcoming ones.
     */
    public function test_execute_returns_sent_and_projected(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $CFG->enablecompletion = 1;
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $now = time();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user(
            $student->id,
            $course->id,
            $this->learner_roleid(),
            'manual',
            $now - 10 * DAYSECS
        );

        $rule = new rule(0, (object) [
            'courseid' => $course->id,
            'type' => rule::TYPE_INACTIVITY,
            'delay' => 7,
            'enrolmethods' => rule::ENROLMETHODS_ALL,
            'maxcount' => 3,
            'sendertype' => rule::SENDER_NOREPLY,
            'subject' => 'Inactivity reminder for [coursename] after [delay]',
            'body' => '<p>Body</p>',
            'bodyformat' => FORMAT_HTML,
            'enabled' => 1,
        ]);
        $rule->create();

        $DB->insert_record('local_coursereminders_sent', (object) [
            'ruleid' => $rule->get('id'),
            'courseid' => $course->id,
            'userid' => $student->id,
            'type' => rule::TYPE_INACTIVITY,
            'occurrence' => 1,
            'timesent' => $now - 2 * DAYSECS,
        ]);

        $result = get_reminder_log::execute($course->id, $student->id);
        $result = external_api::clean_returnvalue(get_reminder_log::execute_returns(), $result);

        $this->assertTrue($result['hassent']);
        $this->assertCount(1, $result['sent']);
        $this->assertSame(get_string('type:inactivity', 'local_coursereminders'), $result['sent'][0]['label']);
        // Placeholders in the subject are rendered with the real values.
        $expecteddetail = 'Inactivity reminder for ' . format_string($course->fullname) . ' after '
            . get_string('delayweeks', 'local_coursereminders', 1);
        $this->assertSame($expecteddetail, $result['sent'][0]['detail']);

        // The next occurrence is projected, spaced by the delay from the last send.
        $this->assertTrue($result['hasprojected']);
        $this->assertCount(1, $result['projected']);
        $this->assertSame(get_string('type:inactivity', 'local_coursereminders'), $result['projected'][0]['label']);
    }

    /**
     * Disabled rules produce no projection, and users without history get empty lists.
     */
    public function test_execute_empty_log(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();

        $CFG->enablecompletion = 1;
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, $this->learner_roleid());

        $rule = new rule(0, (object) [
            'courseid' => $course->id,
            'type' => rule::TYPE_POSTENROL,
            'delay' => 7,
            'subject' => 'Subject',
            'enabled' => 0,
        ]);
        $rule->create();

        $result = get_reminder_log::execute($course->id, $student->id);
        $result = external_api::clean_returnvalue(get_reminder_log::execute_returns(), $result);

        $this->assertFalse($result['hassent']);
        $this->assertSame([], $result['sent']);
        $this->assertFalse($result['hasprojected']);
        $this->assertSame([], $result['projected']);
    }

    /**
     * The view history capability is required.
     */
    public function test_execute_requires_capability(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, $this->learner_roleid());
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        get_reminder_log::execute($course->id, $student->id);
    }
}
