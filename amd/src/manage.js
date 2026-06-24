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
 * Progressive enhancement for the reminder management page.
 *
 * The bulk action form is fully functional without JavaScript; this module only adds
 * the convenience "select all" checkbox behaviour.
 *
 * @module     local_coursereminders/manage
 * @copyright  2026 Enovation Solutions
 * @author     Fabien Dallet <fabien.dallet@enovationsolutions.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Wires the select-all checkbox to the per-row checkboxes.
 */
export const init = () => {
    const form = document.getElementById('local-coursereminders-manageform');
    if (!form) {
        return;
    }
    const selectAll = form.querySelector('[data-action="selectall"]');
    if (!selectAll) {
        return;
    }
    selectAll.addEventListener('change', () => {
        form.querySelectorAll('input[name="ruleids[]"]').forEach((checkbox) => {
            checkbox.checked = selectAll.checked;
        });
    });
};
