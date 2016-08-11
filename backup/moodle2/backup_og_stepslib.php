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
 * Define all the backup steps that will be used by the backup_og_activity_task
 *
 * @package   mod_og
 * @category  backup
 * @copyright 2016 Sarah Street
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Define the complete og structure for backup, with file and id annotations
 *
 * @copyright 2016 Sarah Street
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_og_activity_structure_step extends backup_activity_structure_step {

    /**
     * Defines the backup structure of the module
     *
     * @return backup_nested_element
     */
    protected function define_structure() {

        // Find out if we are including userinfo.
        $userinfo = $this->get_setting_value('userinfo');

        // Define the root element describing the og instance.
        $og = new backup_nested_element('og', array('id'), array(
                'name', 'intro', 'introformat', 'timecreated', 'timemodified',
                'grade', 'max_submissions', 'due_date', 'filetype'));
        $submissions = new backup_nested_element('submissions');
        $submission = new backup_nested_element('submission', array('id'), array(
            'og_id', 'user_id', 'num_submissions'));

        // Build the tree.
        $og->add_child($submissions);
        $submissions->add_child($submission);

        // Define data sources.
        $og->set_source_table('og', array('id' => backup::VAR_ACTIVITYID));
        if ($userinfo) {
            $submission->set_source_table('og_num_submissions', array('og_id' => backup::VAR_PARENTID));
        }

        // Annotate ids.
        $submission->annotate_ids('user', 'user_id');

        // Define file annotations (we do not use itemid in this example).
        $og->annotate_files('mod_og', 'intro', null);
        $og->annotate_files('mod_og', 'keyfiles', null);

        if ($userinfo) {
            $og->annotate_files('mod_og', 'attachment', null);
            $og->annotate_files('mod_og', 'graded', null);
        }

        // Return the root element (og), wrapped into standard activity structure.
        return $this->prepare_activity_structure($og);
    }
}
