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

namespace local_coursereminders\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use local_coursereminders\rule;

/**
 * Tests for the privacy provider.
 *
 * @package    local_coursereminders
 * @category   test
 * @copyright  2026 Enovation Solutions
 * @author     Fabien Dallet <fabien.dallet@enovationsolutions.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_coursereminders\privacy\provider
 */
final class provider_test extends provider_testcase {
    /**
     * Creates a course, two users and a rule, with send log entries for the first user.
     *
     * @return array The course, the reminded user, the other user and the rule.
     */
    private function create_fixture(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $reminded = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();

        $rule = new rule(0, (object) [
            'courseid' => $course->id,
            'type' => rule::TYPE_INACTIVITY,
            'delay' => 7,
            'subject' => 'Subject',
            'enabled' => 1,
        ]);
        $rule->create();

        foreach ([$reminded, $other] as $user) {
            $DB->insert_record('local_coursereminders_sent', (object) [
                'ruleid' => $rule->get('id'),
                'courseid' => $course->id,
                'userid' => $user->id,
                'type' => rule::TYPE_INACTIVITY,
                'occurrence' => 1,
                'timesent' => time(),
            ]);
        }

        return [$course, $reminded, $other, $rule];
    }

    /**
     * The metadata declares the send log, the rule table and the messaging subsystem.
     */
    public function test_get_metadata(): void {
        $collection = provider::get_metadata(new collection('local_coursereminders'));
        $items = $collection->get_collection();
        $this->assertCount(3, $items);

        $names = array_map(function ($item) {
            return $item->get_name();
        }, $items);
        $this->assertContains('local_coursereminders_sent', $names);
        $this->assertContains('local_coursereminders_rule', $names);
        $this->assertContains('core_message', $names);
    }

    /**
     * The contexts of a user are the courses where reminders were sent to them.
     */
    public function test_get_contexts_for_userid(): void {
        $this->resetAfterTest();
        [$course, $reminded, , ] = $this->create_fixture();

        $contextlist = provider::get_contexts_for_userid($reminded->id);
        $this->assertCount(1, $contextlist);
        $this->assertEquals(\context_course::instance($course->id)->id, $contextlist->current()->id);

        $stranger = $this->getDataGenerator()->create_user();
        $this->assertCount(0, provider::get_contexts_for_userid($stranger->id));
    }

    /**
     * The users in a course context are those with send log entries.
     */
    public function test_get_users_in_context(): void {
        $this->resetAfterTest();
        [$course, $reminded, $other, ] = $this->create_fixture();

        $context = \context_course::instance($course->id);
        $userlist = new userlist($context, 'local_coursereminders');
        provider::get_users_in_context($userlist);

        $this->assertEqualsCanonicalizing([(int) $reminded->id, (int) $other->id], $userlist->get_userids());
    }

    /**
     * The export contains the reminders sent to the user.
     */
    public function test_export_user_data(): void {
        $this->resetAfterTest();
        [$course, $reminded, , ] = $this->create_fixture();

        $context = \context_course::instance($course->id);
        $contextlist = new approved_contextlist($reminded, 'local_coursereminders', [$context->id]);
        provider::export_user_data($contextlist);

        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
        $data = $writer->get_data([get_string('pluginname', 'local_coursereminders')]);
        $this->assertCount(1, $data->reminders);
        $this->assertEquals(get_string('type:inactivity', 'local_coursereminders'), $data->reminders[0]->type);
        $this->assertEquals(1, $data->reminders[0]->occurrence);
    }

    /**
     * Deleting for one user only removes that user's log entries.
     */
    public function test_delete_data_for_user(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $reminded, $other, ] = $this->create_fixture();

        $context = \context_course::instance($course->id);
        $contextlist = new approved_contextlist($reminded, 'local_coursereminders', [$context->id]);
        provider::delete_data_for_user($contextlist);

        $this->assertEquals(0, $DB->count_records('local_coursereminders_sent', ['userid' => $reminded->id]));
        $this->assertEquals(1, $DB->count_records('local_coursereminders_sent', ['userid' => $other->id]));
    }

    /**
     * Deleting for a whole context removes every log entry of the course.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, , , $rule] = $this->create_fixture();

        provider::delete_data_for_all_users_in_context(\context_course::instance($course->id));

        $this->assertEquals(0, $DB->count_records('local_coursereminders_sent', ['courseid' => $course->id]));
        // The rules themselves are course configuration and are kept.
        $this->assertTrue($DB->record_exists('local_coursereminders_rule', ['id' => $rule->get('id')]));
    }

    /**
     * Deleting for an approved user list removes only those users' log entries.
     */
    public function test_delete_data_for_users(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $reminded, $other, ] = $this->create_fixture();

        $context = \context_course::instance($course->id);
        $userlist = new approved_userlist($context, 'local_coursereminders', [$reminded->id]);
        provider::delete_data_for_users($userlist);

        $this->assertEquals(0, $DB->count_records('local_coursereminders_sent', ['userid' => $reminded->id]));
        $this->assertEquals(1, $DB->count_records('local_coursereminders_sent', ['userid' => $other->id]));
    }
}
