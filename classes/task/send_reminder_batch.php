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

/**
 * Ad hoc task that sends one batch of queued reminders.
 *
 * @package    local_coursereminders
 * @copyright  2026 Enovation Solutions
 * @author     Fabien Dallet <fabien.dallet@enovationsolutions.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursereminders\task;

use local_coursereminders\reminder_engine;
use local_coursereminders\rule;

/**
 * Sends one batch of reminders queued by the send reminders scheduled task.
 *
 * The batch carries a rule id and a list of user ids. Every user is re-evaluated at
 * execution time through {@see reminder_engine::process_user()}, so reminders that are no
 * longer due are dropped instead of being sent stale, and duplicated batches are harmless.
 *
 * @package    local_coursereminders
 * @copyright  2026 Enovation Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_reminder_batch extends \core\task\adhoc_task {
    /**
     * Returns the localised name of the task.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task:sendreminderbatch', 'local_coursereminders');
    }

    /**
     * Executes the task.
     *
     * @return void
     */
    public function execute(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/coursereminders/lib.php');

        $data = $this->get_custom_data();
        $ruleid = (int) ($data->ruleid ?? 0);
        $userids = array_map('intval', (array) ($data->userids ?? []));
        if (!$ruleid || !$userids) {
            return;
        }

        $rule = rule::get_record(['id' => $ruleid]);
        if (!$rule || !$rule->get('enabled')) {
            mtrace("Rule {$ruleid} no longer exists or is disabled, dropping the batch.");
            return;
        }
        $course = $DB->get_record('course', ['id' => $rule->get('courseid')]);
        if (!$course || !$course->visible || !local_coursereminders_is_completion_configured($course)) {
            mtrace("Course {$rule->get('courseid')} is missing, hidden or has no completion configured, dropping the batch.");
            return;
        }

        $engine = new reminder_engine();
        $now = time();
        $sent = 0;
        foreach ($userids as $userid) {
            if ($engine->process_user($rule, $course, $userid, $now)) {
                $sent++;
            }
        }
        mtrace("Rule {$ruleid} on course {$course->id}: {$sent} of " . count($userids) . " queued reminder(s) sent.");
    }
}
