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
 * Scheduled task that evaluates reminder rules and dispatches messages.
 *
 * @package    local_coursereminders
 * @copyright  2026 Enovation Solutions
 * @author     Fabien Dallet <fabien.dallet@enovationsolutions.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursereminders\task;

/**
 * Walks the enabled reminder rules and sends due reminders.
 *
 * The dispatching logic is implemented by {@see \local_coursereminders\reminder_engine};
 * this class wires the engine to Moodle's task scheduler.
 *
 * @package    local_coursereminders
 * @copyright  2026 Enovation Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_reminders extends \core\task\scheduled_task {
    /**
     * Returns the localised name of the task.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task:sendreminders', 'local_coursereminders');
    }

    /**
     * Executes the task.
     *
     * @return void
     */
    public function execute(): void {
        $engine = new \local_coursereminders\reminder_engine();
        $sent = $engine->run();
        $verb = \local_coursereminders\reminder_engine::is_batch_sending_enabled() ? 'queued' : 'sent';
        mtrace("local_coursereminders: {$sent} reminder(s) {$verb} in total.");
    }
}
