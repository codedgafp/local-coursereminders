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
 * Client-side behaviour for the reminder creation/edit form.
 *
 * Keeps the live summary sentence in sync with the trigger type, delay and maximum
 * count, loads the default subject and body of the selected type while creating a rule,
 * and makes the "all participants" target exclusive of the other methods.
 *
 * @module     local_coursereminders/reminder_form
 * @copyright  2026 Enovation Solutions
 * @author     Fabien Dallet <fabien.dallet@enovationsolutions.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Writes HTML into the body editor, whichever editor is active.
 *
 * @param {string} html The HTML to set.
 */
const setBodyHtml = (html) => {
    const textarea = document.getElementById('id_body');
    if (textarea) {
        textarea.value = html;
    }
    if (window.tinyMCE && window.tinyMCE.get && window.tinyMCE.get('id_body')) {
        window.tinyMCE.get('id_body').setContent(html);
    }
    const atto = textarea ? textarea.closest('[data-fieldtype="editor"]') : null;
    const editable = atto ? atto.querySelector('.editor_atto_content') : null;
    if (editable) {
        editable.innerHTML = html;
    }
};

/**
 * Initialises the form behaviour.
 *
 * Reads its configuration (summary templates, per-type default messages and whether the
 * subject and body should follow the type) from the form's data attribute.
 */
export const init = () => {
    const configEl = document.querySelector('[data-region="cr-form-config"]');
    const config = configEl ? JSON.parse(configEl.getAttribute('data-config') || '{}') : {};
    const templates = config.summaries || {};
    const defaults = config.defaults || {};
    const canPrefill = !!config.canprefill;

    const typeEl = document.getElementById('id_type');
    const numberEl = document.getElementById('id_delayduration_number');
    const unitEl = document.getElementById('id_delayduration_timeunit');
    const maxEl = document.getElementById('id_maxcount');
    const subjectEl = document.getElementById('id_subject');
    const summaryEl = document.querySelector('[data-region="cr-summary"]');

    const delayText = () => {
        const number = numberEl && numberEl.value ? numberEl.value : '0';
        const unit = unitEl && unitEl.selectedIndex >= 0 ? unitEl.options[unitEl.selectedIndex].text : '';
        return (number + ' ' + unit).trim();
    };

    const updateSummary = () => {
        if (!summaryEl || !typeEl) {
            return;
        }
        const template = templates[typeEl.value] || '';
        const count = maxEl && maxEl.value ? maxEl.value : '0';
        summaryEl.textContent = template.replace(/\{delay\}/g, delayText()).replace(/\{count\}/g, count);
    };

    [typeEl, numberEl, unitEl, maxEl].forEach((el) => {
        if (el) {
            el.addEventListener('change', updateSummary);
            el.addEventListener('keyup', updateSummary);
        }
    });
    updateSummary();

    // The enabled checkbox is gated by the standard disabledIf dependency; here we only reveal
    // the no-end-date warning for the before-course-end type. It starts hidden in the markup,
    // so toggling a class (rather than using hideIf) keeps it from flashing on page load.
    const noEndDateWarning = document.querySelector('[data-region="cr-noenddate-warning"]');
    const syncEndDateWarning = () => {
        if (!typeEl || !noEndDateWarning) {
            return;
        }
        const blocked = !!config.noenddate && typeEl.value === config.precourseendtype;
        noEndDateWarning.classList.toggle('d-none', !blocked);
    };
    if (typeEl) {
        typeEl.addEventListener('change', syncEndDateWarning);
    }
    syncEndDateWarning();

    // While creating a rule, loading a type replaces the subject and body with its defaults.
    if (canPrefill && typeEl && defaults) {
        typeEl.addEventListener('change', () => {
            const message = defaults[typeEl.value];
            if (!message) {
                return;
            }
            if (subjectEl) {
                subjectEl.value = message.subject;
            }
            setBodyHtml(message.body);
        });
    }

    // "All participants" is exclusive of the per-method targets.
    const allEl = document.getElementById('id_enrolall');
    const others = ['self', 'manual', 'other'].map((token) => {
        return document.getElementById('id_enrol' + token);
    });
    const syncTargets = () => {
        if (!allEl) {
            return;
        }
        others.forEach((checkbox) => {
            if (!checkbox) {
                return;
            }
            checkbox.disabled = allEl.checked;
            if (allEl.checked) {
                checkbox.checked = false;
            }
        });
    };
    if (allEl) {
        allEl.addEventListener('change', syncTargets);
        syncTargets();
    }
};
