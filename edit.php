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
 * Create or edit a reminder rule.
 *
 * @package    local_coursereminders
 * @copyright  2026 Enovation Solutions
 * @author     Fabien Dallet <fabien.dallet@enovationsolutions.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/coursereminders/lib.php');

use core\output\notification;
use local_coursereminders\rule;
use local_coursereminders\form\rule_form;

$courseid = required_param('courseid', PARAM_INT);
$id = optional_param('id', 0, PARAM_INT);

$course = get_course($courseid);
require_login($course);

$context = context_course::instance($course->id);
require_capability('local/coursereminders:manage', $context);

$manageurl = new moodle_url('/local/coursereminders/manage.php', ['courseid' => $course->id]);
$pageurl = new moodle_url('/local/coursereminders/edit.php', ['courseid' => $course->id, 'id' => $id]);

$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_heading($course->fullname);

$rule = null;
if ($id) {
    $rule = rule::get_record(['id' => $id, 'courseid' => $course->id], MUST_EXIST);
    $PAGE->set_title(get_string('action:edit', 'local_coursereminders'));
} else {
    $PAGE->set_title(get_string('addreminder', 'local_coursereminders'));
}

$separategroups = (groups_get_course_groupmode($course) == SEPARATEGROUPS);
$groups = [];
foreach (groups_get_all_groups($course->id) as $group) {
    $groups[$group->id] = format_string($group->name, true, ['context' => $context]);
}

$mform = new rule_form($pageurl->out(false), [
    'course' => $course,
    'id' => $id,
    'separategroups' => $separategroups,
    'groups' => $groups,
    'completionconfigured' => local_coursereminders_is_completion_configured($course),
    'hasenddate' => !empty($course->enddate),
]);

if ($mform->is_cancelled()) {
    redirect($manageurl);
}

if ($rule) {
    $mform->set_data(local_coursereminders_rule_to_formdata($rule));
} else {
    $mform->set_data(local_coursereminders_new_rule_formdata($course->id));
}

if ($data = $mform->get_data()) {
    if (!empty($data->deleterule) && $rule) {
        $rule->delete();
        redirect($manageurl, get_string('notif:deleted', 'local_coursereminders'), null, notification::NOTIFY_SUCCESS);
    }

    $record = new stdClass();
    $record->courseid = $course->id;
    $record->type = $data->type;
    $record->delay = (int) ($data->delayduration / DAYSECS);
    $record->groupid = ($separategroups && !empty($data->groupid)) ? (int) $data->groupid : null;
    $record->enrolmethods = rule::enrolmethods_from_form([
        'all' => !empty($data->enrolall),
        'self' => !empty($data->enrolself),
        'manual' => !empty($data->enrolmanual),
        'other' => !empty($data->enrolother),
    ]);
    // The cap and alert badge only apply to repeating inactivity rules; the other types
    // send a single reminder, so their stored values are normalised away.
    if ($data->type === rule::TYPE_INACTIVITY) {
        $record->alertthreshold = (int) $data->alertthreshold;
        $record->maxcount = (int) $data->maxcount;
    } else {
        $record->alertthreshold = 0;
        $record->maxcount = 1;
    }
    $record->subject = $data->subject;
    $record->body = $data->body['text'];
    $record->bodyformat = $data->body['format'];
    $record->enabled = empty($data->enabled) ? 0 : 1;

    if ($rule) {
        foreach ((array) $record as $field => $value) {
            $rule->set($field, $value);
        }
        $rule->save();
    } else {
        // The sender is taken from the site default; it is not editable per rule in this form.
        // For the teacher sender type, remember the creator so the engine can prefer them.
        $config = get_config('local_coursereminders');
        $record->sendertype = !empty($config->defaultsender) ? $config->defaultsender : rule::SENDER_NOREPLY;
        $record->senderid = ($record->sendertype === rule::SENDER_TEACHER) ? (int) $USER->id : null;
        (new rule(0, $record))->create();
    }

    redirect($manageurl, get_string('notif:saved', 'local_coursereminders'), null, notification::NOTIFY_SUCCESS);
}

$messagedefaults = [];
foreach ([rule::TYPE_POSTENROL, rule::TYPE_INACTIVITY, rule::TYPE_PRECOURSEEND] as $messagetype) {
    $messagedefaults[$messagetype] = local_coursereminders_default_message($messagetype);
}

// The default message texts are too large to pass as JavaScript call arguments, so the
// configuration is exposed through a data attribute that the module reads from the DOM.
$formconfig = [
    'summaries' => [
        rule::TYPE_POSTENROL => get_string('summary:postenrol', 'local_coursereminders'),
        rule::TYPE_INACTIVITY => get_string('summary:inactivity', 'local_coursereminders'),
        rule::TYPE_PRECOURSEEND => get_string('summary:precourseend', 'local_coursereminders'),
    ],
    'defaults' => $messagedefaults,
    'canprefill' => !$id,
    'precourseendtype' => rule::TYPE_PRECOURSEEND,
    'noenddate' => empty($course->enddate),
];

$PAGE->requires->js_call_amd('local_coursereminders/reminder_form', 'init');

echo $OUTPUT->header();
echo $OUTPUT->heading($rule
    ? get_string('action:edit', 'local_coursereminders')
    : get_string('addreminder', 'local_coursereminders'));
echo html_writer::div('', 'd-none', [
    'data-region' => 'cr-form-config',
    'data-config' => json_encode($formconfig),
]);
$mform->display();
echo $OUTPUT->footer();
