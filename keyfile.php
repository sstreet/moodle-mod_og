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
 * Defines backup_og_activity_task class
 *
 * @package   mod_og
 * @copyright 2016 Sarah Street
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/*********************************************************************
 *
 * This script outputs the key file of an OG Project given the cmid
 * and course id. Only users with the capability of modifying the Moodle
 * site (i.e., admins) are allowed to download the file.
 *
 * To use wget to download the key file, first you must get a cookie so
 * you can get past the login page:
 *
 *     wget --load-cookies og-cookies.txt \
 *       --post-data='username=USERNAME&password=PASSWORD&testcookies=1' \
 *       --save-cookies=og-cookies.txt --keep-session-cookies \
 *       http://officegrader.com/moodle/login/index.php
 *
 * Now you can use wget to download the key file as follows:
 *
 *     wget --load-cookies og-cookies.txt --keep-session-cookies \
 *       --save-cookies og-cookies.txt \
 *       --referer=http://officegrader.com/login/index.php \
 *       http://officegrader.com/moodle/mod/og/keyfile.php\?cmid=357\&course_id=2
 *
 * Of course, replace your values for the cmid and course_id in the
 * last line above.
 *
 **********************************************************************/

require_once('../../config.php');

$cmid = required_param('cmid', PARAM_INT);
$courseid = required_param('course_id', PARAM_INT);

$coursecontext = context_course::instance($courseid);
if (has_capability('moodle/site:config', $coursecontext)) {

    $modinfo = get_fast_modinfo($courseid);
    $cm1 = $modinfo->get_cm($cmid);

    $cm = get_coursemodule_from_instance('og', $cm1->instance, $courseid);
    $context = context_module::instance($cm->id);

    $fs = get_file_storage();
    $files = $fs->get_area_files($cm1->instance, 'mod_og', 'keyfiles', 0, null, false);
    $keyfile = array_pop($files);

    // Output key file.
    $file = 'http://localhost/moodle/pluginfile.php/' . $context->id . '/mod_og/attachment/0/correct.pptx';
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $keyfile->get_filename() . '"');
    header('Content-Length: ' . $keyfile->get_filesize());
    echo $keyfile->get_content();

}
