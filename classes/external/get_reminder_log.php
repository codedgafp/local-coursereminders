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
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_coursereminders\reminder_engine;
use local_coursereminders\rule;

/**
 * Returns the reminder log of one user on one course: sent and projected reminders.
 *
 * @package    local_coursereminders
 * @copyright  2026 Enovation Solutions
 * @author     Fabien Dallet <fabien.dallet@enovationsolutions.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_reminder_log extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'The course id'),
            'userid' => new external_value(PARAM_INT, 'The user id'),
        ]);
    }

    /**
     * Builds the reminder log of a user on a course.
     *
     * @param int $courseid The course id.
     * @param int $userid The user id.
     * @return array The sent and projected reminder lists.
     */
    public static function execute(int $courseid, int $userid): array {
        global $DB;

        [
            'courseid' => $courseid,
            'userid' => $userid,
        ] = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid, 'userid' => $userid]);

        $course = get_course($courseid);
        $context = \context_course::instance($course->id);
        self::validate_context($context);
        require_capability('local/coursereminders:viewhistory', $context);

        $dateformat = get_string('strftimedatetimeshort', 'core_langconfig');
        $typenames = rule::get_type_options();
        $rules = [];
        foreach (rule::get_records_for_course($course->id) as $courserule) {
            $rules[$courserule->get('id')] = $courserule;
        }

        // Render each rule subject with the real values, as in the messages themselves.
        $engine = new reminder_engine();
        $user = \core_user::get_user($userid, '*', MUST_EXIST);
        $enroldate = $engine->get_reference_enrol_date($course, $userid);
        $subjects = [];
        foreach ($rules as $ruleid => $courserule) {
            $subjects[$ruleid] = $engine->render_subject($courserule, $course, $user, $enroldate);
        }

        $sent = [];
        $records = $DB->get_records(
            'local_coursereminders_sent',
            ['courseid' => $course->id, 'userid' => $userid],
            'timesent DESC'
        );
        foreach ($records as $record) {
            $sent[] = [
                'label' => $typenames[$record->type] ?? $record->type,
                'detail' => $subjects[$record->ruleid] ?? '',
                'time' => userdate($record->timesent, $dateformat),
            ];
        }

        $now = time();
        $projected = [];
        foreach ($rules as $rule) {
            if (!$rule->get('enabled')) {
                continue;
            }
            $status = $engine->get_user_status($rule, $course, $userid, $now);
            if ($status === null) {
                continue;
            }
            $time = $engine->project_next_time($rule, $course, $status, $now);
            if ($time === null) {
                continue;
            }
            $projected[] = [
                'label' => $rule->get_type_name(),
                'detail' => $subjects[$rule->get('id')] ?? '',
                'time' => userdate($time, $dateformat),
                'sorttime' => $time,
            ];
        }
        usort($projected, function (array $a, array $b): int {
            return $a['sorttime'] <=> $b['sorttime'];
        });
        $projected = array_map(function (array $item): array {
            unset($item['sorttime']);
            return $item;
        }, $projected);

        return [
            'hassent' => !empty($sent),
            'sent' => $sent,
            'hasprojected' => !empty($projected),
            'projected' => $projected,
        ];
    }

    /**
     * Describes the return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        $entry = new external_single_structure([
            'label' => new external_value(PARAM_TEXT, 'The reminder type name'),
            'detail' => new external_value(PARAM_TEXT, 'The reminder subject'),
            'time' => new external_value(PARAM_TEXT, 'The formatted send or projection date'),
        ]);

        return new external_single_structure([
            'hassent' => new external_value(PARAM_BOOL, 'Whether reminders were already sent'),
            'sent' => new external_multiple_structure($entry, 'The reminders already sent'),
            'hasprojected' => new external_value(PARAM_BOOL, 'Whether upcoming reminders are projected'),
            'projected' => new external_multiple_structure($entry, 'The projected upcoming reminders'),
        ]);
    }
}
