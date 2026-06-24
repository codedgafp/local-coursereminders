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
 * Administration settings for the course reminders plugin.
 *
 * @package    local_coursereminders
 * @copyright  2026 Enovation Solutions
 * @author     Fabien Dallet <fabien.dallet@enovationsolutions.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_coursereminders', get_string('pluginname', 'local_coursereminders'));

    $settings->add(new admin_setting_heading(
        'local_coursereminders/generalheading',
        get_string('settings:generalheading', 'local_coursereminders'),
        get_string('settings:generalheading_desc', 'local_coursereminders')
    ));

    $settings->add(new admin_setting_configselect(
        'local_coursereminders/defaultsender',
        get_string('settings:defaultsender', 'local_coursereminders'),
        get_string('settings:defaultsender_desc', 'local_coursereminders'),
        'noreply',
        [
            'noreply'  => get_string('sender:noreply', 'local_coursereminders'),
            'support'  => get_string('sender:support', 'local_coursereminders'),
            'teacher'  => get_string('sender:teacher', 'local_coursereminders'),
        ]
    ));

    $settings->add(new admin_setting_configtext(
        'local_coursereminders/defaultalertthreshold',
        get_string('settings:defaultalertthreshold', 'local_coursereminders'),
        get_string('settings:defaultalertthreshold_desc', 'local_coursereminders'),
        3,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_coursereminders/defaultmaxcount',
        get_string('settings:defaultmaxcount', 'local_coursereminders'),
        get_string('settings:defaultmaxcount_desc', 'local_coursereminders'),
        3,
        PARAM_INT
    ));

    $types = [
        'postenrol'    => 3 * WEEKSECS,
        'inactivity'   => 4 * WEEKSECS,
        'precourseend' => WEEKSECS,
    ];
    foreach ($types as $type => $defaultdelay) {
        $settings->add(new admin_setting_heading(
            'local_coursereminders/heading_' . $type,
            get_string('type:' . $type, 'local_coursereminders'),
            ''
        ));

        $settings->add(new \local_coursereminders\admin\setting_delayduration(
            'local_coursereminders/defaultdelay_' . $type,
            get_string('settings:defaultdelay', 'local_coursereminders'),
            get_string('settings:defaultdelay_desc', 'local_coursereminders'),
            $defaultdelay,
            WEEKSECS
        ));

        // An empty value means the localised template from the language pack is used,
        // so the new-rule form is prefilled in each course designer's own language.
        $settings->add(new admin_setting_configtext(
            'local_coursereminders/defaultsubject_' . $type,
            get_string('settings:defaultsubject', 'local_coursereminders'),
            get_string('settings:defaultsubject_desc', 'local_coursereminders'),
            '',
            PARAM_TEXT
        ));

        $settings->add(new admin_setting_confightmleditor(
            'local_coursereminders/defaultbody_' . $type,
            get_string('settings:defaultbody', 'local_coursereminders'),
            get_string('settings:defaultbody_desc', 'local_coursereminders'),
            ''
        ));
    }

    $ADMIN->add('localplugins', $settings);
}
