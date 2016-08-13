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
 * Library of interface functions and constants for module og
 *
 * @package    mod_og
 * @copyright  2016 Sarah Street
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Define parent directory for ogin and ogout.
$oginout = get_config('og', 'og_oginout_directory');
if (file_exists($oginout)) {
    define('OGINOUTDIR', $oginout);
} else {
    define('OGINOUTDIR', '/home/oguser');
}

/* Moodle core API */

/**
 * Returns the information on whether the module supports a feature
 *
 * See {@link plugin_supports()} for more info.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed true if the feature is supported, null if unknown
 */
function og_supports($feature) {

    switch($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        default:
            return null;
    }
}

/**
 * Saves a new instance of the og into the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param stdClass $og Submitted data from the form in mod_form.php
 * @param mod_og_mod_form $mform The form instance itself (if needed)
 * @return int The id of the newly inserted og record
 */
function og_add_instance(stdClass $og, mod_og_mod_form $mform = null) {
    global $DB;
    global $CFG;
    global $USER;

    $filecontent = $mform->get_file_content('keyfile');
    $filesize = strlen($filecontent);
    $fileextension = pathinfo($mform->get_new_filename('keyfile'), PATHINFO_EXTENSION);
    if (!verify_minimum_filesize($filesize, $fileextension)) {
        global $PAGE;
        print_error('minfilesizeerror', 'og', $PAGE->url);
    }

    $og->timecreated = time();
    $og->timemodified = time();

    // Store file type.
    $og->filetype = $fileextension;

    $og->id = $DB->insert_record('og', $og);
    og_create_oginout_dir();

    // We need to use context now, so we need to make sure all needed info is already in db.
    $DB->set_field('course_modules', 'instance', $og->id, array('id' => $og->coursemodule));

    // Store file in Moodle.
    $cmid = $og->coursemodule;
    $context = context_module::instance($cmid);
    file_save_draft_area_files(file_get_submitted_draft_itemid('keyfile'), $context->id, 'mod_og', 'keyfiles', 0);

    // Move the key file to ogin folder.
    $onefile = $mform->get_file_content('keyfile');
    $userid = $USER->id;
    $maxgrade = $og->grade;
    $ident = 'Key ' . $og->course . '_' . $og->coursemodule . ' ' .
        $maxgrade . '.' . $fileextension;
    $success = $mform->save_file('keyfile', OGINOUTDIR . '/ogin/' . $ident, true);

    // Store backup copy in oginout/backup.
    $mform->save_file('keyfile', OGINOUTDIR . '/backup/' . $ident, true);

    // Rename file stored in Moodle.
    $fs = get_file_storage();
    $oldfiles = $fs->get_area_files($context->id, 'mod_og', 'keyfiles', 0, null, false);
    $oldfile = array_pop($oldfiles);
    $oldfile->rename($oldfile->get_filepath(), $ident);

    og_grade_item_update($og);

    return $og->id;
}

/**
 * Create ogin, ogout and backup directories if they don't exist.
 */
function og_create_oginout_dir() {
    $path = OGINOUTDIR . '/ogin/';;
    if (!file_exists($path)) {
        $mask = umask(0);
        mkdir($path, 0777);
        umask($mask);
    }

    $path = OGINOUTDIR . '/ogout/';
    if (!file_exists($path)) {
        $mask = umask(0);
        mkdir($path, 0777);
        umask($mask);
    }
    $path = OGINOUTDIR . '/backup/';
    if (!file_exists($path)) {
        $mask = umask(0);
        mkdir($path, 0777);
        umask($mask);
    }
}

/**
 * Verify the filesize is the minimum possible for the given filetype.
 *
 * @param int $filesize The size of the file
 * @param string $filetype The file extension
 * @return bool true if filesize is greater than minimum, false otherwise
 */
