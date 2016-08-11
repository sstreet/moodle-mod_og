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
 * This page lists all instances of OG Projects in a particular course
 *
 * @package    mod_og
 * @copyright  2016 Sarah Street
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');

$id = required_param('id', PARAM_INT); // Course.

$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);

require_course_login($course);

$params = array(
    'context' => context_course::instance($course->id)
);
$event = \mod_og\event\course_module_instance_list_viewed::create($params);
$event->add_record_snapshot('course', $course);
$event->trigger();

$strname = get_string('modulenameplural', 'mod_og');
$strduedate = get_string('duedate', 'mod_og');
$PAGE->set_url('/mod/og/index.php', array('id' => $id));
$PAGE->navbar->add($strname);
$PAGE->set_title("$course->shortname: $strname");
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();
echo $OUTPUT->heading($strname);

if (!$ogs = get_all_instances_in_course('og', $course)) {
    notice(get_string('noogs', 'og'), new moodle_url('/course/view.php', array('id' => $course->id)));
}

$usesections = course_format_uses_sections($course->format);

$table = new html_table();
$table->attributes['class'] = 'generaltable mod_index';

if ($usesections) {
    $strsectionname = get_string('sectionname', 'format_'.$course->format);
    $table->head  = array($strsectionname, $strname, $strduedate);
    $table->align = array('center', 'left');
} else {
    $table->head  = array($strname, $strduedate);
    $table->align = array('left');
}

$modinfo = get_fast_modinfo($course);
$currentsection = '';
foreach ($ogs as $og) {
    $cm = $modinfo->cms[$og->coursemodule];
    $row = '';
    if ($usesections) {
        if ($og->section !== $currentsection) {
            if ($og->section) {
                $row = get_section_name($course, $og->section);
            }
            if ($currentsection !== '') {
                $table->data[] = 'hr';
            }
            $currentsection = $og->section;
        }
    }

    $class = $og->visible ? '' : 'class="dimmed"';

    $table->data[] = array(
        $row,
        "<a $class href=\"view.php?id=$cm->id\">".format_string($og->name)."</a>",
        format_string(userdate($og->due_date, get_string('strftimedatetime', 'langconfig'))));
}

echo html_writer::table($table);

echo $OUTPUT->footer();
