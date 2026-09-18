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
 * Export of the sent reminders list: one row per reminder received.
 *
 * Covers the learners the on-screen list covers, detailed down to each individual reminder
 * they were sent, oldest first within each learner.
 *
 * @package    local_coursereminders
 * @copyright  2026 Enovation Solutions
 * @author     Fabien Dallet <fabien.dallet@enovationsolutions.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sent_export {
    /** @var \stdClass The course. */
    protected $course;

    /** @var \context_course The course context. */
    protected $context;

    /**
     * Constructor.
     *
     * @param \stdClass $course The course record.
     */
    public function __construct(\stdClass $course) {
        $this->course = $course;
        $this->context = \context_course::instance($course->id);
    }

    /**
     * Returns the name of the downloaded file, without its extension.
     *
     * @return string
     */
    public function get_filename(): string {
        return clean_filename(
            format_string($this->course->shortname, true, ['context' => $this->context])
                . '-' . get_string('sentlist:title', 'local_coursereminders')
        );
    }

    /**
     * Returns the exported columns.
     *
     * @return array column key => localised heading
     */
    public function get_columns(): array {
        return [
            'lastname' => get_string('lastname'),
            'firstname' => get_string('firstname'),
            'email' => get_string('email'),
            'type' => get_string('export:type', 'local_coursereminders'),
            'reminder' => get_string('export:reminder', 'local_coursereminders'),
            'date' => get_string('export:date', 'local_coursereminders'),
        ];
    }

    /**
     * Yields one row per reminder sent, ordered by learner then by send date.
     *
     * @return \Generator
     */
    public function get_rows(): \Generator {
        global $DB;

        $courseid = (int) $this->course->id;
        [$where, $params] = learners::get_sql($courseid, $this->context);
        // The learner predicate carries its own :courseid, and a named parameter cannot be reused.
        $params['sentcourseid'] = $courseid;

        $from = "FROM {local_coursereminders_sent} s
                 JOIN {user} u ON u.id = s.userid
                WHERE s.courseid = :sentcourseid AND {$where}";

        $subjects = $this->get_subjects($from, $params);
        $typenames = rule::get_type_options();
        $dateformat = get_string('strftimedatetimeshort', 'core_langconfig');

        $recordset = $DB->get_recordset_sql(
            "SELECT s.id, s.ruleid, s.type, s.timesent, u.id AS userid, u.firstname, u.lastname, u.email
             {$from}
             ORDER BY u.lastname, u.firstname, u.id, s.timesent",
            $params
        );
        foreach ($recordset as $record) {
            yield [
                'lastname' => $record->lastname,
                'firstname' => $record->firstname,
                'email' => $record->email,
                'type' => $typenames[$record->type] ?? $record->type,
                'reminder' => $subjects[$record->ruleid . ':' . $record->userid] ?? '',
                'date' => userdate($record->timesent, $dateformat),
            ];
        }
        $recordset->close();
    }

    /**
     * Returns the reminder subject as each learner received it.
     *
     * The subject is not stored on the sent record, so it is rendered from the rule as the
     * reminder log does; reminders whose rule has since been deleted have no subject left to
     * show. Rendering is done up front, once per rule and learner, because the same pair
     * repeats over the reminders of a looping rule.
     *
     * @param string $from The FROM and WHERE clauses selecting the exported reminders.
     * @param array $params The parameters of those clauses.
     * @return array "ruleid:userid" => rendered subject
     */
    protected function get_subjects(string $from, array $params): array {
        global $DB;

        $rules = [];
        foreach (rule::get_records_for_course((int) $this->course->id) as $rule) {
            $rules[$rule->get('id')] = $rule;
        }
        if (!$rules) {
            return [];
        }

        $recordset = $DB->get_recordset_sql("SELECT DISTINCT s.ruleid, s.userid {$from}", $params);
        $pairs = [];
        $userids = [];
        foreach ($recordset as $pair) {
            $pairs[] = [(int) $pair->ruleid, (int) $pair->userid];
            $userids[(int) $pair->userid] = true;
        }
        $recordset->close();

        if (!$pairs) {
            return [];
        }
        $userids = array_keys($userids);

        $engine = new reminder_engine();
        $enroldates = $engine->get_reference_enrol_dates($this->course, $userids);
        $users = $DB->get_records_list('user', 'id', $userids, '', 'id, firstname, lastname');

        $subjects = [];
        foreach ($pairs as [$ruleid, $userid]) {
            if (!isset($rules[$ruleid]) || !isset($users[$userid])) {
                continue;
            }
            $subjects[$ruleid . ':' . $userid] = $engine->render_subject(
                $rules[$ruleid],
                $this->course,
                $users[$userid],
                $enroldates[$userid] ?? null
            );
        }

        return $subjects;
    }
}
