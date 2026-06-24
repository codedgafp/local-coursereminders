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

namespace local_coursereminders\form;

use local_coursereminders\rule;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Creation and edition form for a reminder rule.
 *
 * @package    local_coursereminders
 * @copyright  2026 Enovation Solutions
 * @author     Fabien Dallet <fabien.dallet@enovationsolutions.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rule_form extends \moodleform {
    /**
     * Defines the form elements.
     */
    protected function definition(): void {
        $mform = $this->_form;

        $course = $this->_customdata['course'];
        $separategroups = !empty($this->_customdata['separategroups']);
        $groups = $this->_customdata['groups'];
        $isedit = !empty($this->_customdata['id']);

        $mform->addElement('hidden', 'courseid', (int) $course->id);
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('hidden', 'id', (int) ($this->_customdata['id'] ?? 0));
        $mform->setType('id', PARAM_INT);

        $mform->addElement('header', 'general', get_string('formheading', 'local_coursereminders'));

        $groupoptions = [0 => get_string('allgroups', 'local_coursereminders')] + $groups;
        $mform->addElement('select', 'groupid', get_string('field:group', 'local_coursereminders'), $groupoptions);
        $mform->setType('groupid', PARAM_INT);
        if (!$separategroups) {
            $mform->hardFreeze('groupid');
            $mform->addElement('static', 'groupnote', '', get_string('groupnote', 'local_coursereminders'));
        }

        $mform->addElement('select', 'type', get_string('field:type', 'local_coursereminders'), rule::get_type_options());
        $mform->setDefault('type', rule::TYPE_POSTENROL);

        // A before-course-end rule needs a course end date to run. The enabled checkbox is
        // gated with the standard dependency; the warning starts hidden and is revealed by the
        // module for that type, which (unlike hideIf) avoids a flash on page load. It mirrors
        // the form grid (offset label column) so it lines up with the other form elements.
        if (empty($course->enddate)) {
            $warning = \html_writer::div(
                \html_writer::div(
                    get_string('warning:noenddate', 'local_coursereminders'),
                    'alert alert-warning mb-0',
                    ['role' => 'alert']
                ),
                'col-md-9 offset-md-3'
            );
            $mform->addElement('html', \html_writer::div(
                $warning,
                'mb-3 row d-none',
                ['data-region' => 'cr-noenddate-warning']
            ));
            $mform->disabledIf('enabled', 'type', 'eq', rule::TYPE_PRECOURSEEND);
        }

        // Delay, entered in days or weeks (stored in days).
        $mform->addElement(
            'duration',
            'delayduration',
            get_string('field:delay', 'local_coursereminders'),
            ['units' => [DAYSECS, WEEKSECS], 'optional' => false]
        );
        $mform->setDefault('delayduration', WEEKSECS);

        // Live summary, populated client-side from the values above.
        $mform->addElement(
            'static',
            'summary',
            get_string('field:summary', 'local_coursereminders'),
            \html_writer::span('', '', ['data-region' => 'cr-summary'])
        );

        $mform->addElement(
            'advcheckbox',
            'enrolall',
            get_string('field:target', 'local_coursereminders'),
            get_string('target:all', 'local_coursereminders')
        );
        $mform->addElement('advcheckbox', 'enrolself', '', get_string('target:self', 'local_coursereminders'));
        $mform->addElement('advcheckbox', 'enrolmanual', '', get_string('target:manual', 'local_coursereminders'));
        $mform->addElement('advcheckbox', 'enrolother', '', get_string('target:other', 'local_coursereminders'));

        // The alert badge and the reminder cap only apply to inactivity rules, which repeat;
        // the other types send a single reminder, so these fields are hidden for them.
        $mform->addElement('text', 'alertthreshold', get_string('field:alertthreshold', 'local_coursereminders'), ['size' => 4]);
        $mform->setType('alertthreshold', PARAM_INT);
        $mform->hideIf('alertthreshold', 'type', 'neq', rule::TYPE_INACTIVITY);

        $mform->addElement('text', 'maxcount', get_string('field:maxcount', 'local_coursereminders'), ['size' => 4]);
        $mform->setType('maxcount', PARAM_INT);
        $mform->hideIf('maxcount', 'type', 'neq', rule::TYPE_INACTIVITY);

        $mform->addElement('text', 'subject', get_string('field:subject', 'local_coursereminders'), ['size' => 60]);
        $mform->setType('subject', PARAM_TEXT);

        $mform->addElement(
            'editor',
            'body',
            get_string('field:body', 'local_coursereminders'),
            null,
            ['maxfiles' => 0, 'context' => \context_course::instance($course->id)]
        );
        $mform->setType('body', PARAM_RAW);
        $mform->addElement('static', 'placeholdershelp', '', get_string('placeholdershelp', 'local_coursereminders'));

        $mform->addElement(
            'advcheckbox',
            'enabled',
            get_string('field:enabled', 'local_coursereminders'),
            get_string('field:enabled_label', 'local_coursereminders')
        );

        if ($isedit) {
            $mform->addElement(
                'advcheckbox',
                'deleterule',
                get_string('field:delete', 'local_coursereminders'),
                get_string('field:delete_label', 'local_coursereminders')
            );
        }

        $submitlabel = $isedit
            ? get_string('savechanges')
            : get_string('addreminder', 'local_coursereminders');
        $this->add_action_buttons(true, $submitlabel);
    }

    /**
     * Server-side validation.
     *
     * @param array $data The submitted data.
     * @param array $files The submitted files.
     * @return array Validation errors keyed by element name.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        if (!empty($data['deleterule'])) {
            return $errors;
        }

        if (trim((string) ($data['subject'] ?? '')) === '') {
            $errors['subject'] = get_string('required');
        }
        // The duration element exports its value as a number of seconds.
        $delayseconds = (int) ($data['delayduration'] ?? 0);
        if ($delayseconds < DAYSECS) {
            $errors['delayduration'] = get_string('error:delaypositive', 'local_coursereminders');
        }
        // The cap and alert badge are only meaningful for repeating inactivity rules.
        if (($data['type'] ?? '') === rule::TYPE_INACTIVITY) {
            if ((int) ($data['maxcount'] ?? 0) < 1) {
                $errors['maxcount'] = get_string('error:maxcountpositive', 'local_coursereminders');
            }
            if ((int) ($data['alertthreshold'] ?? 0) < 0) {
                $errors['alertthreshold'] = get_string('error:thresholdnonneg', 'local_coursereminders');
            }
        }

        $hastarget = !empty($data['enrolall']) || !empty($data['enrolself'])
            || !empty($data['enrolmanual']) || !empty($data['enrolother']);
        if (!$hastarget) {
            $errors['enrolall'] = get_string('error:notarget', 'local_coursereminders');
        }

        if (!empty($data['enabled']) && empty($this->_customdata['completionconfigured'])) {
            $errors['enabled'] = get_string('errornocompletionenable', 'local_coursereminders');
        }

        $isprecourseend = ($data['type'] ?? '') === rule::TYPE_PRECOURSEEND;
        if (!empty($data['enabled']) && $isprecourseend && empty($this->_customdata['hasenddate'])) {
            $errors['enabled'] = get_string('errornoenddateenable', 'local_coursereminders');
        }

        return $errors;
    }
}
