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

namespace local_coursereminders\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for the course reminders plugin.
 *
 * The personal data stored by the plugin is the send log: one row per reminder sent to a
 * user on a course. Reminder rules are course configuration and only reference the user
 * who last modified them.
 *
 * @package    local_coursereminders
 * @copyright  2026 Enovation Solutions
 * @author     Fabien Dallet <fabien.dallet@enovationsolutions.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describes the data the plugin stores.
     *
     * @param collection $collection The collection to add to.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_coursereminders_sent', [
            'ruleid' => 'privacy:metadata:sent:ruleid',
            'courseid' => 'privacy:metadata:sent:courseid',
            'userid' => 'privacy:metadata:sent:userid',
            'type' => 'privacy:metadata:sent:type',
            'occurrence' => 'privacy:metadata:sent:occurrence',
            'timesent' => 'privacy:metadata:sent:timesent',
        ], 'privacy:metadata:sent');

        $collection->add_database_table('local_coursereminders_rule', [
            'usermodified' => 'privacy:metadata:rule:usermodified',
        ], 'privacy:metadata:rule');

        $collection->link_subsystem('core_message', 'privacy:metadata:core_message');

        return $collection;
    }

    /**
     * Returns the contexts holding personal data of a user.
     *
     * @param int $userid The user id.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_from_sql(
            "SELECT ctx.id
               FROM {local_coursereminders_sent} s
               JOIN {context} ctx ON ctx.instanceid = s.courseid AND ctx.contextlevel = :courselevel
              WHERE s.userid = :userid",
            ['courselevel' => CONTEXT_COURSE, 'userid' => $userid]
        );
        return $contextlist;
    }

    /**
     * Adds the users holding personal data in a context.
     *
     * @param userlist $userlist The user list to add to.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_course) {
            return;
        }
        $userlist->add_from_sql(
            'userid',
            'SELECT userid FROM {local_coursereminders_sent} WHERE courseid = :courseid',
            ['courseid' => $context->instanceid]
        );
    }

    /**
     * Exports the reminders sent to a user.
     *
     * @param approved_contextlist $contextlist The approved contexts to export for.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        $typenames = \local_coursereminders\rule::get_type_options();

        foreach ($contextlist as $context) {
            if (!$context instanceof \context_course) {
                continue;
            }
            $records = $DB->get_records(
                'local_coursereminders_sent',
                ['courseid' => $context->instanceid, 'userid' => $userid],
                'timesent ASC'
            );
            if (!$records) {
                continue;
            }

            $reminders = [];
            foreach ($records as $record) {
                $reminders[] = (object) [
                    'type' => $typenames[$record->type] ?? $record->type,
                    'occurrence' => (int) $record->occurrence,
                    'timesent' => transform::datetime($record->timesent),
                ];
            }

            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_coursereminders')],
                (object) ['reminders' => $reminders]
            );
        }
    }

    /**
     * Deletes the send log of every user in a context.
     *
     * @param \context $context The context to purge.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context instanceof \context_course) {
            $DB->delete_records('local_coursereminders_sent', ['courseid' => $context->instanceid]);
        }
    }

    /**
     * Deletes the send log of one user in the approved contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to purge.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist as $context) {
            if ($context instanceof \context_course) {
                $DB->delete_records(
                    'local_coursereminders_sent',
                    ['courseid' => $context->instanceid, 'userid' => $userid]
                );
            }
        }
    }

    /**
     * Deletes the send log of the approved users in a context.
     *
     * @param approved_userlist $userlist The approved users to purge.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof \context_course) {
            return;
        }
        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['courseid'] = $context->instanceid;
        $DB->delete_records_select('local_coursereminders_sent', "courseid = :courseid AND userid {$insql}", $params);
    }
}
