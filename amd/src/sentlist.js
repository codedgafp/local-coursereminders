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
 * Client-side behaviour for the sent reminders list: the reminder log modal.
 *
 * @module     local_coursereminders/sentlist
 * @copyright  2026 Enovation Solutions
 * @author     Fabien Dallet <fabien.dallet@enovationsolutions.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Modal from 'core/modal';
import Notification from 'core/notification';
import Templates from 'core/templates';
import {get_string as getString} from 'core/str';

/**
 * Opens the reminder log modal for one user.
 *
 * @param {Number} courseid The course id.
 * @param {Number} userid The user id.
 * @param {String} fullname The user's full name, shown in the modal title.
 * @returns {Promise}
 */
const showReminderLog = (courseid, userid, fullname) => {
    const request = Ajax.call([{
        methodname: 'local_coursereminders_get_reminder_log',
        args: {courseid: courseid, userid: userid},
    }])[0];

    // The modal title is rendered as HTML; escape the name read from the data attribute.
    const namenode = document.createElement('div');
    namenode.textContent = fullname;

    return Promise.all([
        getString('reminderlog:title', 'local_coursereminders', namenode.innerHTML),
        request.then((data) => Templates.render('local_coursereminders/reminder_log', data)),
    ])
    .then(([title, body]) => Modal.create({title: title, body: body, large: true, removeOnClose: true}))
    .then((modal) => {
        modal.show();
        return modal;
    })
    .catch(Notification.exception);
};

/**
 * Initialises the sent list behaviour.
 *
 * @param {Number} courseid The course id.
 */
export const init = (courseid) => {
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-action="cr-reminderlog"]');
        if (!button) {
            return;
        }
        event.preventDefault();
        showReminderLog(courseid, parseInt(button.dataset.userid, 10), button.dataset.fullname);
    });
};
