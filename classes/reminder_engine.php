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
 * Evaluates the reminder rules and dispatches the due reminder messages.
 *
 * The engine resolves the learners a rule targets, decides for each of them whether a
 * reminder is due "now", and sends the message through the Message API. Every send is
 * recorded in local_coursereminders_sent and a given (rule, user, occurrence) is sent at
 * most once, so re-running the engine on the same day never duplicates a reminder.
 *
 * @package    local_coursereminders
 * @copyright  2026 Enovation Solutions
 * @author     Fabien Dallet <fabien.dallet@enovationsolutions.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reminder_engine {
    /** The batch size used when the batch size setting is empty or invalid. */
    const DEFAULT_BATCH_SIZE = 200;

    /** The keys of the placeholders that can be used in the subject and body. */
    const PLACEHOLDERS = [
        'firstname',
        'lastname',
        'delay',
        'weeks',
        'coursename',
        'courseurl',
        'enroldate',
        'courseenddate',
    ];

    /**
     * Whether the due reminders are queued in background batches instead of sent inline.
     *
     * @return bool
     */
    public static function is_batch_sending_enabled(): bool {
        return (bool) get_config('local_coursereminders', 'batchsending');
    }

    /**
     * Returns the maximum number of reminders dispatched per background batch.
     *
     * @return int
     */
    public static function get_batch_size(): int {
        $size = (int) get_config('local_coursereminders', 'batchsize');
        return $size > 0 ? $size : self::DEFAULT_BATCH_SIZE;
    }

    /**
     * Runs every enabled rule and dispatches the due reminders.
     *
     * @param int $now The evaluation timestamp, 0 for the current time.
     * @return int The number of reminders sent, or queued when batch sending is enabled.
     */
    public function run(int $now = 0): int {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/coursereminders/lib.php');

        $now = $now ?: time();
        $totalsent = 0;

        $rules = rule::get_records(['enabled' => 1], 'courseid');
        if (!$rules) {
            mtrace('No enabled reminder rules.');
            return 0;
        }

        $courseids = array_unique(array_map(function (rule $rule): int {
            return (int) $rule->get('courseid');
        }, $rules));
        $courses = $DB->get_records_list('course', 'id', $courseids);

        foreach ($rules as $rule) {
            $courseid = $rule->get('courseid');
            $course = $courses[$courseid] ?? null;
            if (!$course) {
                mtrace("Rule {$rule->get('id')}: course {$courseid} no longer exists, skipping.");
                continue;
            }
            if (!$course->visible) {
                mtrace("Rule {$rule->get('id')}: course {$courseid} is hidden, skipping.");
                continue;
            }
            if (!local_coursereminders_is_completion_configured($course)) {
                mtrace("Rule {$rule->get('id')}: completion is not configured on course {$courseid}, skipping.");
                continue;
            }
            $sent = $this->process_rule($rule, $course, $now);
            $totalsent += $sent;
            $verb = self::is_batch_sending_enabled() ? 'queued' : 'sent';
            mtrace("Rule {$rule->get('id')} ({$rule->get('type')}) on course {$courseid}: {$sent} reminder(s) {$verb}.");
        }

        return $totalsent;
    }

    /**
     * Evaluates a single rule against its recipients and sends the due reminders.
     *
     * @param rule $rule The rule, expected enabled on a visible course with completion configured.
     * @param \stdClass $course The course the rule belongs to.
     * @param int $now The evaluation timestamp.
     * @return int The number of reminders sent, or queued when batch sending is enabled.
     */
    public function process_rule(rule $rule, \stdClass $course, int $now): int {
        global $DB;

        $recipients = $this->get_recipients($rule, $course, $now);
        if (!$recipients) {
            return 0;
        }

        $lastaccesses = $DB->get_records_menu('user_lastaccess', ['courseid' => $course->id], '', 'userid, timeaccess');
        $completions = $DB->get_records_select_menu(
            'course_completions',
            'course = :courseid AND timecompleted IS NOT NULL',
            ['courseid' => $course->id],
            '',
            'userid, timecompleted'
        );
        $sentrows = $DB->get_records('local_coursereminders_sent', ['ruleid' => $rule->get('id')], '', 'id, userid, timesent');
        $senttimes = [];
        foreach ($sentrows as $sentrow) {
            $senttimes[$sentrow->userid][] = (int) $sentrow->timesent;
        }

        $due = [];
        $refdates = [];
        foreach ($recipients as $userid => $recipient) {
            $refdate = (int) $recipient->refdate;
            $sends = $this->count_sends_since($senttimes[$userid] ?? [], $refdate);
            $status = (object) [
                'refdate' => $refdate,
                'lastaccess' => (int) ($lastaccesses[$userid] ?? 0),
                'completed' => isset($completions[$userid]),
                'sentcount' => $sends['sentcount'],
                'lastsent' => $sends['lastsent'],
            ];
            $occurrence = $this->evaluate($rule, $course, $status, $now);
            if ($occurrence !== null) {
                $due[$userid] = $occurrence;
                $refdates[$userid] = $status->refdate;
            }
        }
        if (!$due) {
            return 0;
        }

        if (self::is_batch_sending_enabled()) {
            return $this->queue_batches($rule, array_keys($due));
        }

        $users = $DB->get_records_list('user', 'id', array_keys($due));
        $sent = 0;
        foreach ($due as $userid => $occurrence) {
            if (!isset($users[$userid])) {
                continue;
            }
            if ($this->already_sent($rule, $userid, $occurrence, $refdates[$userid])) {
                continue;
            }
            if ($this->send_to_user($rule, $course, $users[$userid], $occurrence, $now, $refdates[$userid])) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Queues the due reminders of a rule as background batches.
     *
     * Each batch is an ad hoc task holding at most the configured batch size of user ids.
     * The batch re-evaluates every user at execution time, so a reminder that is no longer
     * due (course completed, learner unenrolled, rule disabled) is silently dropped, and a
     * batch queued twice cannot double-send.
     *
     * @param rule $rule The rule the reminders originate from.
     * @param int[] $userids The ids of the users a reminder is due for.
     * @return int The number of reminders queued.
     */
    protected function queue_batches(rule $rule, array $userids): int {
        foreach (array_chunk($userids, self::get_batch_size()) as $chunk) {
            $task = new task\send_reminder_batch();
            $task->set_custom_data([
                'ruleid' => (int) $rule->get('id'),
                'userids' => $chunk,
            ]);
            \core\task\manager::queue_adhoc_task($task, true);
        }
        return count($userids);
    }

    /**
     * Evaluates one learner under one rule at the current time and sends the due reminder.
     *
     * This is the send path of the background batches: the learner's status is rebuilt from
     * scratch so any change since the batch was queued (completion, unenrolment, an earlier
     * send) is taken into account before anything goes out.
     *
     * @param rule $rule The rule, expected enabled on a visible course with completion configured.
     * @param \stdClass $course The course the rule belongs to.
     * @param int $userid The user id.
     * @param int $now The evaluation and send timestamp.
     * @return bool Whether a reminder was sent.
     */
    public function process_user(rule $rule, \stdClass $course, int $userid, int $now): bool {
        $status = $this->get_user_status($rule, $course, $userid, $now);
        if (!$status) {
            return false;
        }
        $occurrence = $this->evaluate($rule, $course, $status, $now);
        if ($occurrence === null) {
            return false;
        }
        if ($this->already_sent($rule, $userid, $occurrence, $status->refdate)) {
            return false;
        }
        $user = \core_user::get_user($userid);
        if (!$user) {
            return false;
        }
        return $this->send_to_user($rule, $course, $user, $occurrence, $now, $status->refdate);
    }

    /**
     * Last-resort guard against duplicates if two runs overlap.
     *
     * Scoped to the current enrolment so a re-enrolled learner is not blocked by past sends.
     *
     * @param rule $rule The rule.
     * @param int $userid The user id.
     * @param int $occurrence The occurrence number about to be sent.
     * @param int $refdate The reference enrolment date.
     * @return bool Whether this occurrence was already sent during the current enrolment.
     */
    protected function already_sent(rule $rule, int $userid, int $occurrence, int $refdate): bool {
        global $DB;

        return $DB->record_exists_select(
            'local_coursereminders_sent',
            'ruleid = :ruleid AND userid = :userid AND occurrence = :occurrence AND timesent >= :refdate',
            [
                'ruleid' => $rule->get('id'),
                'userid' => $userid,
                'occurrence' => $occurrence,
                'refdate' => $refdate,
            ]
        );
    }

    /**
     * Counts the reminders sent on or after a reference date and the latest of them.
     *
     * Sends made before the reference enrolment date belong to a previous enrolment and
     * are ignored, so a learner who unenrolled and re-enrolled is reminded afresh.
     *
     * @param int[] $timestamps The send timestamps of the learner for the rule.
     * @param int $since The reference enrolment date.
     * @return array{sentcount: int, lastsent: int}
     */
    protected function count_sends_since(array $timestamps, int $since): array {
        $sentcount = 0;
        $lastsent = 0;
        foreach ($timestamps as $timesent) {
            if ($timesent >= $since) {
                $sentcount++;
                $lastsent = max($lastsent, $timesent);
            }
        }
        return ['sentcount' => $sentcount, 'lastsent' => $lastsent];
    }

    /**
     * Resolves the learners a rule currently targets.
     *
     * A learner is targeted when they hold an active enrolment on the course, through an
     * enabled enrolment instance whose method matches the rule, hold a learner role on the
     * course (a gradebook role, which excludes teachers and managers) and, when the rule is
     * restricted to a group, belong to that group. Suspended and deleted accounts are
     * excluded.
     *
     * @param rule $rule The rule.
     * @param \stdClass $course The course the rule belongs to.
     * @param int $now The evaluation timestamp, used to check enrolment validity windows.
     * @param int $userid Restricts the resolution to one user when set.
     * @return \stdClass[] Indexed by user id; each record exposes the userid and refdate,
     *                     the reference enrolment date (the start of the oldest active
     *                     matching enrolment).
     */
    public function get_recipients(rule $rule, \stdClass $course, int $now, int $userid = 0): array {
        global $CFG, $DB;

        $context = \context_course::instance($course->id);
        $roleids = array_filter(array_map('trim', explode(',', (string) $CFG->gradebookroles)));
        if (!$roleids) {
            return [];
        }
        [$roleinsql, $params] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, 'role');

        $params['courseid'] = $course->id;
        $params['enrolenabled'] = ENROL_INSTANCE_ENABLED;
        $params['ueactive'] = ENROL_USER_ACTIVE;
        $params['now1'] = $now;
        $params['now2'] = $now;
        $params['contextid'] = $context->id;

        $methodwhere = '';
        $methods = $rule->get_enrol_methods();
        if ($methods) {
            $conditions = [];
            foreach ($methods as $i => $token) {
                if ($token === 'other') {
                    $conditions[] = "(e.enrol <> 'self' AND e.enrol <> 'manual')";
                } else {
                    $conditions[] = 'e.enrol = :method' . $i;
                    $params['method' . $i] = $token;
                }
            }
            $methodwhere = ' AND (' . implode(' OR ', $conditions) . ')';
        }

        $groupwhere = '';
        if ($rule->get('groupid')) {
            $groupwhere = ' AND EXISTS (SELECT 1
                                          FROM {groups_members} gm
                                         WHERE gm.userid = ue.userid AND gm.groupid = :groupid)';
            $params['groupid'] = $rule->get('groupid');
        }

        $userwhere = '';
        if ($userid) {
            $userwhere = ' AND ue.userid = :userid';
            $params['userid'] = $userid;
        }

        $sql = "SELECT ue.userid, MIN(CASE WHEN ue.timestart > 0 THEN ue.timestart ELSE ue.timecreated END) AS refdate
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid = :courseid AND e.status = :enrolenabled
                  JOIN {user} u ON u.id = ue.userid AND u.deleted = 0 AND u.suspended = 0
                 WHERE ue.status = :ueactive
                       AND (ue.timestart = 0 OR ue.timestart <= :now1)
                       AND (ue.timeend = 0 OR ue.timeend > :now2)
                       AND EXISTS (SELECT 1
                                     FROM {role_assignments} ra
                                    WHERE ra.userid = ue.userid AND ra.contextid = :contextid AND ra.roleid {$roleinsql})
                       {$methodwhere}
                       {$groupwhere}
                       {$userwhere}
              GROUP BY ue.userid";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Decides whether a reminder is due for one learner under one rule.
     *
     * @param rule $rule The rule.
     * @param \stdClass $course The course the rule belongs to (enddate is read for the
     *                          pre-course-end type).
     * @param \stdClass $status The learner status: refdate (reference enrolment date),
     *                          lastaccess (last course access, 0 if never), completed (bool),
     *                          sentcount (reminders already sent by this rule) and lastsent
     *                          (timestamp of the last one, 0 if none).
     * @param int $now The evaluation timestamp.
     * @return int|null The occurrence number to send now, or null when nothing is due.
     */
    public function evaluate(rule $rule, \stdClass $course, \stdClass $status, int $now): ?int {
        if (!empty($status->completed)) {
            return null;
        }
        $delay = $rule->get('delay') * DAYSECS;

        switch ($rule->get('type')) {
            case rule::TYPE_POSTENROL:
                if ($status->sentcount >= 1) {
                    return null;
                }
                if ($status->lastaccess > 0 && $status->lastaccess >= $status->refdate) {
                    return null;
                }
                return ($now >= $status->refdate + $delay) ? 1 : null;

            case rule::TYPE_INACTIVITY:
                if ($status->sentcount >= $rule->get('maxcount')) {
                    return null;
                }
                // A learner who has not accessed the course since enrolment is handled by
                // the after-enrolment rule, not treated as inactive.
                if ($status->lastaccess < $status->refdate) {
                    return null;
                }
                if (!empty($course->enddate) && $now >= $course->enddate) {
                    return null;
                }
                // Each access, and each reminder, restarts the inactivity countdown.
                $basis = max($status->lastaccess, $status->lastsent);
                return ($now >= $basis + $delay) ? $status->sentcount + 1 : null;

            case rule::TYPE_PRECOURSEEND:
                if ($status->sentcount >= 1 || empty($course->enddate)) {
                    return null;
                }
                return ($now >= $course->enddate - $delay && $now < $course->enddate) ? 1 : null;
        }

        return null;
    }

    /**
     * Projects when the next reminder of a rule would be sent to one learner.
     *
     * The projection applies the same gates as {@see evaluate()} but returns the time the
     * next occurrence becomes due, based on the current enrolment and activity state. A
     * reminder already overdue is projected at the evaluation time, as it will go out on
     * the next task run.
     *
     * @param rule $rule The rule.
     * @param \stdClass $course The course the rule belongs to.
     * @param \stdClass $status The learner status, as for {@see evaluate()}.
     * @param int $now The evaluation timestamp.
     * @return int|null The projected send time, or null when no further send is expected.
     */
    public function project_next_time(rule $rule, \stdClass $course, \stdClass $status, int $now): ?int {
        if (!empty($status->completed)) {
            return null;
        }
        $delay = $rule->get('delay') * DAYSECS;

        switch ($rule->get('type')) {
            case rule::TYPE_POSTENROL:
                if ($status->sentcount >= 1) {
                    return null;
                }
                if ($status->lastaccess > 0 && $status->lastaccess >= $status->refdate) {
                    return null;
                }
                return max($status->refdate + $delay, $now);

            case rule::TYPE_INACTIVITY:
                if ($status->sentcount >= $rule->get('maxcount')) {
                    return null;
                }
                // A learner who has not accessed the course since enrolment is handled by
                // the after-enrolment rule, not treated as inactive.
                if ($status->lastaccess < $status->refdate) {
                    return null;
                }
                $basis = max($status->lastaccess, $status->lastsent);
                $due = max($basis + $delay, $now);
                if (!empty($course->enddate) && $due >= $course->enddate) {
                    return null;
                }
                return $due;

            case rule::TYPE_PRECOURSEEND:
                if ($status->sentcount >= 1 || empty($course->enddate)) {
                    return null;
                }
                if ($now >= $course->enddate) {
                    return null;
                }
                return max($course->enddate - $delay, $now);
        }

        return null;
    }

    /**
     * Builds the evaluation status of one learner under one rule.
     *
     * @param rule $rule The rule.
     * @param \stdClass $course The course the rule belongs to.
     * @param int $userid The user id.
     * @param int $now The evaluation timestamp.
     * @return \stdClass|null The status consumed by {@see evaluate()} and
     *                        {@see project_next_time()}, or null when the rule does not
     *                        target this user.
     */
    public function get_user_status(rule $rule, \stdClass $course, int $userid, int $now): ?\stdClass {
        global $DB;

        $recipients = $this->get_recipients($rule, $course, $now, $userid);
        if (!isset($recipients[$userid])) {
            return null;
        }

        $lastaccess = $DB->get_field(
            'user_lastaccess',
            'timeaccess',
            ['courseid' => $course->id, 'userid' => $userid]
        );
        $completed = $DB->get_field_select(
            'course_completions',
            'timecompleted',
            'course = :courseid AND userid = :userid AND timecompleted IS NOT NULL',
            ['courseid' => $course->id, 'userid' => $userid]
        );
        $sentrows = $DB->get_records(
            'local_coursereminders_sent',
            ['ruleid' => $rule->get('id'), 'userid' => $userid],
            '',
            'id, timesent'
        );
        $timestamps = [];
        foreach ($sentrows as $sentrow) {
            $timestamps[] = (int) $sentrow->timesent;
        }
        $refdate = (int) $recipients[$userid]->refdate;
        $sends = $this->count_sends_since($timestamps, $refdate);

        return (object) [
            'refdate' => $refdate,
            'lastaccess' => (int) $lastaccess,
            'completed' => !empty($completed),
            'sentcount' => $sends['sentcount'],
            'lastsent' => $sends['lastsent'],
        ];
    }

    /**
     * Sends one reminder message and records it in the send log.
     *
     * @param rule $rule The rule the reminder originates from.
     * @param \stdClass $course The course the rule belongs to.
     * @param \stdClass $user The recipient user record.
     * @param int $occurrence The occurrence number being sent.
     * @param int $now The send timestamp.
     * @param int $enroldate The reference enrolment date used to evaluate the reminder.
     * @return bool Whether the message was accepted by the Message API.
     */
    public function send_to_user(
        rule $rule,
        \stdClass $course,
        \stdClass $user,
        int $occurrence,
        int $now,
        int $enroldate = 0
    ): bool {
        global $DB;

        $context = \context_course::instance($course->id);
        $courseurl = (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
        $coursename = format_string($course->fullname, true, ['context' => $context, 'escape' => false]);
        $enroldatetext = $this->format_date($enroldate);
        $enddatetext = $this->format_date((int) ($course->enddate ?? 0));

        $plainvalues = [
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'delay' => $rule->get_delay_label(),
            'weeks' => $rule->get_delay_weeks(),
            'coursename' => $coursename,
            'courseurl' => $courseurl,
            'enroldate' => $enroldatetext,
            'courseenddate' => $enddatetext,
        ];
        $htmlvalues = [
            'firstname' => s($user->firstname),
            'lastname' => s($user->lastname),
            'delay' => $rule->get_delay_label(),
            'weeks' => $rule->get_delay_weeks(),
            'coursename' => s($coursename),
            'courseurl' => \html_writer::link($courseurl, $courseurl),
            'enroldate' => s($enroldatetext),
            'courseenddate' => s($enddatetext),
        ];

        $subject = $this->render_placeholders((string) $rule->get('subject'), $plainvalues);
        $bodyhtml = format_text(
            $this->render_placeholders((string) $rule->get('body'), $htmlvalues),
            $rule->get('bodyformat'),
            ['context' => $context]
        );

        $message = new \core\message\message();
        $message->component = 'local_coursereminders';
        $message->name = 'reminder';
        $message->userfrom = $this->get_sender($rule);
        $message->userto = $user;
        $message->subject = $subject;
        $message->fullmessage = html_to_text($bodyhtml);
        $message->fullmessageformat = FORMAT_HTML;
        $message->fullmessagehtml = $bodyhtml;
        $message->smallmessage = $subject;
        $message->notification = 1;
        $message->courseid = $course->id;
        $message->contexturl = $courseurl;
        $message->contexturlname = $coursename;

        $messageid = message_send($message);
        if (!$messageid) {
            mtrace("  Failed to send reminder to user {$user->id} (rule {$rule->get('id')}), will retry on the next run.");
            return false;
        }

        $DB->insert_record('local_coursereminders_sent', (object) [
            'ruleid' => $rule->get('id'),
            'courseid' => $course->id,
            'userid' => $user->id,
            'type' => $rule->get('type'),
            'occurrence' => $occurrence,
            'timesent' => $now,
            'messageid' => $messageid,
        ]);

        return true;
    }

    /**
     * Formats a timestamp as a date placeholder value, or an empty string when unset.
     *
     * @param int $timestamp The timestamp, or 0 when there is no date.
     * @return string
     */
    private function format_date(int $timestamp): string {
        return $timestamp > 0 ? userdate($timestamp, get_string('strftimedate', 'core_langconfig')) : '';
    }

    /**
     * Returns the reference enrolment date of a learner on a course.
     *
     * This is the earliest start of the learner's active enrolments through enabled instances,
     * the same date the reminders are evaluated against, irrespective of any rule's method
     * filter. Used to render the enrolment-date placeholder in the reminder log.
     *
     * @param \stdClass $course The course.
     * @param int $userid The user id.
     * @return int|null The reference enrolment timestamp, or null when none is active.
     */
    public function get_reference_enrol_date(\stdClass $course, int $userid): ?int {
        global $DB;

        $now = time();
        $sql = "SELECT MIN(CASE WHEN ue.timestart > 0 THEN ue.timestart ELSE ue.timecreated END)
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid = :courseid AND e.status = :enrolenabled
                 WHERE ue.userid = :userid
                       AND ue.status = :ueactive
                       AND (ue.timestart = 0 OR ue.timestart <= :now1)
                       AND (ue.timeend = 0 OR ue.timeend > :now2)";
        $refdate = $DB->get_field_sql($sql, [
            'courseid' => $course->id,
            'enrolenabled' => ENROL_INSTANCE_ENABLED,
            'userid' => $userid,
            'ueactive' => ENROL_USER_ACTIVE,
            'now1' => $now,
            'now2' => $now,
        ]);

        return ($refdate === false || $refdate === null) ? null : (int) $refdate;
    }

    /**
     * Replaces the message placeholders with their values.
     *
     * The placeholder tokens are localised strings (for example [firstname] in English and
     * [prenom] in French), so the tokens of every installed language pack are replaced; the
     * author can write the message in any of them.
     *
     * @param string $text The subject or body to render.
     * @param array $values The placeholder values, keyed by placeholder name.
     * @return string
     */
    public function render_placeholders(string $text, array $values): string {
        if ($text === '') {
            return '';
        }

        $stringman = get_string_manager();
        $langs = array_keys($stringman->get_list_of_translations(true));
        if (!in_array('en', $langs)) {
            $langs[] = 'en';
        }

        $map = [];
        foreach ($langs as $lang) {
            foreach ($values as $key => $value) {
                $token = $stringman->get_string('placeholder:' . $key, 'local_coursereminders', null, $lang);
                $map[$token] = (string) $value;
            }
        }

        return strtr($text, $map);
    }

    /**
     * Resolves the user record the reminder is sent from.
     *
     * For the teacher sender type the rule creator is preferred, but only while they still hold
     * a teaching role in the course; otherwise the first teacher found in the course is used.
     * The no-reply user is the final fallback when the course has no usable teacher.
     *
     * @param rule $rule The rule.
     * @return \stdClass
     */
    public function get_sender(rule $rule): \stdClass {
        if ($rule->get('sendertype') === rule::SENDER_SUPPORT) {
            return \core_user::get_support_user();
        }
        if ($rule->get('sendertype') === rule::SENDER_TEACHER) {
            $context = \context_course::instance($rule->get('courseid'));
            $teacherids = $this->get_course_teacher_ids($context);

            $creatorid = (int) $rule->get('senderid');
            $candidateid = ($creatorid && in_array($creatorid, $teacherids, true))
                ? $creatorid
                : (int) (reset($teacherids) ?: 0);

            if ($candidateid) {
                $teacher = \core_user::get_user($candidateid);
                if ($teacher && empty($teacher->deleted) && empty($teacher->suspended)) {
                    return $teacher;
                }
            }
        }
        return \core_user::get_noreply_user();
    }

    /**
     * Returns the ids of the users holding a teaching role in the course context.
     *
     * Editing teachers come before non-editing teachers (by role sort order); roles are matched
     * by archetype so the result is unaffected by site-specific role renaming.
     *
     * @param \context_course $context The course context.
     * @return int[] Teacher user ids, in priority order.
     */
    private function get_course_teacher_ids(\context_course $context): array {
        $roleids = [];
        foreach (['editingteacher', 'teacher'] as $archetype) {
            foreach (get_archetype_roles($archetype) as $role) {
                $roleids[(int) $role->id] = (int) $role->sortorder;
            }
        }
        asort($roleids);

        $userids = [];
        foreach (array_keys($roleids) as $roleid) {
            foreach (get_role_users($roleid, $context, true, 'u.id', 'u.id ASC') as $user) {
                $userids[(int) $user->id] = (int) $user->id;
            }
        }
        return array_values($userids);
    }
}
