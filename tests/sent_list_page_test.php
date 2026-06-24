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

use local_coursereminders\output\sent_list_page;

/**
 * Tests for the sent reminders list renderable.
 *
 * @package    local_coursereminders
 * @category   test
 * @copyright  2026 Enovation Solutions
 * @author     Fabien Dallet <fabien.dallet@enovationsolutions.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_coursereminders\output\sent_list_page
 */
final class sent_list_page_test extends \advanced_testcase {
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
     * The export lists the learners with their reminder counts, badge and actions.
     */
    public function test_export_for_template(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $now = time();
        $generator = $this->getDataGenerator();

        $reminded = $generator->create_user(['lastname' => 'Aaa']);
        $generator->enrol_user($reminded->id, $course->id, $this->learner_roleid());
        $quiet = $generator->create_user(['lastname' => 'Bbb']);
        $generator->enrol_user($quiet->id, $course->id, $this->learner_roleid());
        $teacher = $generator->create_and_enrol($course, 'editingteacher');

        $group = $generator->create_group(['courseid' => $course->id, 'name' => 'Groupe un']);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $reminded->id]);

        $rule = new rule(0, (object) [
            'courseid' => $course->id,
            'type' => rule::TYPE_INACTIVITY,
            'delay' => 7,
            'alertthreshold' => 1,
            'maxcount' => 5,
            'subject' => 'Subject',
            'enabled' => 1,
        ]);
        $rule->create();

        foreach ([1, 2] as $occurrence) {
            $DB->insert_record('local_coursereminders_sent', (object) [
                'ruleid' => $rule->get('id'),
                'courseid' => $course->id,
                'userid' => $reminded->id,
                'type' => rule::TYPE_INACTIVITY,
                'occurrence' => $occurrence,
                'timesent' => $now - $occurrence * DAYSECS,
            ]);
        }

        $PAGE->set_url('/');
        $output = $PAGE->get_renderer('local_coursereminders');
        $page = new sent_list_page($course);
        $data = $page->export_for_template($output);

        $this->assertTrue($data->hasusers);
        $this->assertTrue($data->haslatestsend);
        $this->assertCount(2, $data->users);

        $byid = [];
        foreach ($data->users as $row) {
            $byid[$row->id] = $row;
        }
        $this->assertArrayNotHasKey((int) $teacher->id, $byid);

        $remindedrow = $byid[(int) $reminded->id];
        $this->assertEquals(2, $remindedrow->count);
        // Two reminders received with a threshold of one: the alert badge is shown.
        $this->assertTrue($remindedrow->showbadge);
        $this->assertNotEquals('-', $remindedrow->lastsent);
        $this->assertStringContainsString('Groupe un', $remindedrow->groupnames);
        $this->assertTrue($remindedrow->canunenrol);

        $quietrow = $byid[(int) $quiet->id];
        $this->assertEquals(0, $quietrow->count);
        $this->assertFalse($quietrow->showbadge);
        $this->assertSame('-', $quietrow->lastsent);
        $this->assertSame('-', $quietrow->groupnames);

        // The renderable renders through the plugin renderer.
        $html = $output->render($page);
        $this->assertStringContainsString('local-coursereminders-sentlist', $html);
        $this->assertStringContainsString(fullname($reminded), $html);
    }

    /**
     * Without enabled rules no badge is shown, whatever the count.
     */
    public function test_export_no_badge_without_enabled_rule(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, $this->learner_roleid());

        $rule = new rule(0, (object) [
            'courseid' => $course->id,
            'type' => rule::TYPE_INACTIVITY,
            'delay' => 7,
            'alertthreshold' => 0,
            'subject' => 'Subject',
            'enabled' => 0,
        ]);
        $rule->create();

        $DB->insert_record('local_coursereminders_sent', (object) [
            'ruleid' => $rule->get('id'),
            'courseid' => $course->id,
            'userid' => $student->id,
            'type' => rule::TYPE_INACTIVITY,
            'occurrence' => 1,
            'timesent' => time(),
        ]);

        $PAGE->set_url('/');
        $output = $PAGE->get_renderer('local_coursereminders');
        $data = (new sent_list_page($course))->export_for_template($output);

        $this->assertEquals(1, $data->users[0]->count);
        $this->assertFalse($data->users[0]->showbadge);
    }
}
