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
 * Public library functions for the course reminders plugin.
 *
 * @package    local_coursereminders
 * @copyright  2026 Enovation Solutions
 * @author     Fabien Dallet <fabien.dallet@enovationsolutions.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Adds the reminder management entry to the course navigation.
 *
 * Core auto-discovers this callback; no core files are modified.
 *
 * @param navigation_node $navigation The course navigation node.
 * @param stdClass $course The course being viewed.
 * @param context_course $context The course context.
 * @return void
 */
function local_coursereminders_extend_navigation_course(
    navigation_node $navigation,
    stdClass $course,
    context_course $context
): void {

    if (!has_capability('local/coursereminders:manage', $context)) {
        return;
    }

    $url = new moodle_url('/local/coursereminders/manage.php', ['courseid' => $course->id]);
    $navigation->add(
        get_string('pluginname', 'local_coursereminders'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'local_coursereminders',
        new pix_icon('i/scheduled', '')
    );
}

/**
 * Returns whether course completion is configured on a course.
 *
 * Reminder rules cannot be enabled, and never fire, unless completion tracking is enabled
 * both site-wide and on the course; this is the condition the scope refers to as
 * "course completion is configured".
 *
 * @param stdClass $course The course record.
 * @return bool
 */
function local_coursereminders_is_completion_configured(stdClass $course): bool {
    global $CFG;
    return !empty($CFG->enablecompletion) && !empty($course->enablecompletion);
}

/**
 * Returns the default subject and body for a reminder type.
 *
 * The site-wide template is used when set; otherwise the language-pack default is returned,
 * so the message is prefilled in the current user's language.
 *
 * @param string $type The reminder type.
 * @return array An array with 'subject' and 'body' keys.
 */
function local_coursereminders_default_message(string $type): array {
    $config = get_config('local_coursereminders');

    $subject = trim((string) ($config->{'defaultsubject_' . $type} ?? ''));
    if ($subject === '') {
        $subject = get_string('default:subject_' . $type, 'local_coursereminders');
    }
    $body = (string) ($config->{'defaultbody_' . $type} ?? '');
    if (html_is_blank($body)) {
        $body = get_string('default:body_' . $type, 'local_coursereminders');
    }

    return ['subject' => $subject, 'body' => $body];
}

/**
 * Builds the initial form data for a brand new rule, using the site default settings.
 *
 * @param int $courseid The course id.
 * @return array Form data.
 */
function local_coursereminders_new_rule_formdata(int $courseid): array {
    $config = get_config('local_coursereminders');
    $type = \local_coursereminders\rule::TYPE_POSTENROL;
    $message = local_coursereminders_default_message($type);

    return [
        'courseid' => $courseid,
        'id' => 0,
        'type' => $type,
        'delayduration' => (int) ($config->{'defaultdelay_' . $type} ?? WEEKSECS),
        'enrolall' => 1,
        'enrolself' => 0,
        'enrolmanual' => 0,
        'enrolother' => 0,
        'alertthreshold' => (int) ($config->defaultalertthreshold ?? 0),
        'maxcount' => (int) ($config->defaultmaxcount ?? 1),
        'subject' => $message['subject'],
        'body' => ['text' => $message['body'], 'format' => FORMAT_HTML],
        'enabled' => 0,
    ];
}

/**
 * Converts a stored rule into the data structure expected by the rule form.
 *
 * @param \local_coursereminders\rule $rule The rule.
 * @return array Form data.
 */
function local_coursereminders_rule_to_formdata(\local_coursereminders\rule $rule): array {
    $methods = \local_coursereminders\rule::enrolmethods_to_form((string) $rule->get('enrolmethods'));
    return [
        'id' => $rule->get('id'),
        'courseid' => $rule->get('courseid'),
        'type' => $rule->get('type'),
        'delayduration' => (int) $rule->get('delay') * DAYSECS,
        'groupid' => (int) $rule->get('groupid'),
        'enrolall' => $methods['all'],
        'enrolself' => $methods['self'],
        'enrolmanual' => $methods['manual'],
        'enrolother' => $methods['other'],
        'alertthreshold' => $rule->get('alertthreshold'),
        'maxcount' => $rule->get('maxcount'),
        'subject' => $rule->get('subject'),
        'body' => ['text' => (string) $rule->get('body'), 'format' => $rule->get('bodyformat')],
        'enabled' => $rule->get('enabled'),
    ];
}