function verify_minimum_filesize($filesize, $filetype) {
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
 * Updates an instance of the og in the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param stdClass $og An object from the form in mod_form.php
 * @param mod_og_mod_form $mform The form instance itself (if needed)
 * @return boolean Success/Fail
 */
function og_update_instance(stdClass $og, mod_og_mod_form $mform = null) {
    global $DB;
    global $USER;
    global $CFG;

    $og->timemodified = time();
    $og->id = $og->instance;

    $result = $DB->update_record('og', $og);

    // Delete original file in Moodle.
    $fs = get_file_storage();
    $files = $fs->get_area_files($og->id, 'mod_og', 'keyfiles', 0, null, false);
    foreach ($files as $file) {
        $file->delete();
    }

    // Save updated file in Moodle.
    $cmid = $og->coursemodule;
    $context = context_module::instance($cmid);
    file_save_draft_area_files(file_get_submitted_draft_itemid('keyfile'), $context->id, 'mod_og', 'keyfiles', 0);

    $onefile = $mform->get_file_content('keyfile');
    if ($onefile) {
        // Make sure the file has at least the minimum filesize possible for its type.
        $filecontent = $mform->get_file_content('keyfile');
        $filesize = strlen($filecontent);
        $fileextension = pathinfo($mform->get_new_filename('keyfile'), PATHINFO_EXTENSION);
        if (!verify_minimum_filesize($filesize, $fileextension)) {
            global $PAGE;
            print_error('minfilesizeerror', 'og', $PAGE->url);
        }

        // Move the updated key file to ogin folder.
        $userid = $USER->id;
        $fileextension = pathinfo($mform->get_new_filename('keyfile'), PATHINFO_EXTENSION);
        $maxgrade = $og->grade;
        $ident = 'Key ' . $og->course . '_' . $og->coursemodule . ' ' . $maxgrade . '.' . $fileextension;
        $mform->save_file('keyfile', OGINOUTDIR . '/ogin/' . $ident, true);

        // Store backup copy in oginout/backup (overwriting existing file).
        $mform->save_file('keyfile', OGINOUTDIR . '/backup/' . $ident, true);

        // Rename file stored in Moodle.
        $fs = get_file_storage();
        $oldfiles = $fs->get_area_files($context->id, 'mod_og', 'keyfiles', 0, null, false);
        $oldfile = array_pop($oldfiles);
        if ($oldfile->get_filename() != $ident) {
            $oldfile->rename($oldfile->get_filepath(), $ident);
        }
    }

    og_grade_item_update($og);

    return $result;
}

/**
 * Removes an instance of the og from the database
 *
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function og_delete_instance($id) {
    global $DB;
    global $CFG;

    if (! $og = $DB->get_record('og', array('id' => $id))) {
        return false;
    }

    // Place a file in ogin to indicate deleted assignment.
    list($course, $cm) = get_course_and_cm_from_instance($og, 'og');
    $ident = 'Del ' . $og->course . '_' . $cm->id;
    file_put_contents(OGINOUTDIR . '/ogin/' . $ident, '');

    $DB->delete_records('og', array('id' => $og->id));
    $DB->delete_records('og_num_submissions', array('og_id' => $og->id));

    og_grade_item_delete($og);

    // Delete key files stored in Moodle.
    $context = context_module::instance($cm->id);
    $fs = get_file_storage();
    $files = $fs->get_area_files($og->id, 'mod_og', 'keyfiles', 0, null, false);
    foreach ($files as $file) {
        $file->delete();
    }

    // Delete student files stored in Moodle.
    $files = $fs->get_area_files($og->id, 'mod_og', 'attachment', 0, null, false);
    foreach ($files as $file) {
        $file->delete();
    }

    return true;
}

/**
 * Returns a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 *
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @param stdClass $course The course record
 * @param stdClass $user The user record
 * @param cm_info|stdClass $mod The course module info object or record
 * @param stdClass $og The og instance record
 * @return stdClass|null
 */
function og_user_outline($course, $user, $mod, $og) {

    $return = new stdClass();
    $return->time = 0;
    $return->info = '';
    return $return;
}

/**
 * Prints a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * It is supposed to echo directly without returning a value.
 *
 * @param stdClass $course the current course record
 * @param stdClass $user the record of the user we are generating report for
 * @param cm_info $mod course module info
 * @param stdClass $og the module instance record
 */
function og_user_complete($course, $user, $mod, $og) {
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in og activities and print it out.
 *
 * @param stdClass $course The course record
 * @param bool $viewfullnames Should we display full names
 * @param int $timestart Print activity since this timestamp
 * @return boolean True if anything was printed, otherwise false
 */
function og_print_recent_activity($course, $viewfullnames, $timestart) {
    return false;
}

/**
 * Prepares the recent activity data
 *
 * This callback function is supposed to populate the passed array with
 * custom activity records. These records are then rendered into HTML via
 * {@link og_print_recent_mod_activity()}.
 *
 * Returns void, it adds items into $activities and increases $index.
 *
 * @param array $activities sequentially indexed array of objects with added 'cmid' property
 * @param int $index the index in the $activities to use for the next record
 * @param int $timestart append activity since this time
 * @param int $courseid the id of the course we produce the report for
 * @param int $cmid course module id
 * @param int $userid check for a particular user's activity only, defaults to 0 (all users)
 * @param int $groupid check for a particular group's activity only, defaults to 0 (all groups)
 */
function og_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid = 0, $groupid = 0) {
}

/**
 * Prints single activity item prepared by {@link og_get_recent_mod_activity()}
 *
 * @param stdClass $activity activity record with added 'cmid' property
 * @param int $courseid the id of the course we produce the report for
 * @param bool $detail print detailed report
 * @param array $modnames as returned by {@link get_module_types_names()}
 * @param bool $viewfullnames display users' full names
 */
function og_print_recent_mod_activity($activity, $courseid, $detail, $modnames, $viewfullnames) {
}

/**
 * Function to be run periodically according to the moodle cron
 *
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * Note that this has been deprecated in favour of scheduled task API.
 *
 * @return boolean
 */
function og_cron () {
    return true;
}

/**
 * Returns all other caps used in the module
 *
 * For example, this could be array('moodle/site:accessallgroups') if the
 * module uses that capability.
 *
 * @return array
 */
function og_get_extra_capabilities() {
    return array();
}

/* Gradebook API */

/**
 * Is a given scale used by the instance of og?
 *
 * This function returns if a scale is being used by one og
 * if it has support for grading and scales.
 *
 * @param int $ogid ID of an instance of this module
 * @param int $scaleid ID of the scale
 * @return bool true if the scale is used by the given og instance
 */
function og_scale_used($ogid, $scaleid) {
    global $DB;

    if ($scaleid and $DB->record_exists('og', array('id' => $ogid, 'grade' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Checks if scale is being used by any instance of og.
 *
 * This is used to find out if scale used anywhere.
 *
 * @param int $scaleid ID of the scale
 * @return boolean true if the scale is used by any og instance
 */
function og_scale_used_anywhere($scaleid) {
    global $DB;

    if ($scaleid and $DB->record_exists('og', array('grade' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Creates or updates grade item for the given og instance
 *
 * Needed by {@link grade_update_mod_grades()}.
 *
 * @param stdClass $og instance object with extra cmidnumber and modname property
 * @param mixed $grades Grade (object, array) or several grades (arrays of arrays
 * or objects), NULL if updating grade_item definition only
 * @return int Returns GRADE_UPDATE_OK, GRADE_UPDATE_FAILED, GRADE_UPDATE_MULTIPLE
 * or GRADE_UPDATE_ITEM_LOCKED
 */
function og_grade_item_update(stdClass $og, $grades = null) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    $item = array();
    $item['itemname'] = clean_param($og->name, PARAM_NOTAGS);
    $item['gradetype'] = GRADE_TYPE_VALUE;

    if ($og->grade >= 0) {
        $item['gradetype'] = GRADE_TYPE_VALUE;
        $item['grademax']  = 100;
        $item['grademin']  = 0;
    } else if ($og->grade < 0) {
        $item['gradetype'] = GRADE_TYPE_SCALE;
        $item['scaleid']   = -$og->grade;
    }

    if ($grades && $grades->rawgrade === 'reset') {
        $grades->rawgrade = null;
    }

    return grade_update('mod/og', $og->course, 'mod', 'og',
        $og->instance, 0, $grades, $item);
}

/**
 * Delete grade item for given og instance
 *
 * @param stdClass $og instance object
 * @return grade_item
 */
function og_grade_item_delete($og) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    return grade_update('mod/og', $og->course, 'mod', 'og',
        $og->id, 0, null, array('deleted' => 1));
}

/**
 * Update og grades in the gradebook
 *
 * Needed by {@link grade_update_mod_grades()}.
 *
 * @param stdClass $og instance object with extra cmidnumber and modname property
 * @param int $userid update grade of specific user only, 0 means all participants
 */
function og_update_grades(stdClass $og, $userid = 0) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');

    // Populate array of grade objects indexed by userid.
    $grades = array();

    grade_update('mod/og', $og->course, 'mod', 'og', $og->instance, 0, $grades);
}

/* File API */

/**
 * Returns the lists of all browsable file areas within the given module context
 *
 * The file area 'intro' for the activity introduction field is added automatically
 * by {@link file_browser::get_file_info_context_module()}
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @return array of [(string)filearea] => (string)description
 */
function og_get_file_areas($course, $cm, $context) {
    return array();
}

/**
 * File browsing support for og file areas
 *
 * @package mod_og
 * @category files
 *
 * @param file_browser $browser
 * @param array $areas
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @param string $filearea
 * @param int $itemid
 * @param string $filepath
 * @param string $filename
 * @return file_info instance or null if not found
 */
function og_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    return null;
}

/**
 * Serves the files from the og file areas
 *
 * @package mod_og
 * @category files
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the og's context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 */
function og_pluginfile($course, $cm, $context, $filearea, array $args, $forcedownload, array $options = array()) {
    global $DB, $CFG;

    if ($context->contextlevel != CONTEXT_MODULE) {
        send_file_not_found();
    }

    require_login($course, true, $cm);

    $itemid = (int)array_shift($args);
    $relativepath = implode('/', $args);
    $fullpath = "/{$context->id}/mod_og/$filearea/$itemid/$relativepath";

    $fs = get_file_storage();
    if (!($file = $fs->get_file_by_hash(sha1($fullpath))) || $file->is_directory()) {
        return false;
    }

    // Download MUST be forced - security!
    send_stored_file($file, 0, 0, true);
}
