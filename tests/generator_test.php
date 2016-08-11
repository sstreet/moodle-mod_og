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
 * PHPUnit og generator tests
 *
 * @package    mod_og
 * @copyright  2016 Sarah Street
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


/**
 * PHPUnit og generator testcase
 *
 * @package    mod_og
 * @copyright  2016 Sarah Street, modified from lib/testing/generator/module_generator.php
 *             which is copyright 2012 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_og_generator_testcase extends advanced_testcase {
    public function test_generator() {
        global $DB;
        global $CFG;

        $this->resetAfterTest(true);

        $this->assertEquals(0, $DB->count_records('og'));

        $course = $this->getDataGenerator()->create_course();

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_og');
        $this->assertInstanceOf('mod_og_generator', $generator);
        $this->assertEquals('og', $generator->get_modulename());

        // Create 3 OG Projects.
        $this->setAdminUser(); // Necessary to create dummy keyfile record.
        $CFG->og_oginout_directory = $CFG->dirroot; // Necessary to have oginout_dir in og/lib.php.
        $generator->create_instance(array('course' => $course->id));
        $generator->create_instance(array('course' => $course->id));
        $og = $generator->create_instance(array('course' => $course->id));
        $this->assertEquals(3, $DB->count_records('og'));

        // Make sure OG Project record was stored properly.
        $this->assertEquals('docx', $og->filetype);
        $cm = get_coursemodule_from_instance('og', $og->id);
        $this->assertEquals($og->id, $cm->instance);
        $this->assertEquals('og', $cm->modname);
        $this->assertEquals($course->id, $cm->course);
        $context = context_module::instance($cm->id);
        $this->assertEquals($og->cmid, $context->instanceid);

        // Make sure key file was stored correctly in Moodle files pool.
        $ident = 'Key ' . $og->course . '_' . $cm->id . ' ' . $og->grade . '.' . $og->filetype;
        $fs = get_file_storage();
        $oldfiles = $fs->get_area_files($context->id, 'mod_og', 'keyfiles', 0, null, false);
        $oldfile = array_pop($oldfiles);
        $this->assertEquals($ident, $oldfile->get_filename());

        // Make sure key file was saved to OGINOUTDIR/backup.
        $this->assertTrue(file_exists(OGINOUTDIR . '/backup/' . $ident));
    }
}
