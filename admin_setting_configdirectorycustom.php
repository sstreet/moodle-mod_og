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
 * Defines admin_setting_configdirectorycustom class
 *
 * @package   mod_og
 * @copyright 2016 Sarah Street
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/lib/adminlib.php');

/**
 * Extends admin_setting_configdirectory to validate the file path entered
 *
 * @copyright 2016 Sarah Street
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_configdirectorycustom extends admin_setting_configdirectory {

    /**
     * Validate data before storage
     * @param string $data
     * @return mixed true if ok string if error found
     */
    public function validate($data) {
        if (!file_exists($data)) {
            return get_string('validateerror', 'admin');
        } else if (!is_writeable($data) || !is_readable($data)) {
            return get_string('permissionerror', 'og');
        } else {
            return true;
        }
    }
}
