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
 * Prints a particular instance of og
 *
 * @package mod_og
 * @copyright 2016 Sarah Street
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname( __FILE__ ))) . '/config.php');
require_once(dirname(__FILE__) . '/lib.php');
require_once('locallib.php');

$id = optional_param('id', 0, PARAM_INT); // Course_module ID.
$n = optional_param('n', 0, PARAM_INT); // ... or og instance ID - it should be named as the first character of the module.
$action = optional_param('action', '', PARAM_TEXT);

if ($id) {
    $cm = get_coursemodule_from_id('og', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array(
        'id' => $cm->course
    ), '*', MUST_EXIST);
    $og = $DB->get_record('og', array(
        'id' => $cm->instance
    ), '*', MUST_EXIST);
} else if ($n) {
    $og = $DB->get_record('og', array (
        'id' => $n
    ), '*', MUST_EXIST);
    $course = $DB->get_record('course', array (
        'id' => $og->course
    ), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('og', $og->id, $course->id, false, MUST_EXIST);
} else {
    print_error(get_string('missingcmid', 'og'));
}

require_login($course, true, $cm);

// If this is Moodle version 2.7 or greater.
if ($CFG->version >= 2014051200) {
    $event = \mod_og\event\course_module_viewed::create(array(
        'objectid' => $PAGE->cm->instance,
        'context' => $PAGE->context
    ) );
    $event->add_record_snapshot('course', $PAGE->course);
    $event->add_record_snapshot($PAGE->cm->modname, $og);
    $event->trigger();
}

// Print the page header.

$PAGE->set_url('/mod/og/view.php', array(
    'id' => $cm->id
) );
$PAGE->set_title(format_string($og->name));
$PAGE->set_heading(format_string($course->fullname));

$mform = new simplehtml_form($CFG->wwwroot . '/mod/og/view.php?' . 'id=' . $cm->id. 'action=' . $action);
$mform->id = $cm->id;
echo $mform->view($action);
