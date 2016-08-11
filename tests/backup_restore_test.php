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
 * Unit tests for backing up OG Projects.
 *
 * @package    mod_og
 * @category   test
 * @copyright  2016 Sarah Street
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License
 */


defined('MOODLE_INTERNAL') || die();

global $CFG;

/**
 * Unit tests for backing up OG Projects.
 *
 * @copyright  2016 Sarah Street, modified from course/tests/courselib_test.php,
 *             which is copyright 2012 Peter Skoda.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
class mod_og_backup_restore_testcase extends advanced_testcase {

    /**
     * Test backing up an OG Project instance.
     */
    public function test_og_backup_restore() {
        global $CFG;
        global $DB;
        $CFG->og_oginout_directory = $CFG->dirroot; // Necessary to have oginout_dir in og/lib.php.

        // Get the necessary files to perform backup and restore.
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        $this->resetAfterTest();

        // Set to admin user.
        $this->setAdminUser();

        // The user id is going to be 2 since we are the admin user.
        $userid = 2;

        // Create a course that contains only an OG Project.
        $course = $this->getDataGenerator()->create_course();
        $og = $this->getDataGenerator()->create_module('og', array('course' => $course->id));

        // Create backup file and save it to the backup location.
        $bc = new backup_controller(backup::TYPE_1COURSE, $course->id, backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO, backup::MODE_GENERAL, $userid);
        $bc->execute_plan();
        $results = $bc->get_results();
        $file = $results['backup_destination'];
        $fp = get_file_packer('application/vnd.moodle.backup');
        $filepath = $CFG->dataroot . '/temp/backup/test-og-backup-restore/';
        $files = $file->extract_to_pathname($fp, $filepath);

        // Make sure there were files saved in the backup.
        $this->assertArrayHasKey('files.xml', $files);
        $filecontents = file_get_contents($filepath . 'files.xml');
        $this->assertNotEmpty($filecontents);

        // Make sure the correct files were saved.
        $this->assertEquals(2, substr_count($filecontents, '<filearea>keyfiles</filearea>'));
        $cm = get_coursemodule_from_instance('og', $og->id);
        $this->assertContains('<filename>Key ' . $og->course . '_' . $cm->id . ' 100.docx</filename>', $filecontents);

        // Clean up after backing up.
        $bc->destroy();
        unset($bc);

        // Restore the course.
        $rc = new restore_controller('test-og-backup-restore', $course->id, backup::INTERACTIVE_NO,
            backup::MODE_SAMESITE, $userid, backup::TARGET_NEW_COURSE);
        $rc->execute_precheck();
        $rc->execute_plan();

        // Make sure there is exactly one new OG Project record.
        $og = $DB->get_records_select('og', 'id <> ' . $og->id);
        $this->assertCount(1, $og);

        $og = array_pop($og);
        $cm = get_coursemodule_from_instance('og', $og->id);
        $fs = get_file_storage();
        $context = context_module::instance($cm->id);
        $files = $fs->get_area_files($context->id, 'mod_og', 'keyfiles', 0, null, false);

        // Make sure only one keyfile was saved to Moodle files pool.
        $this->assertCount(1, $files);

        // Make sure restored keyfile was renamed correctly.
        $keyfile = array_pop($files);
        $this->assertEquals('Key ' . $og->course . '_' . $cm->id . ' 100.docx', $keyfile->get_filename());
    }
}
