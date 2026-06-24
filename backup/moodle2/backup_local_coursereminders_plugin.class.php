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
 * Backup support for the course reminders plugin.
 *
 * @package    local_coursereminders
 * @copyright  2026 Enovation Solutions
 * @author     Fabien Dallet <fabien.dallet@enovationsolutions.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Adds the reminder rules, and optionally the send log, to course backups.
 *
 * The rules are always included as they are part of the course configuration; the send
 * log is personal data and is only included when the backup includes user information.
 *
 * @package    local_coursereminders
 * @copyright  2026 Enovation Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_local_coursereminders_plugin extends backup_local_plugin {
    /**
     * Defines the course-level structure added to the backup.
     *
     * @return backup_plugin_element
     */
    protected function define_course_plugin_structure() {
        $plugin = $this->get_plugin_element();
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());
        $plugin->add_child($pluginwrapper);

        $rules = new backup_nested_element('rules');
        $rule = new backup_nested_element('rule', ['id'], [
            'type', 'delay', 'groupid', 'enrolmethods', 'alertthreshold', 'maxcount',
            'sendertype', 'senderid', 'subject', 'body', 'bodyformat', 'enabled',
            'usermodified', 'timecreated', 'timemodified',
        ]);
        $pluginwrapper->add_child($rules);
        $rules->add_child($rule);
        $rule->set_source_table('local_coursereminders_rule', ['courseid' => backup::VAR_COURSEID]);
        $rule->annotate_ids('group', 'groupid');
        $rule->annotate_ids('user', 'senderid');
        $rule->annotate_ids('user', 'usermodified');

        $sentreminders = new backup_nested_element('sentreminders');
        $sent = new backup_nested_element('sent', ['id'], [
            'ruleid', 'userid', 'type', 'occurrence', 'timesent',
        ]);
        $pluginwrapper->add_child($sentreminders);
        $sentreminders->add_child($sent);
        if ($this->get_setting_value('users')) {
            $sent->set_source_table('local_coursereminders_sent', ['courseid' => backup::VAR_COURSEID]);
            $sent->annotate_ids('user', 'userid');
        }

        return $plugin;
    }
}
