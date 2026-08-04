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
 * English strings for the course reminders plugin.
 *
 * @package    local_coursereminders
 * @copyright  2026 Enovation Solutions
 * @author     Fabien Dallet <fabien.dallet@enovationsolutions.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['action:delete'] = 'Delete';
$string['action:disable'] = 'Disable';
$string['action:duplicate'] = 'Duplicate';
$string['action:edit'] = 'Edit';
$string['action:enable'] = 'Enable';
$string['action:reminderlog'] = 'View the reminder log';
$string['action:sendmessage'] = 'Send a message';
$string['action:unenrol'] = 'Unenrol from course';
$string['actionsforuser'] = 'Actions for {$a}';
$string['addreminder'] = 'Add reminder';
$string['allgroups'] = 'All participants';
$string['backtomanage'] = 'Back to reminder management';
$string['badge:inactive'] = 'Inactive';
$string['bulkactionlabel'] = 'For the selected reminders';
$string['col:actions'] = 'Actions';
$string['col:count'] = 'Number of reminders';
$string['col:delay'] = 'Delay';
$string['col:group'] = 'Group';
$string['col:lastsent'] = 'Last reminder';
$string['col:status'] = 'Status';
$string['col:type'] = 'Reminder type';
$string['col:user'] = 'Users';
$string['confirmdelete'] = 'Are you sure you want to delete this reminder? The reminders already sent will be kept in the history.';
$string['confirmdeletebulk'] = 'Are you sure you want to delete the {$a} selected reminders? The reminders already sent will be kept in the history.';
$string['confirmunenrol'] = 'Are you sure you want to unenrol {$a} from this course?';
$string['coursereminders:manage'] = 'Manage course reminders';
$string['coursereminders:send'] = 'Send an individual reminder';
$string['coursereminders:viewhistory'] = 'View the course reminder history';
$string['default:body_inactivity'] = '<p>Hello [firstname],</p><p>It looks like you have not connected to [coursename] for [delay]. To resume your training, please click the following link: [courseurl]</p><p>Kind regards,</p>';
$string['default:body_postenrol'] = '<p>Hello [firstname],</p><p>You enrolled in [coursename] [delay] ago and it looks like you have not connected yet to follow the training. To access the course, please click the following link: [courseurl]</p><p>Kind regards,</p>';
$string['default:body_precourseend'] = '<p>Hello [firstname],</p><p>The course [coursename] ends in [delay] and you have not completed it yet. To access the course, please click the following link: [courseurl]</p><p>Kind regards,</p>';
$string['default:subject_inactivity'] = 'Reminder: [coursename]';
$string['default:subject_postenrol'] = 'Reminder: [coursename]';
$string['default:subject_precourseend'] = 'Reminder: [coursename] is ending soon';
$string['delaydays'] = '{$a} day(s)';
$string['delayweeks'] = '{$a} week(s)';
$string['error:delaypositive'] = 'The delay must be at least 1 day.';
$string['error:maxcountpositive'] = 'The maximum number of reminders must be at least 1.';
$string['error:notarget'] = 'Select at least one enrolment method.';
$string['error:thresholdnonneg'] = 'The alert threshold cannot be negative.';
$string['errorcannotunenrol'] = 'You cannot unenrol this user from this course.';
$string['errornocompletionenable'] = 'Reminders cannot be enabled because course completion is not configured on this course.';
$string['errornoenddateenable'] = 'A before-course-end reminder cannot be enabled until an end date is set on this course.';
$string['errorrulenotfound'] = 'The reminder could not be found.';
$string['field:alertthreshold'] = 'Alert badge threshold';
$string['field:body'] = 'Customise the message';
$string['field:delay'] = 'Delay';
$string['field:delete'] = 'Delete';
$string['field:delete_label'] = 'Delete this reminder';
$string['field:enabled'] = 'Enabled';
$string['field:enabled_label'] = 'Enable this reminder';
$string['field:group'] = 'Target group';
$string['field:maxcount'] = 'Maximum number of reminders';
$string['field:subject'] = 'Message subject';
$string['field:summary'] = 'Summary';
$string['field:target'] = 'Recipients';
$string['field:type'] = 'Reminder type';
$string['formheading'] = 'Reminder';
$string['groupnote'] = 'The target group can only be selected when the course uses separate groups. In any other group mode the rule applies to all participants.';
$string['invalidaction'] = 'Invalid action.';
$string['managepageheading'] = 'Reminder management';
$string['managetablecaption'] = 'List of reminders configured on this course';
$string['messageprovider:reminder'] = 'Course reminders';
$string['nobulkaction'] = 'Please choose an action to apply to the selected reminders.';
$string['nobulkselection'] = 'Please select at least one reminder.';
$string['norules'] = 'No reminders have been configured on this course yet.';
$string['notif:deleted'] = 'Reminders deleted.';
$string['notif:disabled'] = 'Reminders disabled.';
$string['notif:duplicated'] = 'Reminder duplicated.';
$string['notif:enabled'] = 'Reminders enabled.';
$string['notif:saved'] = 'Reminder saved.';
$string['notif:unenrolled'] = 'User unenrolled.';
$string['placeholder:courseenddate'] = '[courseenddate]';
$string['placeholder:coursename'] = '[coursename]';
$string['placeholder:courseurl'] = '[courseurl]';
$string['placeholder:delay'] = '[delay]';
$string['placeholder:enroldate'] = '[enroldate]';
$string['placeholder:firstname'] = '[firstname]';
$string['placeholder:lastname'] = '[lastname]';
$string['placeholder:weeks'] = '[weeks]';
$string['placeholdershelp'] = 'Available placeholders: [firstname], [lastname], [delay], [coursename], [courseurl], [enroldate], [courseenddate].';
$string['pluginname'] = 'Course reminders';
$string['privacy:metadata:core_message'] = 'Reminders are sent to learners through the messaging system.';
$string['privacy:metadata:rule'] = 'Reminder rules configured on courses.';
$string['privacy:metadata:rule:usermodified'] = 'The ID of the user who last modified the reminder rule.';
$string['privacy:metadata:sent'] = 'Log of the reminders sent to each user.';
$string['privacy:metadata:sent:courseid'] = 'The ID of the course the reminder relates to.';
$string['privacy:metadata:sent:occurrence'] = 'The rank of the reminder within the rule loop.';
$string['privacy:metadata:sent:ruleid'] = 'The ID of the reminder rule that produced the message.';
$string['privacy:metadata:sent:timesent'] = 'The time the reminder was sent.';
$string['privacy:metadata:sent:type'] = 'The reminder trigger type.';
$string['privacy:metadata:sent:userid'] = 'The ID of the user the reminder was sent to.';
$string['reminderlog:nosent'] = 'No reminder has been sent to this user yet.';
$string['reminderlog:noupcoming'] = 'No upcoming reminder is currently projected for this user.';
$string['reminderlog:sentheading'] = 'Reminders sent';
$string['reminderlog:title'] = 'Reminder log for {$a}';
$string['reminderlog:upcomingheading'] = 'Upcoming reminders';
$string['rowactions'] = 'Actions for this reminder';
$string['selectallreminders'] = 'Select all reminders';
$string['selectreminder'] = 'Select this reminder';
$string['sender:noreply'] = 'No-reply user';
$string['sender:support'] = 'Support user';
$string['sender:teacher'] = 'A course teacher';
$string['sentlist:latestsend'] = 'Latest reminder sent on this course: {$a}';
$string['sentlist:nousers'] = 'No learner is currently enrolled on this course.';
$string['sentlist:title'] = 'Sent reminders list';
$string['sentlisttablecaption'] = 'List of enrolled learners and the reminders they have received';
$string['settings:batchsending'] = 'Send reminders in background batches';
$string['settings:batchsending_desc'] = 'When enabled, the scheduled task no longer sends the reminders itself: it queues them in background batches (ad hoc tasks) that cron processes over its following runs. Each reminder is checked again when its batch runs, so a learner whose situation changed in the meantime (course completed, unenrolled) is skipped. Recommended on sites with a large number of enrolled users.';
$string['settings:batchsize'] = 'Batch size';
$string['settings:batchsize_desc'] = 'Maximum number of reminders per background batch. Only used when batch sending is enabled.';
$string['settings:defaultalertthreshold'] = 'Default alert threshold';
$string['settings:defaultalertthreshold_desc'] = 'Default number of reminders above which the "inactive" alert badge is displayed next to a learner\'s reminder count. Course designers can override this per rule.';
$string['settings:defaultbody'] = 'Default message body';
$string['settings:defaultbody_desc'] = 'Starting value for the message body when a course designer creates a new reminder of this type. Leave empty to use the standard text translated into each course designer\'s language. Available placeholders: [firstname], [lastname], [delay], [coursename], [courseurl], [enroldate], [courseenddate].';
$string['settings:defaultdelay'] = 'Default delay';
$string['settings:defaultdelay_desc'] = 'Starting value for the delay, in days or weeks, when a course designer creates a new reminder of this type.';
$string['settings:defaultmaxcount'] = 'Default maximum number of reminders';
$string['settings:defaultmaxcount_desc'] = 'Starting value for the maximum number of reminders a single rule may send to a learner.';
$string['settings:defaultsender'] = 'Default sender';
$string['settings:defaultsender_desc'] = 'Identity used as the sender of reminder messages by default. With "A course teacher", messages are sent from the person who created the rule while they still teach the course, otherwise from the first teacher found in the course, falling back to the no-reply user when the course has no teacher.';
$string['settings:defaultsubject'] = 'Default subject';
$string['settings:defaultsubject_desc'] = 'Starting value for the message subject when a course designer creates a new reminder of this type. Leave empty to use the standard text translated into each course designer\'s language.';
$string['settings:generalheading'] = 'General defaults';
$string['settings:generalheading_desc'] = 'These values are used as the starting point when a course designer creates a new reminder.';
$string['settings:sendingheading'] = 'Sending';
$string['settings:sendingheading_desc'] = 'These settings control how the scheduled task dispatches the due reminders.';
$string['status:disabled'] = 'Disabled';
$string['status:enabled'] = 'Enabled';
$string['summary:inactivity'] = 'A reminder will be sent after each period of {delay} of inactivity, up to {count} time(s), until the learner connects again or the course ends.';
$string['summary:postenrol'] = 'A reminder will be sent {delay} after enrolment if the learner has not connected to the course.';
$string['summary:precourseend'] = 'A reminder will be sent {delay} before the course end date if the course is not yet complete.';
$string['target:all'] = 'All enrolled users';
$string['target:manual'] = 'Users enrolled manually';
$string['target:other'] = 'Users enrolled by another method';
$string['target:self'] = 'Users enrolled via self enrolment';
$string['task:sendreminderbatch'] = 'Send a batch of course reminders';
$string['task:sendreminders'] = 'Send course reminders';
$string['type:inactivity'] = 'Inactivity';
$string['type:postenrol'] = 'After enrolment';
$string['type:precourseend'] = 'Before course end';
$string['warning:nocompletion'] = 'Course completion is not configured on this course. Reminders cannot be enabled and none will be sent until completion tracking is turned on in the course settings.';
$string['warning:noenddate'] = 'This course has no end date. A before-course-end reminder cannot run, and cannot be enabled, until an end date is set in the course settings.';
