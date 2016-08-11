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
 * Unit tests for mod/og/lib.php.
 *
 * @package    mod_og
 * @category   test
 * @copyright  2016 Sarah Street
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License
 */


defined('MOODLE_INTERNAL') || die();

global $CFG;

/**
 * Unit tests for mod/og/lib.php.
 *
 * @copyright  2016 Sarah Street
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
class mod_og_lib_testcase extends advanced_testcase {

    /**
     * Test deleting an OG Project instance.
     */
    public function test_og_delete_instance() {
        global $CFG, $DB;
        $CFG->og_oginout_directory = $CFG->dirroot; // Necessary to have oginout_dir in og/lib.php.
        $this->resetAfterTest(true);
        $this->setAdminUser(); // Necessary to create dummy keyfile record.

        // Create three OG Projects.
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_og');
        $generator->create_instance(array('course' => $course->id));
        $generator->create_instance(array('course' => $course->id));
        $og = $generator->create_instance(array('course' => $course->id));

        // Put three records in og_num_submissions.
        $record = new stdClass();
        $record->og_id = $og->id + 1;
        $record->user_id = '3';
        $record->num_submissions = '1';
        $DB->insert_record('og_num_submissions', $record, false);
        $record->og_id = $og->id + 2;
        $record->user_id = '3';
        $record->num_submissions = '1';
        $DB->insert_record('og_num_submissions', $record, false);
        $record->og_id = $og->id;
        $record->user_id = '3';
        $record->num_submissions = '1';
        $DB->insert_record('og_num_submissions', $record, false);

        // Delete the last OG Project.
        og_delete_instance($og->id);

        // Make sure there are no records in og or og_num_submissions for deleted OG Project.
        $count = $DB->count_records('og', array('id' => $og->id));
        $this->assertEquals(0, $count);
        $count = $DB->count_records('og_num_submissions', array('og_id' => $og->id));
        $this->assertEquals(0, $count);

        // Make sure the other records in og and og_num_submissions still exist.
        $count = $DB->count_records('og');
        $this->assertEquals(2, $count);
        $count = $DB->count_records('og_num_submissions');
        $this->assertEquals(2, $count);
    }
}
