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
 * mod_og data generator
 *
 * @package    mod_og
 * @category   test
 * @copyright  2016 Sarah Street
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


/**
 * Og module data generator class
 *
 * @package    mod_og
 * @category   test
 * @copyright  2016 Sarah Street, modified from mod/label/tests/generator/lib.php
 *             which is copyright 2013 Jerome Mouneyrac
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_og_generator extends testing_module_generator {
    /**
     * This function is based on parent::create_instance(), modified
     * to allow $mform to not be null.
     *
     * @param array|stdClass $record DB record for module being generated.
     * @param null|array $options General options for course module. Since 2.6 it is
     *     possible to omit this argument by merging options into $record
     * @return stdClass Record from module-defined table with additional field
     *     cmid (corresponding id in course_modules table)
     */
    public function create_instance($record = null, array $options = null) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/course/modlib.php');
        require_once($CFG->dirroot.'/lib/gradelib.php');
        require_once($CFG->dirroot.'/mod/og/mod_form.php');
        require_once($CFG->dirroot.'/lib/filestorage/file_storage.php');

        $this->instancecount++;

        // Merge options into record and add default values.
        $record = $this->prepare_moduleinfo_record($record, $options);

        // Retrieve the course record.
        if (!empty($record->course->id)) {
            $course = $record->course;
            $record->course = $record->course->id;
        } else {
            $course = get_course($record->course);
        }

        // Fill the name and intro with default values (if missing).
        if (empty($record->name)) {
            $record->name = get_string('pluginname', $this->get_modulename()).' '.$this->instancecount;
        }
        if (empty($record->introeditor) && empty($record->intro)) {
            $record->intro = 'Test '.$this->get_modulename().' ' . $this->instancecount;
        }
        if (empty($record->introeditor) && empty($record->introformat)) {
            $record->introformat = FORMAT_MOODLE;
        }

        // Before Moodle 2.6 it was possible to create a module with completion tracking when
        // it is not setup for course and/or site-wide. Display debugging message so it is
        // easier to trace an error in unittests.
        if ($record->completion && empty($CFG->enablecompletion)) {
            debugging('Did you forget to set $CFG->enablecompletion before generating module with completion tracking?',
                DEBUG_DEVELOPER);
        }
        if ($record->completion && empty($course->enablecompletion)) {
            debugging('Did you forget to enable completion tracking for the course ' .
                      'before generating module with completion tracking?',
                DEBUG_DEVELOPER);
        }

        // Create $mform so dummy key file can be submitted.
        global $PAGE;
        global $USER;

        $PAGE->set_url($CFG->wwwroot . '/mod/og/mod_form.php?update=1');

        $record->instance = '';

        $usercontext = context_user::instance($USER->id);
        $draftitemid = file_get_unused_draft_itemid();
        $filerecord = array('contextid' => $usercontext->id, 'component' => 'user',
            'filearea' => 'draft', 'itemid' => $draftitemid, 'filepath' => '/',
            'filename' => 'temp.docx');
        $fs = get_file_storage();
        $keyfile = $fs->create_file_from_string($filerecord, str_repeat('xyz', 90000));

        // Set $_POST and $_REQUEST to avoid problems with managing the keyfile.
        $data = array();
        $data['id'] = 0;
        $data['_qf__mod_og_mod_form'] = '1';
        $data['name'] = 'Test X';
        $data['cmidnumber'] = '';
        $data['sesskey'] = sesskey();
        $data['keyfile'] = strval($draftitemid);
        $_POST = $data;
        $_REQUEST['keyfile'] = $draftitemid;

        $record->grade = '100'; // For og/lib.php line 106.
        $record->timemodified = time();

        $mform = new mod_og_mod_form($record, $record->section, null, $course);

        // Add the module to the course.
        $moduleinfo = add_moduleinfo($record, $course, $mform);

        // Prepare object to return with additional fields cmid and keyfile draftitemid.
        $instance = $DB->get_record($this->get_modulename(), array('id' => $moduleinfo->instance), '*', MUST_EXIST);
        $instance->cmid = $moduleinfo->coursemodule;
        $instance->keyfile = strval($draftitemid);
        return $instance;
    }

}
