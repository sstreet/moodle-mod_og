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
 * Events tests.
 *
 * @package    mod_og
 * @copyright  2016 Sarah Street
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

/**
 * Events tests class.
 *
 * @package    mod_og
 * @copyright  2016 Sarah Street
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_og_events_testcase extends advanced_testcase {

    public function test_student_file_submitted() {
        global $CFG;
        global $USER;
        $CFG->og_oginout_directory = $CFG->dirroot; // Necessary to have oginout_dir in og/lib.php.

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $og = $this->getDataGenerator()->create_module('og', array('course' => $course->id));
        $cm = get_coursemodule_from_instance('og', $og->id);
        $context = context_module::instance($cm->id);

        $other = array(
            'modulename' => 'og',
            'name' => $og->name,
            'instanceid' => $og->id
        );
        $params = array(
            'objectid' => $og->id,
            'context' => $context,
            'other' => $other
        );
        $event = \mod_og\event\student_file_submitted::create($params);
        $event->add_record_snapshot('og', $og);
        $event->trigger();

        $this->assertInstanceOf('\mod_og\event\student_file_submitted', $event);
        $this->assertEquals($USER->id, $event->userid);
        $this->assertEquals(context_module::instance($og->cmid), $event->get_context());
        $this->assertEquals($event->action, 'student_file_submitted');
    }
}
