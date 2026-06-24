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
 * Tests for the course reminders library functions.
 *
 * @package    local_coursereminders
 * @category   test
 * @copyright  2026 Enovation Solutions
 * @author     Fabien Dallet <fabien.dallet@enovationsolutions.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::local_coursereminders_extend_navigation_course
 * @covers     ::local_coursereminders_is_completion_configured
 * @covers     ::local_coursereminders_new_rule_formdata
 * @covers     ::local_coursereminders_rule_to_formdata
 */
final class lib_test extends \advanced_testcase {
    /**
     * Loads the plugin library, which holds plain functions that are not autoloaded.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/coursereminders/lib.php');
        parent::setUpBeforeClass();
    }

    /**
     * A user who can manage reminders gets the management entry in the course navigation.
     */
    public function test_extend_navigation_course_adds_node_with_capability(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $navigation = new \navigation_node('root');
        local_coursereminders_extend_navigation_course($navigation, $course, $context);

        $node = $navigation->find('local_coursereminders', \navigation_node::TYPE_SETTING);
        $this->assertInstanceOf(\navigation_node::class, $node);
        $this->assertSame(
            (new \moodle_url('/local/coursereminders/manage.php', ['courseid' => $course->id]))->out(),
            $node->action()->out()
        );
    }

    /**
     * A user without the manage capability does not see the management entry.
     */
    public function test_extend_navigation_course_hidden_without_capability(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $navigation = new \navigation_node('root');
        local_coursereminders_extend_navigation_course($navigation, $course, $context);

        $this->assertFalse($navigation->find('local_coursereminders', \navigation_node::TYPE_SETTING));
    }

    /**
     * Completion is reported as configured only when enabled both site-wide and on the course.
     */
    public function test_is_completion_configured(): void {
        global $CFG;
        $this->resetAfterTest();

        $CFG->enablecompletion = 1;
        $withcompletion = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $withoutcompletion = $this->getDataGenerator()->create_course(['enablecompletion' => 0]);

        $this->assertTrue(local_coursereminders_is_completion_configured($withcompletion));
        $this->assertFalse(local_coursereminders_is_completion_configured($withoutcompletion));

        // Completion disabled site-wide overrides the course setting.
        $CFG->enablecompletion = 0;
        $this->assertFalse(local_coursereminders_is_completion_configured($withcompletion));
    }

    /**
     * New-rule form data is seeded from the admin default settings.
     */
    public function test_new_rule_formdata(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        set_config('defaultdelay_postenrol', 4 * WEEKSECS, 'local_coursereminders');
        set_config('defaultalertthreshold', 6, 'local_coursereminders');
        set_config('defaultmaxcount', 5, 'local_coursereminders');
        set_config('defaultsubject_postenrol', 'Seeded subject', 'local_coursereminders');
        set_config('defaultbody_postenrol', '<p>Seeded body</p>', 'local_coursereminders');

        $data = local_coursereminders_new_rule_formdata($course->id);

        $this->assertEquals($course->id, $data['courseid']);
        $this->assertEquals(rule::TYPE_POSTENROL, $data['type']);
        $this->assertEquals(4 * WEEKSECS, $data['delayduration']);
        $this->assertEquals(6, $data['alertthreshold']);
        $this->assertEquals(5, $data['maxcount']);
        $this->assertEquals('Seeded subject', $data['subject']);
        $this->assertEquals('<p>Seeded body</p>', $data['body']['text']);
        $this->assertEquals(1, $data['enrolall']);
        $this->assertEquals(0, $data['enabled']);
    }

    /**
     * Empty default subject/body settings fall back to the language-pack templates,
     * so the form is prefilled in the current user's language.
     */
    public function test_new_rule_formdata_localised_fallback(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        set_config('defaultsubject_postenrol', '', 'local_coursereminders');
        set_config('defaultbody_postenrol', '', 'local_coursereminders');

        $data = local_coursereminders_new_rule_formdata($course->id);

        $this->assertEquals(get_string('default:subject_postenrol', 'local_coursereminders'), $data['subject']);
        $this->assertEquals(get_string('default:body_postenrol', 'local_coursereminders'), $data['body']['text']);
    }

    /**
     * An existing rule is mapped onto the form data structure.
     */
    public function test_rule_to_formdata(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $rule = new rule(0, (object) [
            'courseid' => $course->id,
            'type' => rule::TYPE_INACTIVITY,
            'delay' => 28,
            'enrolmethods' => 'self,manual',
            'alertthreshold' => 2,
            'maxcount' => 3,
            'sendertype' => rule::SENDER_NOREPLY,
            'subject' => 'Subject',
            'body' => '<p>Body</p>',
            'bodyformat' => FORMAT_HTML,
            'enabled' => 1,
        ]);
        $rule->create();

        $data = local_coursereminders_rule_to_formdata($rule);

        $this->assertEquals(rule::TYPE_INACTIVITY, $data['type']);
        $this->assertEquals(28 * DAYSECS, $data['delayduration']);
        $this->assertEquals(0, $data['enrolall']);
        $this->assertEquals(1, $data['enrolself']);
        $this->assertEquals(1, $data['enrolmanual']);
        $this->assertEquals(0, $data['enrolother']);
        $this->assertEquals('<p>Body</p>', $data['body']['text']);
        $this->assertEquals(1, $data['enabled']);
    }
}
