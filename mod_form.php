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
 * The main og configuration form
 *
 * @package    mod_og
 * @copyright  2016 Sarah Street
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');

/**
 * Module instance settings form
 *
 * @copyright  2016 Sarah Street
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_og_mod_form extends moodleform_mod {

    /** @var stdclass context object */
    public $context;

    /**
     * Defines forms elements
     */
    public function definition() {

        $mform = $this->_form;

        // Adding the "general" fieldset, where all the common settings are shown.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field.
        $mform->addElement('text', 'name', get_string('ogname', 'og'), array('size' => '64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEAN);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'ogname', 'og');

        // Adding the standard "intro" and "introformat" fields.
        $this->add_intro_editor();
        $mform->setDefault('introeditor', '');

        // Add file picker so teacher can upload the key file.
        $mform->addElement('filepicker', 'keyfile', get_string('attachkeyfile', 'og'), null,
            array('accepted_types' => array ('.doc', '.dot', '.docx', '.docm', '.dotx', '.dotm', '.txt', '.rtf',
            '.ppt', '.pptx', '.pptm',
            '.xls', '.xlsx', '.xlsm', '.xltm',
            '.accdb')));
        $editing = optional_param('update', null, PARAM_INT);
        if (!$editing) {
            $mform->addRule('keyfile', null, 'required', null, 'client');
        }

        $choices = array(
            0 => 'unlimited',
            1 => 1,
            2 => 2,
            3 => 3,
            5 => 5,
            10 => 10
        );
        $mform->addElement('select', 'max_submissions', get_string('maxsubmissions', 'og'), $choices);
        $mform->addHelpButton('max_submissions', 'max_submissions', 'og');

        $mform->addElement('date_time_selector', 'due_date', get_string('duedate', 'og'));
        $mform->addHelpButton('due_date', 'due_date', 'og');

        // Add standard grading elements.
        $this->standard_grading_coursemodule_elements();

        // Add standard elements, common to all modules.
        $this->standard_coursemodule_elements();

        // Add standard buttons, common to all modules.
        $this->add_action_buttons();
    }

    /**
     * Any data processing needed before the form is displayed
     * (needed to set up draft areas for editor and filemanager elements)
     * @param array $defaultvalues
     */
    public function data_preprocessing(&$defaultvalues) {
        $editing = optional_param('update', null, PARAM_INT);
        if ($editing) {
            // See if key file still exists in Moodle.
            $context = context_module::instance($editing);
            $filename = 'Key ' . $defaultvalues['course'] . '_' . $editing . ' ' .
                $defaultvalues['grade'] . '.' . $defaultvalues['filetype'];
            $fs = get_file_storage();
            $keyfile = $fs->get_file($context->id, 'mod_og', 'keyfiles', 0, '/', $filename);
            if (!$keyfile) {
                if (file_exists(OGINOUTDIR . '/backup/' . $filename)) {
                    global $USER;
                    global $CFG;
                    $filerecord = array('contextid' => $context->id, 'component' => 'mod_og',
                        'filearea' => 'keyfiles',
                        'itemid' => 0, 'filepath' => '/', 'filename' => $filename,
                        'timecreated' => time(), 'timemodified' => time());
                    $fs->create_file_from_pathname($filerecord, OGINOUTDIR . '/backup/' . $filename);
                }
            }

            // Load key file in filemanager.
            $draftitemid = file_get_submitted_draft_itemid('keyfile');

            if (empty($draftitemid) && $keyfile) {
                 // The code in this block is from file_prepare_draft_area, modified to
                 // only load the key file ($keyfile obtained above) into the draft area.
                global $USER;
                $usercontext = context_user::instance($USER->id);
                $draftitemid = file_get_unused_draft_itemid();
                $filerecord = array('contextid' => $usercontext->id, 'component' => 'user',
                    'filearea' => 'draft', 'itemid' => $draftitemid);
                $draftfile = $fs->create_file_from_storedfile($filerecord, $keyfile);
                // XXX: This is a hack for file manager (MDL-28666)
                // File manager needs to know the original file information before copying
                // to draft area, so we append these information in mdl_files.source field
                // {@link file_storage::search_references()}
                // {@link file_storage::search_references_count()}
                $sourcefield = $keyfile->get_source();
                $newsourcefield = new stdClass;
                $newsourcefield->source = $sourcefield;
                $original = new stdClass;
                $original->contextid = 1;
                $original->component = 'mod_og';
                $original->filearea  = 'keyfiles';
                $original->itemid    = 0;
                $original->filename  = $keyfile->get_filename();
                $original->filepath  = $keyfile->get_filepath();
                $newsourcefield->original = file_storage::pack_reference($original);
                $draftfile->set_source(serialize($newsourcefield));
                // End of file manager hack.
            } else {
                file_prepare_draft_area($draftitemid, $context->id, 'mod_og', 'keyfiles', 0);
            }
            $defaultvalues['keyfile'] = $draftitemid;
        }
    }
}
