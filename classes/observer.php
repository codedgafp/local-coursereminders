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
 * Event observers for the course reminders plugin.
 *
 * @package    local_coursereminders
 * @copyright  2026 Enovation Solutions
 * @author     Fabien Dallet <fabien.dallet@enovationsolutions.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Disables before-course-end reminders when a course loses its end date.
     *
     * A before-course-end reminder can never fire without an end date, so when one is removed
     * from a course the matching enabled rules are disabled, mirroring the form and action
     * guards that prevent enabling them in the first place.
     *
     * @param \core\event\course_updated $event The course updated event.
     * @return void
     */
    public static function course_updated(\core\event\course_updated $event): void {
        global $DB;

        $courseid = (int) $event->courseid;
        if ((int) $DB->get_field('course', 'enddate', ['id' => $courseid]) > 0) {
            return;
        }

        $rules = rule::get_records([
            'courseid' => $courseid,
            'type' => rule::TYPE_PRECOURSEEND,
            'enabled' => 1,
        ]);
        foreach ($rules as $rule) {
            $rule->set('enabled', 0);
            $rule->save();
        }
    }
}
