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
 * Tests for the course reminders event observers.
 *
 * @package    local_coursereminders
 * @category   test
 * @copyright  2026 Enovation Solutions
 * @author     Fabien Dallet <fabien.dallet@enovationsolutions.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_coursereminders\observer
 */
final class observer_test extends \advanced_testcase {
    /**
     * Creates a reminder rule on a course.
     *
     * @param int $courseid The course id.
     * @param string $type The reminder type.
     * @param int $enabled Whether the rule is enabled.
     * @return rule
     */
    private function create_rule(int $courseid, string $type, int $enabled): rule {
        $rule = new rule(0, (object) [
            'courseid' => $courseid,
            'type' => $type,
            'delay' => 7,
            'subject' => 'Subject',
            'enabled' => $enabled,
        ]);
        $rule->create();
        return $rule;
    }

    /**
     * Removing the course end date disables its enabled before-course-end rules only.
     */
    public function test_removing_enddate_disables_precourseend_rules(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['enddate' => time() + 30 * DAYSECS]);
        $precourseend = $this->create_rule($course->id, rule::TYPE_PRECOURSEEND, 1);
        $inactivity = $this->create_rule($course->id, rule::TYPE_INACTIVITY, 1);

        update_course((object) ['id' => $course->id, 'enddate' => 0]);

        $this->assertEquals(0, rule::get_record(['id' => $precourseend->get('id')])->get('enabled'));
        // Other reminder types are left untouched.
        $this->assertEquals(1, rule::get_record(['id' => $inactivity->get('id')])->get('enabled'));
    }

    /**
     * Updating a course that keeps its end date leaves before-course-end rules enabled.
     */
    public function test_keeping_enddate_leaves_precourseend_rules_enabled(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['enddate' => time() + 30 * DAYSECS]);
        $rule = $this->create_rule($course->id, rule::TYPE_PRECOURSEEND, 1);

        update_course((object) ['id' => $course->id, 'fullname' => 'Renamed course']);

        $this->assertEquals(1, rule::get_record(['id' => $rule->get('id')])->get('enabled'));
    }
}
