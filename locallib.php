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
 * Internal library of functions for module og
 *
 * @package    mod_og
 * @copyright  2016 Sarah Street
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir.'/formslib.php');
require_once($CFG->libdir.'/gradelib.php');
require_once($CFG->dirroot . '/repository/lib.php');

/**
 * Custom form to be displayed on view.php
 *
 * @copyright  2016 Sarah Street
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class simplehtml_form extends moodleform {

    /** @var int The cmid of the current OG Project*/
    public $id;

    /** @var int The number of student submissions*/
    private $numsubmissions;

    /** @var int The maximum number of student submissions*/
    private $maxsubmissions;

    /** @var int The due date of the current OG Project*/
    private $duedate;

    /**
     * Define the form
     *
     * @return void
     */
    public function definition() {
        global $COURSE;
        global $DB;

        $mform = $this->_form;

        $coursecontext = context_course::instance($COURSE->id);
        if (has_capability('mod/og:addinstance', $coursecontext)) {
            $action = optional_param('action', null, PARAM_TEXT);
            if ($action == 'grading') {
                $gradeid = required_param('gradeid', PARAM_INT);
                $graderecord = $DB->get_record('grade_grades', array('id' => $gradeid),
                    'userid,finalgrade,rawgrademax,feedback', MUST_EXIST);
                $userrecord = $DB->get_record('user', array('id' => $graderecord->userid), 'firstname,lastname', MUST_EXIST);
                $mform->addElement('static', 'user', get_string('user'), $userrecord->firstname . ' ' . $userrecord->lastname);
                $mform->addElement('static', 'grademax', get_string('grademax', 'grades'), $graderecord->rawgrademax);
                $mform->addElement('text', 'new_grade', get_string('grade', 'grades'), 'value="' . $graderecord->finalgrade . '"');
                $mform->setType('new_grade', PARAM_RAW);
                $mform->addElement('textarea', 'feedback', get_string("feedback", "grades"),
                'wrap="virtual" rows="10" cols="50"');
                $mform->setDefault('feedback', $graderecord->feedback);
                $mform->setType('feedback', PARAM_RAW);

                $mform->addElement('hidden', 'userid', $graderecord->userid);
                $mform->setType('userid', PARAM_INT);
            }
        } else {
            $id = required_param('id', PARAM_INT);
            $cm = get_coursemodule_from_id('og', $id, 0, false, MUST_EXIST);
            $filetype = $DB->get_field('og', 'filetype', array('id' => $cm->instance));

            $duedate = $DB->get_field('og', 'due_date', array('id' => $cm->instance), $strictness = IGNORE_MISSING);
            $mform->addElement('static', 'due_date', get_string('duedate', 'og'),
                               userdate($duedate, get_string('strftimedatetime', 'langconfig')));

            $maxgrade = $DB->get_field('og', 'grade', array('id' => $cm->instance), $strictness = IGNORE_MISSING);
            $mform->addElement('static', 'max_grade', get_string('grademax', 'grades'), $maxgrade);

            $acceptedtypes = array('.' . $filetype);
            $mform->addElement('filepicker', 'studentfile', get_string('attachfile', 'og'), null,
                array('accepted_types' => $acceptedtypes));
        }

        $this->add_action_buttons();
    }

    /**
     * Display the assignment, used by view.php
     *
     * The assignment is displayed differently depending on your role,
     * the settings for the assignment and the status of the assignment.
     *
     * @param string $action The current action if any.
     * @return string - The page output.
     */
    public function view($action = '') {
        global $USER;
        global $OUTPUT;
        global $CFG;
        global $PAGE;
        global $COURSE;
        global $DB;

        $o = '';
        $o .= $OUTPUT->header();

        if ($this->is_cancelled ()) {
            redirect ( $CFG->wwwroot . '/mod/og/view.php?' . 'id=' . $this->id . '&action=viewSummary' );
        } else {
            $coursecontext = context_course::instance($COURSE->id);
            if (has_capability('mod/og:addinstance', $coursecontext)) {
                if ($action == 'grading') {
                    $o .= $OUTPUT->heading(get_string('editgrade', 'grades'), 3);
                    $o .= $this->render();
                } else {
                    if ($datasubmitted = $this->get_data()) {
                        // Save the new grade in the gradebook.
                        $userid = required_param('userid', PARAM_INT);
                        $newgrade = unformat_float(required_param('new_grade', PARAM_FLOAT));
                        $feedback = optional_param('feedback', null, PARAM_TEXT);

                        $og = get_fast_modinfo($COURSE->id, $userid)->get_cm($this->id)->get_course_module_record(true);
                        $og->grade = $newgrade;
                        $grades = new stdClass();
                        $grades->userid = $userid;
                        $grades->rawgrade = $newgrade;
                        $grades->feedback = $feedback;
                        $success = og_grade_item_update($og, $grades);
                    }

                    $o .= $this->get_intro();
                    // Show link to edit the current OG project.
                    $editprojecturl = $CFG->wwwroot . '/course/modedit.php?update=' . $this->id . '&return=0&sr=0';
                    $o .= '<center>' . html_writer::link($editprojecturl, get_string('editsettings', 'og')) . '</center>';

                    $summarytable = new html_table();
                    $o .= $OUTPUT->heading(get_string('studentsummary', 'og'), 3);
                    $summarytable->head = array(get_string('student', 'grades'), get_string('grade', 'grades'),
                                                get_string('gradedfile', 'og'), get_string('submittedfile', 'og'),
                                                get_string('submittedon', 'og'));
                    $info = get_fast_modinfo($COURSE->id);
                    $sort = users_order_by_sql();
                    $students = get_users_by_capability(context_module::instance ( $this->id ), 'mod/og:view');

                    // Sort students by last name, then first name.
                    usort($students, function($a, $b) {
                        $name = strcmp($a->lastname, $b->lastname);
                        if ($name === 0) {
                            return strcmp($a->firstname, $b->firstname);
                        }
                        return $name;
                    });

                    foreach ($students as $student) {
                        if ($student->id != $USER->id) {
                            // Add row in summary table.
                            $row = new html_table_row();
                            $cellstudent = new html_table_cell($student->lastname . ', ' . $student->firstname);

                            list($course, $cm) = get_course_and_cm_from_cmid($this->id, 'og');
                            $oggrades = grade_get_grades($course->id, 'mod', 'og', $cm->instance, $student->id);

                            if (isset($oggrades->items[0]->grades[$student->id]->grade) ) {
                                // Display grade and link to edit grade.
                                $grade = $oggrades->items[0]->grades[$student->id]->grade;
                                $gradeid = $DB->get_record('grade_grades', array('itemid' => $oggrades->items[0]->id,
                                                           'userid' => $student->id), 'id')->id;

                                $nextpageurl = $CFG->wwwroot . '/mod/og/view.php?id=' . $this->id .
                                    '&action=grading&gradeid=' . $gradeid;
                                $grade .= ' ' . html_writer::link($nextpageurl, '[Edit]');
                            } else {
                                $grade = get_string('gradenotavailable', 'og');
                            }

                            $gradedfilelink = $this->get_graded_file_link($student->id);
                            if ($gradedfilelink === false) {
                                $gradedfilelink = get_string('gradedfilenotavailable', 'og');
                            }

                            $cellgrade = new html_table_cell($grade);
                            $cellgradedfile = new html_table_cell($gradedfilelink);

                            $file = $this->get_submitted_file($student->id);
                            if ($file) {
                                $url = moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(),
                                                                       $file->get_filearea(), $file->get_itemid(),
                                                                       $file->get_filepath(), $file->get_filename());
                                $submittedfile = html_writer::link($url, $file->get_filename());
                                $submittedon = userdate($file->get_timecreated(), get_string('strftimedatetime', 'langconfig'));
                            } else {
                                $submittedfile = get_string('nofilesubmitted', 'og');
                                $submittedon = get_string('notavailable', 'moodle');
                            }
                            $cellsubmittedfile = new html_table_cell($submittedfile);

                            $cellsubmittedon = new html_table_cell($submittedon);
                            $row->cells = array($cellstudent, $cellgrade, $cellgradedfile, $cellsubmittedfile, $cellsubmittedon);
                            $summarytable->data[] = $row;
                        }
                    }

                    $o .= html_writer::table($summarytable);
                }
            } else {
                // The current user is a student.
                og_create_oginout_dir();
                if ($action == '') {

                    // If the student has just submitted a file.
                    if ($datasubmitted = $this->get_data()) {

                        if ($this->can_submit() === true) {
                            // Make sure file has at least the minimum size for its type.
                            $fileextension = pathinfo($this->get_new_filename('studentfile') , PATHINFO_EXTENSION);
                            $onefile = $this->get_file_content('studentfile');
                            $filesize = strlen($onefile);
                            if ($this->verify_minimum_filesize(strlen($onefile), $fileextension)) {
                                $context = context_module::instance ( $this->id );
                                file_save_draft_area_files(file_get_submitted_draft_itemid('studentfile'), $context->id,
                                                           'mod_og', 'attachment', $USER->id);

                                if ($onefile) {
                                    // If this is Moodle version 2.7 or greater.
                                    if ($CFG->version >= 2014051200) {
                                        global $PAGE;
                                        $event = \mod_og\event\student_file_submitted::create ( array (
                                            'objectid' => $PAGE->cm->instance,
                                            'context' => $PAGE->context,
                                            'other' => array(
                                                'modulename' => $PAGE->cm->modname,
                                                'instanceid' => $PAGE->cm->instance,
                                                'name' => 'student_file_submitted')
                                            ) );
                                        $event->trigger ();
                                    }

                                    // Generate ident.
                                    $userid = $USER->id;
                                    list($course, $cm) = get_course_and_cm_from_cmid($this->id, 'og');
                                    $ident = $course->id . '_' . $this->id . '_' . $userid .
                                        '(' . $this->numsubmissions . ').' . $fileextension;

                                    // Save file to ogin folder.
                                    $oginout = get_config('og', 'og_oginout_directory');
                                    $success = $this->save_file('studentfile', $oginout .
                                        '/ogin/' . $ident, true);

                                    // Retry if unsuccessful.
                                    if (!file_exists($oginout . '/ogin/' . $ident)) {
                                        $success = $this->save_file('studentfile',
                                                                    $oginout . '/ogin/' . $ident, true);
                                    }

                                    if ($success) {
                                        // Increment submissions count.
                                        $this->can_submit(true);
                                    }
                                }
                            } else {
                                print_error('minfilesizeerror', 'og', $PAGE->url);
                            }
                        }

                        // Redirect to view submitted file summary.
                        $nextpageparams = array();
                        $nextpageparams['id'] = $this->id;
                        $nextpageparams['action'] = 'viewSummary';
                        $nextpageurl = new moodle_url('/mod/og/view.php', $nextpageparams);
                        redirect($nextpageurl);
                        return;
                    } else {
                        // See if the student has previously submitted a file.
                        if ($this->get_submitted_file($USER->id)) {
                            // Redirect to view submitted file summary.
                            $nextpageparams = array();
                            $nextpageparams['id'] = $this->id;
                            $nextpageparams['action'] = 'viewSummary';
                            $nextpageurl = new moodle_url('/mod/og/view.php', $nextpageparams);
                            redirect($nextpageurl);
                            return;
                        } else {
                            $o .= $this->get_intro();

                            $cansubmit = $this->can_submit();
                            if ($cansubmit === true) {
                                $o .= $this->render();
                            } else {
                                $o .= '<b>' . get_string('error', 'moodle') . ': </b>' . $cansubmit;
                            }
                        }
                    }
                } else if ($action == 'submit') {
                    $o .= $this->get_intro();

                    $cansubmit = $this->can_submit();
                    if ($cansubmit === true) {
                        $o .= $this->render();
                    } else {
                        $o .= '<b>' . get_string('error', 'moodle') . ': </b>' . $cansubmit;
                    }
                } else if ($action == 'viewSummary') {
                    $o .= $this->get_intro();
                    $gradeinfo = new html_table();
                    $gradeinfo->size = array('50%', '50%');

                    list($course, $cm) = get_course_and_cm_from_cmid($this->id, 'og');
                    $og = grade_get_grades($course->id, 'mod', 'og', $cm->instance, $USER->id);

                    $file = $this->get_submitted_file($USER->id);
                    if ($file || isset($og->items[0]->grades[$USER->id]->grade)) {
                        // Print grade information.
                        $o .= $OUTPUT->heading(get_string('gradeinfo', 'og'), 3);

                        if (isset($og->items[0])) {
                            $grade = $og->items[0]->grades[$USER->id]->grade;
                        }
                        if (isset($grade) ) {
                            // Display grade.
                            $gradedlink = $this->get_graded_file_link($USER->id);
                            $gradedon = userdate($og->items[0]->grades[$USER->id]->dategraded,
                                                 get_string('strftimedatetime', 'langconfig'));
                        } else {
                            $gradedlink = '';
                            $grade = get_string('gradenotavailable', 'og');
                            $gradedon = get_string('notavailable', 'moodle');
                        }

                        if (!$gradedlink) {
                            $gradedlink = get_string('gradedfilenotavailable', 'og');
                        }

                        $this->add_table_row_tuple($gradeinfo, get_string('grade', 'grades'), $grade);

                        if (isset($og->items[0]->grades[$USER->id]->feedback)) {
                            $this->add_table_row_tuple($gradeinfo, get_string('feedback', 'grades'),
                                                       $og->items[0]->grades[$USER->id]->feedback);
                        }

                        $this->add_table_row_tuple($gradeinfo, get_string('modgrademaxgrade', 'grades'),
                                                   $PAGE->activityrecord->grade);
                        $this->add_table_row_tuple($gradeinfo, get_string('gradedfile', 'og'), $gradedlink);
                        $this->add_table_row_tuple($gradeinfo, get_string('dategraded', 'og'), $gradedon);

                        $o .= html_writer::table($gradeinfo);

                        // Get submitted file information.
                        if ($file) {
                            $url = moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(),
                                                                   $file->get_filearea(), $file->get_itemid(),
                                                                   $file->get_filepath(), $file->get_filename());
                            $submittedfile = html_writer::link($url, $file->get_filename());
                            $numsubmissions = $this->numsubmissions;
                            $submittedon = userdate($file->get_timecreated(), get_string('strftimedatetime', 'langconfig'));
                        } else {
                            $submittedfile = get_string('nofilesubmitted', 'og');
                            $submittedon = get_string('notavailable', 'moodle');
                            $this->numsubmissions = 0;
                        }
                    } else {
                        $submittedfile = get_string('nofilesubmitted', 'og');
                        $submittedon = get_string('notavailable', 'moodle');
                        $this->numsubmissions = 0;
                    }

                    // Print submission information.
                    $result = $this->can_submit(); // Also necessary (here) to initialize class variables.

                    if ($this->maxsubmissions == 0) {
                        $maxsubmissions = get_string('unlimited', 'moodle');
                    } else {
                        $maxsubmissions = $this->maxsubmissions;
                    }

                    $submissioninfo = new html_table();
                    $submissioninfo->size = array('50%', '50%');
                    $o .= $OUTPUT->heading(get_string('submissioninfo', 'og'), 3);
                    $this->add_table_row_tuple($submissioninfo, get_string('submittedfile', 'og'), $submittedfile);
                    $this->add_table_row_tuple($submissioninfo, get_string('submittedon', 'og'), $submittedon);
                    $this->add_table_row_tuple($submissioninfo, get_string('numbersubmissions', 'og'), $this->numsubmissions);
                    $this->add_table_row_tuple($submissioninfo, get_string('maxsubmissions', 'og'), $maxsubmissions);
                    $this->add_table_row_tuple($submissioninfo, get_string('duedate', 'og'),
                                               userdate($this->duedate, get_string('strftimedatetime', 'langconfig')));

                    $o .= html_writer::table($submissioninfo);

                    $urlparams = array('id' => $this->id, 'action' => 'submit');
                    $o .= $OUTPUT->single_button(new moodle_url($CFG->wwwroot . '/mod/og/view.php', $urlparams),
                        get_string('editsubmission', 'og'), 'get');
                }
            }

            $o .= $OUTPUT->footer ();
        }

        return $o;
    }

    /**
     * Verify the filesize is the minimum possible for the given filetype.
     *
     * @param int $filesize The size of the file
     * @param string $filetype The file extension
     * @return bool true if filesize is greater than minimum, false otherwise
     */
    private function verify_minimum_filesize($filesize, $filetype) {
        if (substr($filetype, 0, 2) === 'do' && $filesize > 10000) {
            return true;
        } else if ($filetype === 'txt') {
            return true;
        } else if ($filetype === 'rtf' && $filesize > 100) {
            return true;
        } else if (substr($filetype, 0, 3) === 'ppt' && $filesize > 27000) {
            return true;
        } else if (substr($filetype, 0, 3) === 'xls' && $filesize > 7000) {
            return true;
        } else if ($filetype === 'accdb' && $filesize > 300000) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get the link to the graded file for the specified user.
     *
     * @param int $userid The user id
     * @return mixed The graded file link if it exists, false otherwise
     */
    private function get_graded_file_link($userid) {
        $fs = get_file_storage();
        $context = \context_module::instance($this->id);
        $filelist = $fs->get_area_files($context->id, 'mod_og', 'graded', $userid, '', false);

        // Always use most recent graded file.
        usort($filelist, function($a, $b) {
                return $a->get_timemodified() - $b->get_timemodified();
        }
        );

        $gradedfile = array_pop($filelist);
        if ($gradedfile) {
            $url = moodle_url::make_pluginfile_url($gradedfile->get_contextid(), $gradedfile->get_component(),
                                                   $gradedfile->get_filearea(), $gradedfile->get_itemid(),
                                                   $gradedfile->get_filepath(), $gradedfile->get_filename());
            return html_writer::link($url, $gradedfile->get_filename());
        } else {
            return false;
        }
    }

    /**
     * Utility function to add a row of data to a table with 2 columns. Modified
     * the table param and does not return a value (from mod/assign/renderer.php)
     *
     * @param html_table $table The table to append the row of data to
     * @param string $first The first column text
     * @param string $second The second column text
     * @return void
     */
    private function add_table_row_tuple(html_table $table, $first, $second) {
        $row = new html_table_row();
        $cell1 = new html_table_cell(nl2br($first));
        $cell2 = new html_table_cell(nl2br($second));
        $row->cells = array($cell1, $cell2);
        $table->data[] = $row;
    }

    /**
     * Gets the student submission for the current OG Project
     *
     * @param int $userid The user id
     * @return stored_file The submitted file
     */
    private function get_submitted_file($userid) {
        global $USER;
        $context = context_module::instance ( $this->id );
        $fs = get_file_storage ();
        $files = $fs->get_area_files ( $context->id, 'mod_og', 'attachment', $userid, '', false );
        return array_pop($files);
    }

    /**
     * Returns the intro box for the OG activity
     * @return string The HTML code for the intro
     */
    private function get_intro() {
        global $DB;
        global $OUTPUT;
        global $PAGE;

        list($course, $cm) = get_course_and_cm_from_cmid($this->id, 'og');
        $og = $PAGE->activityrecord;
        $o = '';

        // Print the header.
        $o .= $OUTPUT->heading(format_string($og->name));

        if ($og->intro) {
            $o .= $OUTPUT->box(format_module_intro('og', $og, $cm->id), 'generalbox mod_introbox', 'intro');
        }

        return $o;
    }

    /**
     * Determines whether the student should be allowed to make a submission (based on
     * the due date and maximum number of submissions allowed).
     *
     * @param bool $incrementnumsubmissions If true, num_submissions is incremented
     *
     * @return mixed True if the student is allowed to submit a document, error description otherwise
     */
    private function can_submit($incrementnumsubmissions = false) {
        global $DB;
        global $PAGE;
        global $USER;

        // Get submissions count.
        list($course, $cm) = get_course_and_cm_from_cmid($this->id, 'og');
        $this->maxsubmissions = $PAGE->activityrecord->max_submissions;
        $numsubmissionsrecord = $DB->get_record('og_num_submissions', array('og_id' => $cm->instance, 'user_id' => $USER->id));
        $this->numsubmissions = ($numsubmissionsrecord ? $numsubmissionsrecord->num_submissions : 0);
        $this->duedate = $DB->get_field('og', 'due_date', array('id' => $cm->instance), $strictness = IGNORE_MISSING);

        if ($this->numsubmissions < $this->maxsubmissions || $this->maxsubmissions == 0) {
            // Check due date.
            if ($this->duedate > time()) {
                // Increment submissions count.
                if ($incrementnumsubmissions) {
                    if ($this->numsubmissions) {
                        $newrecord = array('id' => $numsubmissionsrecord->id,
                            'og_id' => $cm->instance,
                            'user_id' => $USER->id,
                            'num_submissions' => $this->numsubmissions + 1);
                        $DB->update_record('og_num_submissions', $newrecord);
                    } else {
                        $newrecord = array('og_id' => $cm->instance,
                            'user_id' => $USER->id,
                            'num_submissions' => 1);
                        $DB->insert_record('og_num_submissions', $newrecord);
                    }
                }

                return true;
            } else {
                return get_string('duedateerror', 'og');
            }
        } else {
            return get_string('maxsubmissionserror', 'og');
        }
    }
}

/**
 * Serves the OG Project files.
 *
 * @package  mod_og
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - just send the file
 */
function local_og_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    global $DB;

    if ($context->contextlevel != CONTEXT_SYSTEM) {
        return false;
    }

    require_login();

    if ($filearea != 'attachment') {
        return false;
    }

    $itemid = (int)array_shift($args);

    if ($itemid != 0) {
        return false;
    }

    $fs = get_file_storage();

    $filename = array_pop($args);
    if (empty($args)) {
        $filepath = '/';
    } else {
        $filepath = '/'.implode('/', $args).'/';
    }

    $file = $fs->get_file($context->id, 'local_filemanager', $filearea, $itemid, $filepath, $filename);
    if (!$file) {
        return false;
    }

    send_stored_file($file, 0, 0, true, $options); // Download MUST be forced - security!
}
