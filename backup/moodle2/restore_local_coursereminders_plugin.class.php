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
 * Restore support for the course reminders plugin.
 *
 * @package    local_coursereminders
 * @copyright  2026 Enovation Solutions
 * @author     Fabien Dallet <fabien.dallet@enovationsolutions.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restores the reminder rules, and the send log when user data is included.
 *
 * Course, group and user references are remapped to the restored site; send log entries
 * whose user or rule cannot be mapped are skipped.
 *
 * @package    local_coursereminders
 * @copyright  2026 Enovation Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_local_coursereminders_plugin extends restore_local_plugin {
    /**
     * Defines the course-level paths handled during restore.
     *
     * @return restore_path_element[]
     */
    protected function define_course_plugin_structure() {
        return [
            new restore_path_element('coursereminders_rule', $this->get_pathfor('/rules/rule')),
            new restore_path_element('coursereminders_sent', $this->get_pathfor('/sentreminders/sent')),
        ];
    }

    /**
     * Restores one reminder rule.
     *
     * @param array|\stdClass $data The rule data from the backup file.
     * @return void
     */
    public function process_coursereminders_rule($data): void {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        unset($data->id);

        $data->courseid = $this->task->get_courseid();

        if (!empty($data->groupid)) {
            $data->groupid = $this->get_mappingid('group', $data->groupid) ?: null;
        } else {
            $data->groupid = null;
        }
        if (!empty($data->senderid)) {
            $data->senderid = $this->get_mappingid('user', $data->senderid) ?: null;
        } else {
            $data->senderid = null;
        }
        $data->usermodified = (int) $this->get_mappingid('user', $data->usermodified);

        $newid = $DB->insert_record('local_coursereminders_rule', $data);
        $this->set_mapping('coursereminders_rule', $oldid, $newid);
    }

    /**
     * Restores one send log entry.
     *
     * Only present in the backup when it includes user information.
     *
     * @param array|\stdClass $data The log data from the backup file.
     * @return void
     */
    public function process_coursereminders_sent($data): void {
        global $DB;

        $data = (object) $data;
        unset($data->id);

        $data->courseid = $this->task->get_courseid();
        $data->ruleid = (int) $this->get_mappingid('coursereminders_rule', $data->ruleid);
        $data->userid = (int) $this->get_mappingid('user', $data->userid);
        // Message ids reference the messaging tables of the source site.
        $data->messageid = null;

        if (!$data->ruleid || !$data->userid) {
            return;
        }
        $DB->insert_record('local_coursereminders_sent', $data);
    }
}
