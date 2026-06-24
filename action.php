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
 * Performs management actions on reminder rules (enable, disable, duplicate, delete).
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

$courseid = required_param('courseid', PARAM_INT);
$bulk = optional_param('bulk', 0, PARAM_BOOL);

$course = get_course($courseid);
require_login($course);

$context = context_course::instance($course->id);
require_capability('local/coursereminders:manage', $context);
require_sesskey();

$returnurl = new moodle_url('/local/coursereminders/manage.php', ['courseid' => $course->id]);

if ($bulk) {
    $action = optional_param('bulkaction', '', PARAM_ALPHA);
    $ruleids = optional_param_array('ruleids', [], PARAM_INT);
    if (empty($ruleids)) {
        redirect($returnurl, get_string('nobulkselection', 'local_coursereminders'), null, notification::NOTIFY_ERROR);
    }
    if ($action === '') {
        redirect($returnurl, get_string('nobulkaction', 'local_coursereminders'), null, notification::NOTIFY_ERROR);
    }
} else {
    $action = required_param('action', PARAM_ALPHA);
    $ruleids = [required_param('id', PARAM_INT)];
}

$validactions = [
    'enable' => 'notif:enabled',
    'disable' => 'notif:disabled',
    'duplicate' => 'notif:duplicated',
    'delete' => 'notif:deleted',
];
if (!isset($validactions[$action])) {
    throw new moodle_exception('invalidaction', 'local_coursereminders');
}

$rules = [];
foreach ($ruleids as $ruleid) {
    $rule = rule::get_record(['id' => $ruleid, 'courseid' => $course->id]);
    if ($rule) {
        $rules[] = $rule;
    }
}
if (empty($rules)) {
    redirect($returnurl, get_string('errorrulenotfound', 'local_coursereminders'), null, notification::NOTIFY_ERROR);
}

if ($action === 'delete' && !optional_param('confirm', 0, PARAM_BOOL)) {
    $PAGE->set_url(new moodle_url('/local/coursereminders/action.php'));
    $PAGE->set_context($context);
    $PAGE->set_pagelayout('incourse');
    $PAGE->set_title(get_string('action:delete', 'local_coursereminders'));
    $PAGE->set_heading($course->fullname);

    $continueparams = [
        'action' => 'delete',
        'courseid' => $course->id,
        'confirm' => 1,
        'sesskey' => sesskey(),
    ];
    if ($bulk) {
        $continueparams['bulk'] = 1;
        $continueparams['bulkaction'] = 'delete';
        // Core moodle_url rejects array values; spell the ids out as indexed parameters.
        foreach (array_values($rules) as $i => $bulkrule) {
            $continueparams["ruleids[{$i}]"] = (int) $bulkrule->get('id');
        }
    } else {
        $continueparams['id'] = (int) $rules[0]->get('id');
    }
    $continueurl = new moodle_url('/local/coursereminders/action.php', $continueparams);

    $message = count($rules) > 1
        ? get_string('confirmdeletebulk', 'local_coursereminders', count($rules))
        : get_string('confirmdelete', 'local_coursereminders');

    echo $OUTPUT->header();
    echo $OUTPUT->confirm($message, $continueurl, $returnurl);
    echo $OUTPUT->footer();
    exit;
}

if ($action === 'enable' && !local_coursereminders_is_completion_configured($course)) {
    redirect($returnurl, get_string('errornocompletionenable', 'local_coursereminders'), null, notification::NOTIFY_ERROR);
}

if ($action === 'enable' && empty($course->enddate)) {
    foreach ($rules as $enablerule) {
        if ($enablerule->get('type') === rule::TYPE_PRECOURSEEND) {
            redirect($returnurl, get_string('errornoenddateenable', 'local_coursereminders'), null, notification::NOTIFY_ERROR);
        }
    }
}

foreach ($rules as $rule) {
    switch ($action) {
        case 'enable':
            $rule->set('enabled', 1);
            $rule->save();
            break;
        case 'disable':
            $rule->set('enabled', 0);
            $rule->save();
            break;
        case 'duplicate':
            $data = $rule->to_record();
            unset($data->id, $data->timecreated, $data->timemodified, $data->usermodified);
            $data->enabled = 0;
            (new rule(0, $data))->create();
            break;
        case 'delete':
            // The sent log is intentionally preserved so the history survives rule deletion.
            $rule->delete();
            break;
    }
}

redirect($returnurl, get_string($validactions[$action], 'local_coursereminders'), null, notification::NOTIFY_SUCCESS);
