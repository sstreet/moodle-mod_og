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
 * Defines grade_checker class
 *
 * @package   mod_og
 * @copyright 2016 Sarah Street
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_og\task;

use moodle;

/**
 * Checks for graded files in the ogout directory and updates grades
 *
 * @copyright  2016 Sarah Street
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_checker extends \core\task\scheduled_task {
    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('gradechecker', 'mod_og');
    }

    /**
     * Check for graded files.
     */
    public function execute() {
        global $CFG;
        global $PAGE;

        $grade = 0;
        $courseid = 0;
        $cmid = 0;
        $userid = 0;

        include_once($CFG->dirroot . '/mod/og/lib.php');
        require_once($CFG->libdir.'/gradelib.php');
        $ogout = \OGINOUTDIR . "/ogout/";
        $files = scandir($ogout);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                $filename = pathinfo($file, PATHINFO_FILENAME); // Remove the extension from the filename.
                $words = explode(" ", $filename);
                $ident = $words[0]; // Get the first word of the filename.
                $grade = $words[1]; // Get the second word of the filename.

                $fields = explode("_", $ident); // Break the ident into fields.
                $courseid = $fields[0];
                $cmid = $fields[1];
                $userid = $fields[2];
                $submissionid = $fields[3];

                // Record the grade in gradebook.
                $og = get_fast_modinfo($courseid, $userid)->get_cm($cmid)->get_course_module_record(true);
                $old_grade = grade_get_grades($courseid, 'mod', 'og', $og->instance, $userid)->items[0]->grades[$userid]->grade;
                $fs = get_file_storage();
                $context = \context_module::instance($cmid);
                echo "Current grade: ", $old_grade, "<br>";
                echo "New grade: ", $grade, "<br>";
                if ($grade >= $old_grade) {
                    $og->grade = $grade;
                    $og->id = $og->instance;
                    $grades = new \stdClass();
                    $grades->userid = $userid;
                    $grades->rawgrade = 'reset';
                    og_grade_item_update($og, $grades); // This line is necessary to update date graded if grade is the same.
                    $grades->rawgrade = $grade;
                    $success = og_grade_item_update($og, $grades);

                    if ($success === GRADE_UPDATE_OK || $success === GRADE_UPDATE_MULTIPLE) {
                        // Move the file to Moodle so we can display link for student to download it.
                        $filerecord = array('contextid' => $context->id, 'component' => 'mod_og', 'filearea' => 'graded',
                                            'itemid' => $userid, 'filepath' => '/', 'filename' => $file,
                                            'timecreated' => time(), 'timemodified' => time());

                        // Delete the graded file if it exists.
                        $oldfile = $fs->get_file($filerecord['contextid'], $filerecord['component'], $filerecord['filearea'],
                            $filerecord['itemid'], $filerecord['filepath'], $filerecord['filename']);
                        if ($oldfile) {
                            $oldfile->delete();
                        }

                        $fs->create_file_from_pathname($filerecord, $ogout . $file);

                        // Delete the file.
                        unlink($ogout . $file);
                    }
                }
                else {
                    // Delete the file.
                    unlink($ogout . $file);
                }

                // Place grade in author field of stored_file.
                $file = array_pop($fs->get_area_files ( $context->id, 'mod_og', "user".$userid, $submissionid, '', false ));
                $file->set_author($grade);
            }
        }
    }
}
