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
 * Upgrade steps for the course reminders plugin.
 *
 * @package    local_coursereminders
 * @copyright  2026 Enovation Solutions
 * @author     Fabien Dallet <fabien.dallet@enovationsolutions.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrades the course reminders plugin to the current version.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool
 */
function xmldb_local_coursereminders_upgrade(int $oldversion): bool {
    if ($oldversion < 2026061100) {
        // The default subject and body settings used to be seeded with the English
        // language-pack text at install time. An empty value now means "use the
        // language-pack default in the current user's language", so clear the
        // seeded values. The plugin has not been released yet, so no site can hold
        // a deliberately customised value at this point.
        foreach (['postenrol', 'inactivity', 'precourseend'] as $type) {
            set_config('defaultsubject_' . $type, '', 'local_coursereminders');
            set_config('defaultbody_' . $type, '', 'local_coursereminders');
        }

        upgrade_plugin_savepoint(true, 2026061100, 'local', 'coursereminders');
    }

    if ($oldversion < 2026061200) {
        // The default delay settings used to be stored as a plain number of weeks. They are
        // now stored as a number of seconds so the admin can pick days or weeks, mirroring
        // the rule form. Convert any existing value from weeks to seconds.
        foreach (['postenrol', 'inactivity', 'precourseend'] as $type) {
            $weeks = get_config('local_coursereminders', 'defaultdelay_' . $type);
            if ($weeks !== false && $weeks !== '') {
                set_config('defaultdelay_' . $type, (int) $weeks * WEEKSECS, 'local_coursereminders');
            }
        }

        upgrade_plugin_savepoint(true, 2026061200, 'local', 'coursereminders');
    }

    return true;
}
