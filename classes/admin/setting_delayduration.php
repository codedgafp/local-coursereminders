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

namespace local_coursereminders\admin;

/**
 * A duration setting whose unit selector is limited to days and weeks.
 *
 * Reminder delays are only meaningful in days or weeks, so this mirrors the days/weeks
 * duration element used on the rule form rather than offering hours, minutes and seconds.
 *
 * @package    local_coursereminders
 * @copyright  2026 Enovation Solutions
 * @author     Fabien Dallet <fabien.dallet@enovationsolutions.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setting_delayduration extends \admin_setting_configduration {
    /**
     * Returns the selectable units, restricted to weeks and days.
     *
     * @return array
     */
    protected static function get_units() {
        return [
            WEEKSECS => get_string('weeks'),
            DAYSECS => get_string('days'),
        ];
    }

    /**
     * Returns the duration text and unit selector, using the restricted unit list.
     *
     * @param array $data Must be of the form 'v' => xx, 'u' => xx.
     * @param string $query
     * @return string The duration field markup and its wrapping div(s).
     */
    public function output_html($data, $query = ''): string {
        global $OUTPUT;

        $default = $this->get_defaultsetting();
        if (is_number($default)) {
            $defaultinfo = self::get_duration_text($default);
        } else if (is_array($default)) {
            $defaultinfo = self::get_duration_text($default['v'] * $default['u']);
        } else {
            $defaultinfo = null;
        }

        $inputid = $this->get_id() . 'v';
        $units = self::get_units();
        $defaultunit = $this->defaultunit;

        $context = (object) [
            'id' => $this->get_id(),
            'name' => $this->get_full_name(),
            'value' => $data['v'] ?? '',
            'readonly' => $this->is_readonly(),
            'options' => array_map(function ($unit) use ($units, $data, $defaultunit) {
                return [
                    'value' => $unit,
                    'name' => $units[$unit],
                    'selected' => isset($data) && (($data['v'] == 0 && $unit == $defaultunit) || $unit == $data['u']),
                ];
            }, array_keys($units)),
        ];

        $element = $OUTPUT->render_from_template('core_admin/setting_configduration', $context);

        return format_admin_setting($this, $this->visiblename, $element, $this->description, $inputid, '', $defaultinfo, $query);
    }
}
