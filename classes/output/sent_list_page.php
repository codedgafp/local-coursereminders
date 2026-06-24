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

namespace local_coursereminders\output;

use local_coursereminders\rule;
use moodle_url;
use renderer_base;
use renderable;
use templatable;

/**
 * Renderable for the sent reminders list.
 *
 * Lists every learner holding an active enrolment on the course, with the number of
 * reminders received, the date of the latest one, their groups and the row actions.
 *
 * @package    local_coursereminders
 * @copyright  2026 Enovation Solutions
 * @author     Fabien Dallet <fabien.dallet@enovationsolutions.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sent_list_page implements renderable, templatable {
    /** @var int The number of users shown per page. */
    const PERPAGE = 50;

    /** @var \stdClass The course. */
    protected $course;

    /** @var int The current page number, starting at 0. */
    protected $page;

    /**
     * Constructor.
     *
     * @param \stdClass $course The course record.
     * @param int $page The current page number, starting at 0.
     */
    public function __construct(\stdClass $course, int $page = 0) {
        $this->course = $course;
        $this->page = max(0, $page);
    }

    /**
     * Exports the data needed to render the sent list template.
     *
     * @param renderer_base $output The renderer.
     * @return \stdClass
     */
    public function export_for_template(renderer_base $output): \stdClass {
        global $DB;

        $courseid = (int) $this->course->id;
        $context = \context_course::instance($courseid);
        $dateformat = get_string('strftimedatetimeshort', 'core_langconfig');

        $data = new \stdClass();
        $data->courseid = $courseid;
        $data->manageurl = (new moodle_url('/local/coursereminders/manage.php', ['courseid' => $courseid]))->out(false);

        $latestsend = $DB->get_field('local_coursereminders_sent', 'MAX(timesent)', ['courseid' => $courseid]);
        $data->haslatestsend = !empty($latestsend);
        if ($data->haslatestsend) {
            $data->latestsend = get_string(
                'sentlist:latestsend',
                'local_coursereminders',
                userdate($latestsend, $dateformat)
            );
        }

        $threshold = $this->get_effective_threshold($courseid);

        [$users, $totalcount] = $this->get_users($courseid, $context);
        $data->hasusers = !empty($users);

        $sentstats = $DB->get_records_sql(
            'SELECT userid, COUNT(id) AS sentcount, MAX(timesent) AS lastsent
               FROM {local_coursereminders_sent}
              WHERE courseid = :courseid
           GROUP BY userid',
            ['courseid' => $courseid]
        );
        $groupnames = $this->get_group_names($courseid, $context);
        $unenrollable = $this->get_unenrollable_users($courseid, $context, array_keys($users));

        $data->users = [];
        foreach ($users as $user) {
            $row = new \stdClass();
            $row->id = (int) $user->id;
            $row->fullname = fullname($user);
            $row->profileurl = (new moodle_url(
                '/user/view.php',
                ['id' => $user->id, 'course' => $courseid]
            ))->out(false);

            $count = isset($sentstats[$user->id]) ? (int) $sentstats[$user->id]->sentcount : 0;
            $row->count = $count;
            $row->showbadge = ($threshold !== null && $count > $threshold);
            $row->lastsent = isset($sentstats[$user->id])
                ? userdate($sentstats[$user->id]->lastsent, $dateformat)
                : '-';
            $row->groupnames = empty($groupnames[$user->id]) ? '-' : implode(', ', $groupnames[$user->id]);

            $row->messageurl = (new moodle_url('/message/index.php', ['id' => $user->id]))->out(false);
            $row->canunenrol = !empty($unenrollable[$user->id]);
            if ($row->canunenrol) {
                $row->unenrolurl = (new moodle_url('/local/coursereminders/sentlist.php', [
                    'courseid' => $courseid,
                    'action' => 'unenrol',
                    'userid' => $user->id,
                    'sesskey' => sesskey(),
                ]))->out(false);
            }

            $data->users[] = $row;
        }

        $baseurl = new moodle_url('/local/coursereminders/sentlist.php', ['courseid' => $courseid]);
        $data->pagingbar = $output->paging_bar($totalcount, $this->page, self::PERPAGE, $baseurl);

        return $data;
    }

    /**
     * Returns the threshold above which the inactivity alert badge is displayed.
     *
     * Only inactivity rules repeat and so carry a meaningful threshold; the list shows a
     * single badge against the user's total received count, so the lowest threshold among
     * the enabled inactivity rules is used (the badge appears as soon as any of them
     * considers the learner inactive).
     *
     * @param int $courseid The course id.
     * @return int|null The threshold, or null when no enabled inactivity rule defines one.
     */
    protected function get_effective_threshold(int $courseid): ?int {
        global $DB;
        $threshold = $DB->get_field(
            'local_coursereminders_rule',
            'MIN(alertthreshold)',
            ['courseid' => $courseid, 'enabled' => 1, 'type' => rule::TYPE_INACTIVITY]
        );
        return ($threshold === false || $threshold === null) ? null : (int) $threshold;
    }

    /**
     * Returns the page of learners holding an active enrolment on the course.
     *
     * @param int $courseid The course id.
     * @param \context_course $context The course context.
     * @return array A two-element array: the user records of the page, and the total count.
     */
    protected function get_users(int $courseid, \context_course $context): array {
        global $CFG, $DB;

        $roleids = array_filter(array_map('trim', explode(',', (string) $CFG->gradebookroles)));
        if (!$roleids) {
            return [[], 0];
        }
        [$roleinsql, $params] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, 'role');

        $now = time();
        $params['courseid'] = $courseid;
        $params['enrolenabled'] = ENROL_INSTANCE_ENABLED;
        $params['ueactive'] = ENROL_USER_ACTIVE;
        $params['now1'] = $now;
        $params['now2'] = $now;
        $params['contextid'] = $context->id;

        $where = "u.deleted = 0 AND u.suspended = 0
                  AND EXISTS (SELECT 1
                                FROM {user_enrolments} ue
                                JOIN {enrol} e ON e.id = ue.enrolid
                               WHERE ue.userid = u.id AND e.courseid = :courseid AND e.status = :enrolenabled
                                     AND ue.status = :ueactive
                                     AND (ue.timestart = 0 OR ue.timestart <= :now1)
                                     AND (ue.timeend = 0 OR ue.timeend > :now2))
                  AND EXISTS (SELECT 1
                                FROM {role_assignments} ra
                               WHERE ra.userid = u.id AND ra.contextid = :contextid AND ra.roleid {$roleinsql})";

        $totalcount = $DB->count_records_sql("SELECT COUNT(u.id) FROM {user} u WHERE {$where}", $params);

        $namefields = \core_user\fields::for_name()->get_sql('u')->selects;
        $users = $DB->get_records_sql(
            "SELECT u.id{$namefields}
               FROM {user} u
              WHERE {$where}
           ORDER BY u.lastname, u.firstname, u.id",
            $params,
            $this->page * self::PERPAGE,
            self::PERPAGE
        );

        return [$users, $totalcount];
    }

    /**
     * Returns the names of the course groups each user belongs to.
     *
     * @param int $courseid The course id.
     * @param \context_course $context The course context.
     * @return array userid => array of group names.
     */
    protected function get_group_names(int $courseid, \context_course $context): array {
        global $DB;

        $rows = $DB->get_records_sql(
            'SELECT gm.id, gm.userid, g.name
               FROM {groups} g
               JOIN {groups_members} gm ON gm.groupid = g.id
              WHERE g.courseid = :courseid
           ORDER BY g.name',
            ['courseid' => $courseid]
        );

        $names = [];
        foreach ($rows as $row) {
            $names[$row->userid][] = format_string($row->name, true, ['context' => $context]);
        }
        return $names;
    }

    /**
     * Returns which of the given users the current user is allowed to unenrol.
     *
     * A user can be unenrolled when at least one of their enrolment instances allows
     * manual unenrolment and the current user holds that enrolment plugin's unenrol
     * capability, as on the course participants page.
     *
     * @param int $courseid The course id.
     * @param \context_course $context The course context.
     * @param array $userids The user ids of the page.
     * @return array userid => true for the users that can be unenrolled.
     */
    protected function get_unenrollable_users(int $courseid, \context_course $context, array $userids): array {
        global $DB;

        if (!$userids) {
            return [];
        }
        [$userinsql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'usr');
        $params['courseid'] = $courseid;

        $instances = $DB->get_records_sql(
            "SELECT ue.id, ue.userid, e.id AS instanceid, e.enrol
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = :courseid AND ue.userid {$userinsql}",
            $params
        );

        $plugins = [];
        $capabilities = [];
        $enrolinstances = enrol_get_instances($courseid, false);

        $unenrollable = [];
        foreach ($instances as $instance) {
            $name = $instance->enrol;
            if (!array_key_exists($name, $plugins)) {
                $plugins[$name] = enrol_get_plugin($name);
                $capabilities[$name] = has_capability('enrol/' . $name . ':unenrol', $context);
            }
            if (!$plugins[$name] || !$capabilities[$name]) {
                continue;
            }
            if (!isset($enrolinstances[$instance->instanceid])) {
                continue;
            }
            if ($plugins[$name]->allow_unenrol($enrolinstances[$instance->instanceid])) {
                $unenrollable[$instance->userid] = true;
            }
        }
        return $unenrollable;
    }
}
