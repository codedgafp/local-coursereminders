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

namespace local_coursereminders\output;

use plugin_renderer_base;

/**
 * Plugin renderer.
 *
 * @package    local_coursereminders
 * @copyright  2026 Enovation Solutions
 * @author     Fabien Dallet <fabien.dallet@enovationsolutions.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {
    /**
     * Renders the reminder management page.
     *
     * @param manage_page $page The renderable.
     * @return string
     */
    public function render_manage_page(manage_page $page): string {
        return $this->render_from_template('local_coursereminders/manage_page', $page->export_for_template($this));
    }

    /**
     * Renders the sent reminders list.
     *
     * @param sent_list_page $page The renderable.
     * @return string
     */
    public function render_sent_list_page(sent_list_page $page): string {
        return $this->render_from_template('local_coursereminders/sent_list_page', $page->export_for_template($this));
    }
}
