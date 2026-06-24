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
 * Persistent describing a single reminder rule attached to a course.
 *
 * @package    local_coursereminders
 * @copyright  2026 Enovation Solutions
 * @author     Fabien Dallet <fabien.dallet@enovationsolutions.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rule extends \core\persistent {
    /** The table that stores reminder rules. */
    const TABLE = 'local_coursereminders_rule';

    /** Reminder fired once when the learner has not accessed the course X days after enrolment. */
    const TYPE_POSTENROL = 'postenrol';
    /** Reminder fired repeatedly while the learner stays inactive, capped by the per-rule maximum. */
    const TYPE_INACTIVITY = 'inactivity';
    /** Reminder fired once X days before the course end date when the course is not yet complete. */
    const TYPE_PRECOURSEEND = 'precourseend';

    /** Sender is the site no-reply user. */
    const SENDER_NOREPLY = 'noreply';
    /** Sender is the site support user. */
    const SENDER_SUPPORT = 'support';
    /** Sender is a specific course teacher. */
    const SENDER_TEACHER = 'teacher';

    /** Sentinel meaning the rule applies to every enrolment method. */
    const ENROLMETHODS_ALL = 'all';

    /**
     * Defines the persistent properties, mirroring the database schema.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'courseid' => [
                'type' => PARAM_INT,
            ],
            'type' => [
                'type' => PARAM_ALPHA,
                'choices' => [self::TYPE_POSTENROL, self::TYPE_INACTIVITY, self::TYPE_PRECOURSEEND],
            ],
            'delay' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'groupid' => [
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'enrolmethods' => [
                'type' => PARAM_TEXT,
                'default' => self::ENROLMETHODS_ALL,
            ],
            'alertthreshold' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'maxcount' => [
                'type' => PARAM_INT,
                'default' => 1,
            ],
            'sendertype' => [
                'type' => PARAM_ALPHA,
                'choices' => [self::SENDER_NOREPLY, self::SENDER_SUPPORT, self::SENDER_TEACHER],
                'default' => self::SENDER_NOREPLY,
            ],
            'senderid' => [
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'subject' => [
                'type' => PARAM_TEXT,
                'default' => '',
            ],
            'body' => [
                'type' => PARAM_RAW,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'bodyformat' => [
                'type' => PARAM_INT,
                'default' => FORMAT_HTML,
            ],
            'enabled' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
        ];
    }

    /**
     * Returns the available trigger types with their human readable names.
     *
     * @return array type constant => localised label
     */
    public static function get_type_options(): array {
        return [
            self::TYPE_POSTENROL => get_string('type:postenrol', 'local_coursereminders'),
            self::TYPE_INACTIVITY => get_string('type:inactivity', 'local_coursereminders'),
            self::TYPE_PRECOURSEEND => get_string('type:precourseend', 'local_coursereminders'),
        ];
    }

    /**
     * Returns the available sender identities with their human readable names.
     *
     * @return array sender constant => localised label
     */
    public static function get_sender_options(): array {
        return [
            self::SENDER_NOREPLY => get_string('sender:noreply', 'local_coursereminders'),
            self::SENDER_SUPPORT => get_string('sender:support', 'local_coursereminders'),
            self::SENDER_TEACHER => get_string('sender:teacher', 'local_coursereminders'),
        ];
    }

    /**
     * Returns the localised label of this rule's trigger type.
     *
     * @return string
     */
    public function get_type_name(): string {
        $options = self::get_type_options();
        $type = $this->get('type');
        return $options[$type] ?? $type;
    }

    /**
     * Returns the rule delay expressed in weeks (the unit used in the user interface).
     *
     * @return int
     */
    public function get_delay_weeks(): int {
        return (int) round($this->get('delay') / 7);
    }

    /**
     * Returns the localised delay label, in weeks when the delay is a whole number of
     * weeks and in days otherwise.
     *
     * @return string For example "2 week(s)" or "10 day(s)".
     */
    public function get_delay_label(): string {
        $delay = (int) $this->get('delay');
        if ($delay > 0 && $delay % 7 === 0) {
            return get_string('delayweeks', 'local_coursereminders', $delay / 7);
        }
        return get_string('delaydays', 'local_coursereminders', $delay);
    }

    /**
     * Returns the timestamp of the most recent reminder produced by this rule, or null if none.
     *
     * @return int|null
     */
    public function get_last_sent_time(): ?int {
        global $DB;
        $time = $DB->get_field('local_coursereminders_sent', 'MAX(timesent)', ['ruleid' => $this->get('id')]);
        return empty($time) ? null : (int) $time;
    }

    /**
     * Returns the enrolment method names the rule targets, or an empty array when it targets all of them.
     *
     * @return string[]
     */
    public function get_enrol_methods(): array {
        $value = trim((string) $this->get('enrolmethods'));
        if ($value === '' || $value === self::ENROLMETHODS_ALL) {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    /**
     * Returns every rule configured on a course.
     *
     * @param int $courseid The course id.
     * @return rule[] Indexed by rule id.
     */
    public static function get_records_for_course(int $courseid): array {
        return self::get_records(['courseid' => $courseid], 'type', 'ASC');
    }

    /**
     * Converts the form checkbox group into a stored enrolment-methods value.
     *
     * @param array $methods Map with the keys all, self, manual and other.
     * @return string Either the "all" sentinel or a CSV of the selected method tokens.
     */
    public static function enrolmethods_from_form(array $methods): string {
        if (!empty($methods['all'])) {
            return self::ENROLMETHODS_ALL;
        }
        $selected = [];
        foreach (['self', 'manual', 'other'] as $token) {
            if (!empty($methods[$token])) {
                $selected[] = $token;
            }
        }
        return implode(',', $selected);
    }

    /**
     * Converts a stored enrolment-methods value into the form checkbox group state.
     *
     * @param string $value Either the "all" sentinel or a CSV of method tokens.
     * @return array Map with the keys all, self, manual and other set to 0 or 1.
     */
    public static function enrolmethods_to_form(string $value): array {
        $state = ['all' => 0, 'self' => 0, 'manual' => 0, 'other' => 0];
        if (trim($value) === '' || $value === self::ENROLMETHODS_ALL) {
            $state['all'] = 1;
            return $state;
        }
        foreach (explode(',', $value) as $token) {
            $token = trim($token);
            if (isset($state[$token])) {
                $state[$token] = 1;
            }
        }
        return $state;
    }
}
