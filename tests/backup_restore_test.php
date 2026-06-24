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
 * Tests for the backup and restore of reminder rules and the send log.
 *
 * @package    local_coursereminders
 * @category   test
 * @copyright  2026 Enovation Solutions
 * @author     Fabien Dallet <fabien.dallet@enovationsolutions.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \backup_local_coursereminders_plugin
 * @covers     \restore_local_coursereminders_plugin
 */
final class backup_restore_test extends \advanced_testcase {
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
     * Pre-warms the plugin manager disk scan, tolerating broken unrelated plugin checkouts.
     *
     * The restore precheck triggers a full plugin disk scan. When an unrelated plugin
     * directory misses its version.php, PHPUnit would convert the resulting include()
     * warning into an error in the middle of the restore. The scan result is cached on
     * the plugin manager singleton, so scanning once here keeps the test itself unaffected.
     */
    private function warm_plugin_manager(): void {
        set_error_handler(static function (): bool {
            return true;
        }, E_WARNING);
        try {
            \core_plugin_manager::instance()->get_present_plugins('qbank');
        } finally {
            restore_error_handler();
        }
        $this->resetDebugging();
    }

    /**
     * Backs up a course and restores it into a new course.
     *
     * @param \stdClass $course The course to back up.
     * @param bool $userinfo Whether to include user information.
     * @return int The restored course id.
     */
    private function backup_and_restore(\stdClass $course, bool $userinfo): int {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        $this->warm_plugin_manager();

        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_value($userinfo);
        $bc->execute_plan();
        $results = $bc->get_results();
        $file = $results['backup_destination'];
        $bc->destroy();

        $backupdir = 'coursereminders_test_' . ($userinfo ? 'users' : 'nousers');
        $path = make_backup_temp_directory($backupdir);
        $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), $path);
        $file->delete();

        $category = $DB->get_field('course', 'category', ['id' => $course->id]);
        $newcourseid = \restore_dbops::create_new_course('Restored course', 'RC' . ($userinfo ? 'U' : 'N'), $category);
        $rc = new \restore_controller(
            $backupdir,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        return $newcourseid;
    }

    /**
     * Creates a course carrying a group-restricted rule and one send log entry.
     *
     * @return array The course, the group, the student and the rule.
     */
    private function create_fixture(): array {
        global $DB;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, $this->learner_roleid());

        $group = $generator->create_group(['courseid' => $course->id, 'name' => 'Backup group']);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $student->id]);

        $rule = new rule(0, (object) [
            'courseid' => $course->id,
            'type' => rule::TYPE_INACTIVITY,
            'delay' => 14,
            'groupid' => $group->id,
            'enrolmethods' => 'self,manual',
            'alertthreshold' => 4,
            'maxcount' => 3,
            'sendertype' => rule::SENDER_NOREPLY,
            'subject' => 'Backed up subject',
            'body' => '<p>Backed up body</p>',
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
            'timesent' => time() - DAYSECS,
            'messageid' => 12345,
        ]);

        return [$course, $group, $student, $rule];
    }

    /**
     * With user information, the rules and the send log are restored and remapped.
     */
    public function test_backup_restore_with_userinfo(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $group, $student, $rule] = $this->create_fixture();

        $newcourseid = $this->backup_and_restore($course, true);

        $newrule = $DB->get_record('local_coursereminders_rule', ['courseid' => $newcourseid], '*', MUST_EXIST);
        $this->assertSame(rule::TYPE_INACTIVITY, $newrule->type);
        $this->assertEquals(14, $newrule->delay);
        $this->assertSame('self,manual', $newrule->enrolmethods);
        $this->assertEquals(4, $newrule->alertthreshold);
        $this->assertEquals(3, $newrule->maxcount);
        $this->assertSame('Backed up subject', $newrule->subject);
        $this->assertEquals(1, $newrule->enabled);

        // The group reference points to the group of the restored course.
        $this->assertNotEquals($group->id, $newrule->groupid);
        $newgroup = $DB->get_record('groups', ['id' => $newrule->groupid], '*', MUST_EXIST);
        $this->assertEquals($newcourseid, $newgroup->courseid);
        $this->assertSame('Backup group', $newgroup->name);

        // The send log followed, remapped to the new rule, with the message id dropped.
        $logs = $DB->get_records('local_coursereminders_sent', ['courseid' => $newcourseid]);
        $this->assertCount(1, $logs);
        $log = reset($logs);
        $this->assertEquals($newrule->id, $log->ruleid);
        $this->assertEquals($student->id, $log->userid);
        $this->assertEquals(1, $log->occurrence);
        $this->assertNull($log->messageid);
    }

    /**
     * Without user information, the rules are restored but the send log is not.
     */
    public function test_backup_restore_without_userinfo(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, , , ] = $this->create_fixture();

        $newcourseid = $this->backup_and_restore($course, false);

        $newrule = $DB->get_record('local_coursereminders_rule', ['courseid' => $newcourseid], '*', MUST_EXIST);
        $this->assertSame('Backed up subject', $newrule->subject);
        $this->assertEquals(0, $DB->count_records('local_coursereminders_sent', ['courseid' => $newcourseid]));
    }
}
