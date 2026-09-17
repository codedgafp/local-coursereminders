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
 * Sent reminders list.
 *
 * @package    local_coursereminders
 * @copyright  2026 Enovation Solutions
 * @author     Fabien Dallet <fabien.dallet@enovationsolutions.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_coursereminders\output\sent_list_page;

$courseid = required_param('courseid', PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$course = get_course($courseid);
require_login($course);

$context = context_course::instance($course->id);
require_capability('local/coursereminders:viewhistory', $context);

$pageurl = new moodle_url('/local/coursereminders/sentlist.php', ['courseid' => $course->id]);
if ($page) {
    $pageurl->param('page', $page);
}
$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('sentlist:title', 'local_coursereminders'));
$PAGE->set_heading($course->fullname);

$manageurl = has_capability('local/coursereminders:manage', $context)
    ? new moodle_url('/local/coursereminders/manage.php', ['courseid' => $course->id])
    : null;
$PAGE->navbar->add(get_string('pluginname', 'local_coursereminders'), $manageurl);
$PAGE->navbar->add(get_string('sentlist:title', 'local_coursereminders'));

if ($action === 'unenrol') {
    $userid = required_param('userid', PARAM_INT);
    $confirm = optional_param('confirm', 0, PARAM_BOOL);
    require_sesskey();

    $user = core_user::get_user($userid, '*', MUST_EXIST);

    // Collect the enrolment instances the current user may unenrol this user from,
    // applying the standard Moodle unenrol capabilities, as on the participants page.
    $unenrollable = [];
    $instances = enrol_get_instances($course->id, false);
    $userenrolments = $DB->get_records_sql(
        'SELECT ue.id, ue.enrolid
           FROM {user_enrolments} ue
           JOIN {enrol} e ON e.id = ue.enrolid
          WHERE e.courseid = :courseid AND ue.userid = :userid',
        ['courseid' => $course->id, 'userid' => $user->id]
    );
    foreach ($userenrolments as $userenrolment) {
        if (!isset($instances[$userenrolment->enrolid])) {
            continue;
        }
        $instance = $instances[$userenrolment->enrolid];
        $plugin = enrol_get_plugin($instance->enrol);
        if (
            $plugin && $plugin->allow_unenrol($instance)
                && has_capability('enrol/' . $instance->enrol . ':unenrol', $context)
        ) {
            $unenrollable[] = ['plugin' => $plugin, 'instance' => $instance];
        }
    }

    if (!$unenrollable) {
        throw new moodle_exception('errorcannotunenrol', 'local_coursereminders', $pageurl);
    }

    if (!$confirm) {
        $confirmurl = new moodle_url('/local/coursereminders/sentlist.php', [
            'courseid' => $course->id,
            'action' => 'unenrol',
            'userid' => $user->id,
            'confirm' => 1,
            'sesskey' => sesskey(),
        ]);
        echo $OUTPUT->header();
        echo $OUTPUT->confirm(
            get_string('confirmunenrol', 'local_coursereminders', fullname($user)),
            $confirmurl,
            $pageurl
        );
        echo $OUTPUT->footer();
        exit;
    }

    foreach ($unenrollable as $enrolment) {
        $enrolment['plugin']->unenrol_user($enrolment['instance'], $user->id);
    }
    redirect(
        $pageurl,
        get_string('notif:unenrolled', 'local_coursereminders'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$output = $PAGE->get_renderer('local_coursereminders');

echo $output->header();
echo $output->render(new sent_list_page($course, $page));
echo $output->footer();
