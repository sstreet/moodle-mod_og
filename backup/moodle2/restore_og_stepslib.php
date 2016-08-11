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
 * Define all the restore steps that will be used by the restore_og_activity_task
 *
 * @package   mod_og
 * @category  backup
 * @copyright 2016 Sarah Street
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Structure step to restore one og activity
 *
 * @copyright 2016 Sarah Street
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_og_activity_structure_step extends restore_activity_structure_step {

    /** @var int The instance id of the newly restored OG Project */
    private $newid;

    /**
     * Defines structure of path elements to be processed during the restore
     *
     * @return array of {@link restore_path_element}
     */
    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('og', '/activity/og');
        if ($userinfo) {
            $paths[] = new restore_path_element('og_num_submissions', '/activity/og/submissions/submission');
        }

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process the given restore path element data
     *
     * @param array $data parsed element data
     */
    protected function process_og($data) {
        global $DB;

        $data = (object)$data;
        $data->course = $this->get_courseid();

        if (empty($data->timecreated)) {
            $data->timecreated = time();
        }

        if (empty($data->timemodified)) {
            $data->timemodified = time();
        }

        if ($data->grade < 0) {
            // Scale found, get mapping.
            $data->grade = -($this->get_mappingid('scale', abs($data->grade)));
        }

        // Create the og instance.
        $this->newid = $DB->insert_record('og', $data);
        $this->apply_activity_instance($this->newid);
    }

    /**
     * Inserts a record in the og_num_submissions table
     *
     * @param array $data parsed element data
     */
    protected function process_og_num_submissions($data) {
        global $DB;

        $data = (object)$data;

        $data->og_id = $this->get_new_parentid('og');
        $data->user_id = $this->get_mappingid('user', $data->user_id);

        $DB->insert_record('og_num_submissions', $data);
    }

    /**
     * Post-execution actions
     */
    protected function after_execute() {
        // Add og related files, no need to match by itemname (just internally handled context).
        $this->add_related_files('mod_og', 'intro', null);

        // Create ogin and ogout folders if they don't exist.
        global $CFG;
        $path = $CFG->dataroot . '/ogin/';
        if (!file_exists($path)) {
            $mask = umask(0);
            mkdir($path, 0777);
            umask($mask);
        }
        $path = $CFG->dataroot . '/ogout/';
        if (!file_exists($path)) {
            $mask = umask(0);
            mkdir($path, 0777);
            umask($mask);
        }

        $userinfo = $this->get_setting_value('userinfo');

        // Add the key file.
        $this->add_related_files('mod_og', 'keyfiles', null);
        if ($userinfo) {
            $this->add_related_files('mod_og', 'attachment', null);
            $this->add_related_files('mod_og', 'graded', null);
        }

        // Rename file stored in Moodle.
        $this->rename_files('keyfiles');
        if ($userinfo) {
            $this->rename_files('attachment');
            $this->rename_files('graded');
        }
    }

    /**
     * Renames files in the given filearea so that the
     * filename includes the course id and cmid of the
     * newly restored OG Project.
     *
     * @param string $filearea The filearea whose files
     * need to be renamed. Must be 'keyfiles', 'graded',
     * or 'attachment'.
     */
    protected function rename_files($filearea) {
        global $CFG;

        // Get new courseid and cm for new filename.
        $cm = get_coursemodule_from_instance('og', $this->newid);
        $newcourse = $cm->course;
        $newcm = $cm->id;
        $context = context_module::instance($cm->id);

        $fs = get_file_storage();
        $oldfiles = $fs->get_area_files($context->id, 'mod_og', $filearea, false, null, false);
        if ($oldfiles) {
            foreach ($oldfiles as $oldfile) {
                $oldfilename = $oldfile->get_filename();
                if ($oldfilename !== '.') {
                    if ($filearea === 'keyfiles') {
                        // Example filename: Key 2_548 100.docx.
                        $words = explode(" ", $oldfilename);
                        $newfilename = 'Key ' . $newcourse . '_' . $newcm . ' ' . $words[2];
                    } else if ($filearea === 'attachment') {
                        // Example filename: myfile.docx.
                        break;
                    } else if ($filearea === 'graded') {
                        // Example filename: 2_548_3 97.docx.
                        $words = explode('_', $oldfilename);
                        $newfilename = $newcourse . '_' . $newcm . '_' . $words[2];
                    }

                    // Create a new file with the correct filename.
                    $context = context_module::instance($newcm);
                    $filerecord = new stdClass;
                    $filerecord->filename = $newfilename;
                    $filerecord->contextid = $context->id;
                    $newfile = $fs->create_file_from_storedfile($filerecord, $oldfile);

                    // Delete the old file.
                    $oldfile->delete();

                    if ($filearea === 'keyfiles') {
                        // Move the file to ogin folder.
                        file_put_contents($CFG->dataroot . '/ogin/' . $newfilename, $newfile->get_content());
                    }
                }
            }
        }
    }
}
