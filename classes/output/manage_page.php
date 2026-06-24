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

use local_coursereminders\rule;
use moodle_url;
use renderer_base;
use renderable;
use templatable;

/**
 * Renderable for the course-level reminder management page.
 *
 * @package    local_coursereminders
 * @copyright  2026 Enovation Solutions
 * @author     Fabien Dallet <fabien.dallet@enovationsolutions.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manage_page implements renderable, templatable {
    /** @var \stdClass The course. */
    protected $course;

    /** @var rule[] The reminder rules configured on the course. */
    protected $rules;

    /**
     * Constructor.
     *
     * @param \stdClass $course The course record.
     * @param rule[] $rules The reminder rules to display.
     */
    public function __construct(\stdClass $course, array $rules) {
        $this->course = $course;
        $this->rules = $rules;
    }

    /**
     * Exports the data needed to render the management page template.
     *
     * @param renderer_base $output The renderer.
     * @return \stdClass
     */
    public function export_for_template(renderer_base $output): \stdClass {
        $courseid = (int) $this->course->id;

        $data = new \stdClass();
        $data->courseid = $courseid;
        $data->completionconfigured = local_coursereminders_is_completion_configured($this->course);
        $data->sesskey = sesskey();
        $data->addurl = (new moodle_url('/local/coursereminders/edit.php', ['courseid' => $courseid]))->out(false);
        $data->sentlisturl = (new moodle_url('/local/coursereminders/sentlist.php', ['courseid' => $courseid]))->out(false);
        $data->actionurl = (new moodle_url('/local/coursereminders/action.php'))->out(false);
        $data->hasrules = !empty($this->rules);
        $data->bulkactions = [
            ['value' => 'enable', 'label' => get_string('action:enable', 'local_coursereminders')],
            ['value' => 'disable', 'label' => get_string('action:disable', 'local_coursereminders')],
            ['value' => 'delete', 'label' => get_string('action:delete', 'local_coursereminders')],
        ];
        $data->rules = [];
        foreach ($this->rules as $rule) {
            $data->rules[] = $this->export_rule($rule, $courseid);
        }

        return $data;
    }

    /**
     * Builds the template context for a single rule row.
     *
     * @param rule $rule The rule.
     * @param int $courseid The course id.
     * @return \stdClass
     */
    protected function export_rule(rule $rule, int $courseid): \stdClass {
        $ruleid = (int) $rule->get('id');
        $enabled = (bool) $rule->get('enabled');

        $lastsent = $rule->get_last_sent_time();

        $row = new \stdClass();
        $row->id = $ruleid;
        $row->typename = $rule->get_type_name();
        $row->delaylabel = $rule->get_delay_label();
        $row->lastsent = $lastsent ? userdate($lastsent, get_string('strftimedatetimeshort', 'core_langconfig')) : '-';
        $row->groupname = $this->get_group_label($rule);
        $row->enabled = $enabled;
        $row->statuslabel = $enabled
            ? get_string('status:enabled', 'local_coursereminders')
            : get_string('status:disabled', 'local_coursereminders');

        $row->editurl = (new moodle_url(
            '/local/coursereminders/edit.php',
            ['courseid' => $courseid, 'id' => $ruleid]
        ))->out(false);
        $row->duplicateurl = $this->action_url('duplicate', $courseid, $ruleid);
        $row->toggleurl = $this->action_url($enabled ? 'disable' : 'enable', $courseid, $ruleid);
        $row->togglelabel = $enabled
            ? get_string('action:disable', 'local_coursereminders')
            : get_string('action:enable', 'local_coursereminders');
        $row->deleteurl = $this->action_url('delete', $courseid, $ruleid);

        return $row;
    }

    /**
     * Builds a signed single-rule action URL.
     *
     * @param string $action The action name.
     * @param int $courseid The course id.
     * @param int $ruleid The rule id.
     * @return string
     */
    protected function action_url(string $action, int $courseid, int $ruleid): string {
        return (new moodle_url('/local/coursereminders/action.php', [
            'action' => $action,
            'courseid' => $courseid,
            'id' => $ruleid,
            'sesskey' => sesskey(),
        ]))->out(false);
    }

    /**
     * Returns the group label shown for a rule.
     *
     * @param rule $rule The rule.
     * @return string
     */
    protected function get_group_label(rule $rule): string {
        $groupid = $rule->get('groupid');
        if (empty($groupid)) {
            return get_string('allgroups', 'local_coursereminders');
        }
        $group = groups_get_group($groupid);
        return $group ? format_string($group->name, true, ['context' => \context_course::instance($this->course->id)]) : '-';
    }
}
