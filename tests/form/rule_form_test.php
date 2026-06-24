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

namespace local_coursereminders\form;

use local_coursereminders\rule;

/**
 * Tests for the reminder rule form.
 *
 * @package    local_coursereminders
 * @category   test
 * @copyright  2026 Enovation Solutions
 * @author     Fabien Dallet <fabien.dallet@enovationsolutions.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_coursereminders\form\rule_form
 */
final class rule_form_test extends \advanced_testcase {
    /**
     * Builds a rule form bound to a course.
     *
     * @param \stdClass $course The course.
     * @param bool $completionconfigured Whether completion is configured.
     * @param bool $separategroups Whether the course is in separate groups mode.
     * @param bool $hasenddate Whether the course has an end date.
     * @return rule_form
     */
    private function make_form(
        \stdClass $course,
        bool $completionconfigured,
        bool $separategroups = false,
        bool $hasenddate = true
    ): rule_form {
        return new rule_form(new \moodle_url('/local/coursereminders/edit.php'), [
            'course' => $course,
            'id' => 0,
            'separategroups' => $separategroups,
            'groups' => [],
            'completionconfigured' => $completionconfigured,
            'hasenddate' => $hasenddate,
        ]);
    }

    /**
     * Returns a valid submission payload.
     *
     * @param int $courseid The course id.
     * @param array $overrides Values to override.
     * @return array
     */
    private function valid_data(int $courseid, array $overrides = []): array {
        return array_merge([
            'courseid' => $courseid,
            'id' => 0,
            'type' => rule::TYPE_POSTENROL,
            // Validation receives the duration element's exported value in seconds.
            'delayduration' => 3 * WEEKSECS,
            'groupid' => 0,
            'enrolall' => 1,
            'enrolself' => 0,
            'enrolmanual' => 0,
            'enrolother' => 0,
            'alertthreshold' => 3,
            'maxcount' => 2,
            'subject' => 'Subject',
            'body' => ['text' => '<p>Body</p>', 'format' => FORMAT_HTML],
            'enabled' => 0,
        ], $overrides);
    }

    /**
     * Valid data produces no validation errors.
     */
    public function test_validation_accepts_valid_data(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        $form = $this->make_form($course, true);
        $this->assertSame([], $form->validation($this->valid_data($course->id, ['enabled' => 1]), []));
    }

    /**
     * Enabling a rule without configured completion is rejected.
     */
    public function test_validation_blocks_enable_without_completion(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 0]);

        $form = $this->make_form($course, false);
        $errors = $form->validation($this->valid_data($course->id, ['enabled' => 1]), []);
        $this->assertArrayHasKey('enabled', $errors);
    }

    /**
     * A before-course-end rule cannot be enabled while the course has no end date.
     */
    public function test_validation_blocks_enable_precourseend_without_enddate(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        $form = $this->make_form($course, true, false, false);
        $errors = $form->validation($this->valid_data($course->id, [
            'type' => rule::TYPE_PRECOURSEEND,
            'enabled' => 1,
        ]), []);
        $this->assertArrayHasKey('enabled', $errors);
    }

    /**
     * A before-course-end rule can be enabled once the course has an end date.
     */
    public function test_validation_allows_enable_precourseend_with_enddate(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        $form = $this->make_form($course, true, false, true);
        $errors = $form->validation($this->valid_data($course->id, [
            'type' => rule::TYPE_PRECOURSEEND,
            'enabled' => 1,
        ]), []);
        $this->assertArrayNotHasKey('enabled', $errors);
    }

    /**
     * Out-of-range values and an empty recipient set are rejected.
     */
    public function test_validation_rejects_bad_values(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        $form = $this->make_form($course, true);
        $errors = $form->validation($this->valid_data($course->id, [
            'type' => rule::TYPE_INACTIVITY,
            'delayduration' => 0,
            'maxcount' => 0,
            'alertthreshold' => -1,
            'subject' => '',
            'enrolall' => 0,
            'enrolself' => 0,
            'enrolmanual' => 0,
            'enrolother' => 0,
        ]), []);

        $this->assertArrayHasKey('delayduration', $errors);
        $this->assertArrayHasKey('maxcount', $errors);
        $this->assertArrayHasKey('alertthreshold', $errors);
        $this->assertArrayHasKey('subject', $errors);
        $this->assertArrayHasKey('enrolall', $errors);
    }

    /**
     * One-shot types ignore the reminder cap and alert badge, so their values are not validated.
     */
    public function test_validation_skips_cap_for_oneshot_types(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        $form = $this->make_form($course, true);
        foreach ([rule::TYPE_POSTENROL, rule::TYPE_PRECOURSEEND] as $type) {
            $errors = $form->validation($this->valid_data($course->id, [
                'type' => $type,
                'maxcount' => 0,
                'alertthreshold' => -1,
            ]), []);
            $this->assertArrayNotHasKey('maxcount', $errors);
            $this->assertArrayNotHasKey('alertthreshold', $errors);
        }
    }

    /**
     * Ticking "delete" bypasses field validation.
     */
    public function test_validation_delete_bypasses_other_rules(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        $form = $this->make_form($course, true);
        $errors = $form->validation($this->valid_data($course->id, [
            'deleterule' => 1,
            'delayduration' => 0,
            'subject' => '',
            'enrolall' => 0,
            'enrolself' => 0,
            'enrolmanual' => 0,
            'enrolother' => 0,
        ]), []);
        $this->assertSame([], $errors);
    }

    /**
     * A simulated submission yields a usable data object with the expected element names.
     */
    public function test_get_data_returns_submitted_values(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        rule_form::mock_submit($this->valid_data($course->id, [
            'type' => rule::TYPE_INACTIVITY,
            'delayduration' => ['number' => 10, 'timeunit' => DAYSECS],
            'enrolall' => 0,
            'enrolself' => 1,
            'enrolmanual' => 1,
            'enrolother' => 0,
            'subject' => 'Hello',
        ]));

        $form = $this->make_form($course, true);
        $data = $form->get_data();

        $this->assertNotNull($data);
        $this->assertEquals(rule::TYPE_INACTIVITY, $data->type);
        // The duration element exports seconds; ten days were submitted.
        $this->assertEquals(10 * DAYSECS, $data->delayduration);
        $map = [
            'all' => $data->enrolall,
            'self' => $data->enrolself,
            'manual' => $data->enrolmanual,
            'other' => $data->enrolother,
        ];
        $this->assertEquals('self,manual', rule::enrolmethods_from_form($map));
    }
}
