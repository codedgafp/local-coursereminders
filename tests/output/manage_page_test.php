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

namespace local_coursereminders\output;

use local_coursereminders\rule;

/**
 * Tests for the management page renderable.
 *
 * @package    local_coursereminders
 * @category   test
 * @copyright  2026 Enovation Solutions
 * @author     Fabien Dallet <fabien.dallet@enovationsolutions.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_coursereminders\output\manage_page
 */
final class manage_page_test extends \advanced_testcase {
    /**
     * Builds a saved rule for a course.
     *
     * @param int $courseid The course id.
     * @param array $overrides Properties to override.
     * @return rule
     */
    private function create_rule(int $courseid, array $overrides = []): rule {
        $data = (object) array_merge([
            'courseid' => $courseid,
            'type' => rule::TYPE_POSTENROL,
            'delay' => 21,
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
     * The exported context contains one row per rule with the expected display values.
     */
    public function test_export_for_template(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Group A']);

        $enabledrule = $this->create_rule($course->id, ['enabled' => 1, 'groupid' => $group->id]);
        $this->create_rule($course->id, ['type' => rule::TYPE_INACTIVITY, 'delay' => 14]);

        $rules = rule::get_records_for_course($course->id);
        $renderer = $PAGE->get_renderer('local_coursereminders');
        $data = (new manage_page($course, $rules))->export_for_template($renderer);

        $this->assertEquals($course->id, $data->courseid);
        $this->assertTrue($data->hasrules);
        $this->assertCount(2, $data->rules);
        $this->assertCount(3, $data->bulkactions);

        // Find the enabled, grouped post-enrolment rule and check its display values.
        $row = null;
        foreach ($data->rules as $candidate) {
            if ($candidate->id === (int) $enabledrule->get('id')) {
                $row = $candidate;
            }
        }
        $this->assertNotNull($row);
        $this->assertEquals(get_string('type:postenrol', 'local_coursereminders'), $row->typename);
        $this->assertEquals(get_string('delayweeks', 'local_coursereminders', 3), $row->delaylabel);
        $this->assertEquals('Group A', $row->groupname);
        $this->assertTrue($row->enabled);
        $this->assertEquals(get_string('status:enabled', 'local_coursereminders'), $row->statuslabel);
        // An enabled rule offers the "disable" toggle.
        $this->assertStringContainsString('action=disable', $row->toggleurl);
        $this->assertStringContainsString('action.php', $row->deleteurl);
    }

    /**
     * The page renders through its Mustache template without errors and includes key markup.
     */
    public function test_render(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url('/local/coursereminders/manage.php', ['courseid' => SITEID]);

        $course = $this->getDataGenerator()->create_course();
        $this->create_rule($course->id, ['enabled' => 1]);

        $renderer = $PAGE->get_renderer('local_coursereminders');
        $html = $renderer->render(new manage_page($course, rule::get_records_for_course($course->id)));

        $this->assertStringContainsString(get_string('addreminder', 'local_coursereminders'), $html);
        $this->assertStringContainsString(get_string('type:postenrol', 'local_coursereminders'), $html);
        $this->assertStringContainsString('local-coursereminders-manageform', $html);
        $this->assertStringContainsString('name="ruleids[]"', $html);
    }

    /**
     * A course with no rules reports an empty list.
     */
    public function test_export_for_template_without_rules(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $renderer = $PAGE->get_renderer('local_coursereminders');
        $data = (new manage_page($course, []))->export_for_template($renderer);

        $this->assertFalse($data->hasrules);
        $this->assertSame([], $data->rules);
    }
}
