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
 * Defines the learner population the sent reminders list covers.
 *
 * The on-screen list and its export must always describe the same set of learners, so both
 * build their queries from the predicate returned here.
 *
 * @package    local_coursereminders
 * @copyright  2026 Enovation Solutions
 * @author     Fabien Dallet <fabien.dallet@enovationsolutions.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class learners {
    /**
     * Returns the SQL predicate matching the learners holding an active enrolment on the course.
     *
     * A learner is a user with a gradebook role in the course context and at least one active
     * enrolment through an enabled instance, within its start and end dates.
     *
     * @param int $courseid The course id.
     * @param \context_course $context The course context.
     * @param string $useralias The alias the {user} table is queried under.
     * @return array A two-element array: the WHERE predicate, and its named parameters. The
     *               predicate is the literal string '1 = 0' when the site defines no gradebook role.
     */
    public static function get_sql(int $courseid, \context_course $context, string $useralias = 'u'): array {
        global $CFG, $DB;

        $roleids = array_filter(array_map('trim', explode(',', (string) $CFG->gradebookroles)));
        if (!$roleids) {
            return ['1 = 0', []];
        }
        [$roleinsql, $params] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, 'role');

        $now = time();
        $params['courseid'] = $courseid;
        $params['enrolenabled'] = ENROL_INSTANCE_ENABLED;
        $params['ueactive'] = ENROL_USER_ACTIVE;
        $params['now1'] = $now;
        $params['now2'] = $now;
        $params['contextid'] = $context->id;

        $where = "{$useralias}.deleted = 0 AND {$useralias}.suspended = 0
                  AND EXISTS (SELECT 1
                                FROM {user_enrolments} ue
                                JOIN {enrol} e ON e.id = ue.enrolid
                               WHERE ue.userid = {$useralias}.id AND e.courseid = :courseid
                                     AND e.status = :enrolenabled
                                     AND ue.status = :ueactive
                                     AND (ue.timestart = 0 OR ue.timestart <= :now1)
                                     AND (ue.timeend = 0 OR ue.timeend > :now2))
                  AND EXISTS (SELECT 1
                                FROM {role_assignments} ra
                               WHERE ra.userid = {$useralias}.id AND ra.contextid = :contextid
                                     AND ra.roleid {$roleinsql})";

        return [$where, $params];
    }
}
