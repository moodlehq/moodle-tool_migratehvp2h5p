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
 * Class containing helper methods for processing mod_hvp migrations.
 *
 * @package     tool_migratehvp2h5p
 * @copyright   2020 Sara Arjona <sara@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_migratehvp2h5p;

use context_course;
use context_module;
use context_user;
use stdClass;
use stored_file;
use mod_h5pactivity\local\attempt;
use tool_migratehvp2h5p\event\hvp_migrated;

/**
 * Class containing helper methods for processing mod_hvp migrations.
 *
 * @copyright   2020 Sara Arjona <sara@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class api {

    /**
     * Migrates a mod_hvp activity to a mod_h5pactivity.
     *
     * @param  int    $hvpid The mod_hvp of the activity to migrate.
     * @return bool True if the activity was migrated; false otherwise;
     */
    public static function migrate_hvp2h5p(int $hvpid): bool {
        global $DB, $USER;

        // TODO: Check in the logs if this $hvpid has been migrated previously.

        // Get the mod_hvp activity information.
        $hvp = $DB->get_record('hvp', ['id' => $hvpid]);
        $hvpgradeitem = $DB->get_record('grade_items', ['itemtype' => 'mod', 'itemmodule' => 'hvp', 'iteminstance' => $hvpid]);

        // Create mod_h5pactivity.
        $h5pactivity = self::create_mod_h5pactivity($hvp, $hvpgradeitem);
        var_dump($h5pactivity);

        // Create attempt and upgrade grades.
        self::duplicate_grades($hvpgradeitem->id, $h5pactivity->gradeitem->id);
        self::create_h5pactivity_attempts($hvpid, $h5pactivity->cm);

        h5pactivity_update_grades($h5pactivity);

        self::trigger_migration_event($hvp, $h5pactivity);

        // TODO: Add a setting to decide if the mod_hvp activity should be hidden/removed.

        // TODO: Implement rollback in case some error has been raising when migrating the activity.

        return false;
    }

    /**
     * Create an h5pactivity copying information from the existing $hvp activity.
     *
     * @param  stdClass $hvp The mod_hvp activity to be migrated from.
     * @param  stdClass $hvpgradeitem This information is required to update the h5pactivity grading information.
     * @return stdClass The new h5pactivity created from the $hvp activity.
     */
    private static function create_mod_h5pactivity(stdClass $hvp, stdClass $hvpgradeitem): stdClass {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/h5pactivity/lib.php');
        require_once($CFG->libdir . '/gradelib.php');

        // Create the mod_h5pactivity object.
        $h5pactivity = new stdClass();
        $h5pactivity->course = $hvp->course;
        $h5pactivity->name = $hvp->name;
        $h5pactivity->timecreated = time();
        $h5pactivity->timemodified = time();
        $h5pactivity->intro = $hvp->intro;
        $h5pactivity->introformat = $hvp->introformat;
        $h5pactivity->grade = intval($hvpgradeitem->grademax);
        // TODO: Add setting to define default grademethod (for attempts).
        $h5pactivity->grademethod = 1; // Use highest attempt result for grading.

        $h5pactivity->displayoptions = $hvp->disable;
        // TODO: Add setting to define default enabletracking value.
        $h5pactivity->enabletracking = 1; // Enabled.

        // Create the H5P file as a draft, simulating how mod_form works.
        $h5pfile = self::prepare_draft_file_from_hvp($hvp);
        $h5pactivity->packagefile = $h5pfile->get_itemid();

        // Create the course-module with the correct information.
        $hvpmodule = $DB->get_record('modules', ['name' => 'hvp'], '*', MUST_EXIST);
        $h5pmodule = $DB->get_record('modules', ['name' => 'h5pactivity'], '*', MUST_EXIST);
        $params = ['module' => $hvpmodule->id, 'instance' => $hvp->id];
        $hvpcm = $DB->get_record('course_modules', $params, '*', MUST_EXIST);
        $h5pactivity->cm = self::duplicate_course_module($hvpcm, $h5pmodule->id);
        $h5pactivity->coursemodule = $h5pactivity->cm->id;
        $h5pactivity->module = $h5pmodule->id;
        $h5pactivity->modulename = $h5pmodule->name;

        // Create mod_h5pactivity entry.
        $h5pactivity->id = h5pactivity_add_instance($h5pactivity);
        $h5pactivity->cm->instance = $h5pactivity->id;

        // Copy intro files.
        self::copy_area_files($hvp, $hvpcm, $h5pactivity);

        // Copy grade-item.
        $h5pactivity->gradeitem = self::duplicate_grade_item($hvpgradeitem, $h5pactivity);

        // Update couse_module information.
        $h5pcm = self::add_course_module_to_section($hvpcm, $h5pactivity->cm->id);

        // TODO: completion, availability, tags, competencies.

        return $h5pactivity;
    }


    /**
     * Helper function to create draft .h5p file from an existing mod_hvp activity.
     *
     * @param stdClass $hvp mod_hvp object with, at least, id, slug and course.
     * @return stored_file the stored file instance.
     */
    private static function prepare_draft_file_from_hvp(stdClass $hvp): stored_file {
        global $USER;

        // TODO: Force exports file creation because if $CFG->mod_hvp_export is disabled, the file won't exist and the h5pactivity
        // can't be migrated. This parameter can only be defined in config.php and, by default, it doesn't exist. However, sites
        // using it, won't be able to use this migration tool if this part is not fixed and the export file is generated.
        $coursecontext = context_course::instance($hvp->course);
        $exportfilename = $hvp->slug . '-' . $hvp->id . '.h5p';
        $fs = get_file_storage();
        $exportfile = $fs->get_file($coursecontext->id, 'mod_hvp', 'exports', 0, '/', $exportfilename);

        $usercontext = context_user::instance($USER->id);
        $filerecord = [
            'component' => 'user',
            'filearea'  => 'draft',
            'itemid'    => file_get_unused_draft_itemid(),
            'author'    => fullname($USER),
            'filepath'  => '/',
            'filename'  => $exportfile->get_filename(),
            'contextid' => $usercontext->id,
        ];

        $fs = get_file_storage();
        $file = $fs->create_file_from_storedfile($filerecord, $exportfile);

        return $file;
    }

    /**
     * Create a duplicate course module record so we can create the upgraded
     * h5pactivity module alongside the hvp module.
     *
     * @param stdClass $cm The old course module record
     * @param int $moduleid The id of the new h5pactivity module
     * @return stdClass The new course module for the mod_h5pactivity.
     */
    private static function duplicate_course_module(stdClass $cm, int $moduleid): stdClass {
        global $CFG;

        $newcm = new stdClass();
        $newcm->course = $cm->course;
        $newcm->module = $moduleid;
        $newcm->visible = $cm->visible;
        $newcm->visibleoncoursepage = $cm->visibleoncoursepage;
        $newcm->section = $cm->section;
        $newcm->score = $cm->score;
        $newcm->indent = $cm->indent;
        $newcm->groupmode = $cm->groupmode;
        $newcm->groupingid = $cm->groupingid;
        $newcm->completion = $cm->completion;
        $newcm->completiongradeitemnumber = $cm->completiongradeitemnumber;
        $newcm->completionview = $cm->completionview;
        $newcm->completionexpected = $cm->completionexpected;
        if (!empty($CFG->enableavailability)) {
            $newcm->availability = $cm->availability;
        }
        $newcm->showdescription = $cm->showdescription;

        $newcm->id = add_course_module($newcm);

        return $newcm;
    }

    /**
     * Add the course module to the course section.
     *
     * @param stdClass $hvpcm
     * @param int      $h5pcmid
     * @return stdClass The course module object for the h5pactivity.
     */
    private static function add_course_module_to_section(stdClass $hvpcm, int $h5pcmid): stdClass {
        global $DB;

        $h5pcm = get_coursemodule_from_id('', $h5pcmid, $hvpcm->course);
        if (!$h5pcm) {
            return false;
        }
        $section = $DB->get_record('course_sections', ['id' => $h5pcm->section]);
        if (!$section) {
            return false;
        }

        $h5pcm->section = course_add_cm_to_section($h5pcm->course, $h5pcm->id, $section->section, $hvpcm->id);

        // Make sure visibility is set correctly.
        set_coursemodule_visible($h5pcm->id, $h5pcm->visible);

        return $h5pcm;
    }

    /**
     * Copy all the files from the mod_hvp files area to the new mod_h5p one.
     *
     * @param stdClass $hvp mod_hvp object
     * @param stdClass $hvpcm mod_hvp course module object
     * @param stdClass $h5pactivity mod_h5p object
     * @return int total of files copied.
     */
    private static function copy_area_files(stdClass $hvp, stdClass $hvpcm, stdClass $h5pactivity): int {
        $count = 0;

        $hvpcontext = context_module::instance($hvpcm->id);
        $h5pcontext = context_module::instance($h5pactivity->coursemodule);
        $fs = get_file_storage();
        $hvpfiles = $fs->get_area_files($hvpcontext->id, 'mod_hvp', 'intro', 0, 'id', false);
        foreach ($hvpfiles as $hvpfile) {
            $filerecord = new stdClass();
            $filerecord->contextid = $h5pcontext->id;
            $filerecord->component = 'mod_h5pactivity';
            $filerecord->filearea = 'intro';
            $filerecord->itemid = 0;
            $fs->create_file_from_storedfile($filerecord, $hvpfile);
            $count++;
        }

        return $count;
    }

    private static function duplicate_grade_item(stdClass $hvpgradeitem, stdClass $h5pactivity) {
        global $DB;

        // Get the existing grade_item entry for the h5pactivity.
        $params = ['itemtype' => 'mod', 'itemmodule' => 'h5pactivity', 'iteminstance' => $h5pactivity->id];
        $h5pgradeitem = $DB->get_record('grade_items', $params);

        // Copy all the fields from the mod_hvp grade_items entry to the mod_h5pactivity one.
        $h5pgradeitem->categoryid = $hvpgradeitem->categoryid;
        $h5pgradeitem->grademin = $hvpgradeitem->grademin;
        $h5pgradeitem->gradepass = $hvpgradeitem->gradepass;
        $h5pgradeitem->multfactor = $hvpgradeitem->multfactor;
        $h5pgradeitem->plusfactor = $hvpgradeitem->plusfactor;
        $h5pgradeitem->aggregationcoef = $hvpgradeitem->aggregationcoef;
        $h5pgradeitem->aggregationcoef2 = $hvpgradeitem->aggregationcoef2;
        $h5pgradeitem->display = $hvpgradeitem->display;
        $h5pgradeitem->decimals = $hvpgradeitem->decimals;
        $h5pgradeitem->hidden = $hvpgradeitem->hidden;
        $h5pgradeitem->locked = $hvpgradeitem->locked;
        $h5pgradeitem->locktime = $hvpgradeitem->locktime;
        $h5pgradeitem->needsupdate = $hvpgradeitem->needsupdate;
        $h5pgradeitem->weightoverride = $hvpgradeitem->weightoverride;

        // Update changes in DB.
        $DB->update_record('grade_items', $h5pgradeitem);

        return $h5pgradeitem;
    }

    private static function duplicate_grades(int $hvpgradeitemid, int $h5pgradeitemid) {
        global $DB;

        $count = 0;
        $records = $DB->get_records('grade_grades', ['itemid' => $hvpgradeitemid]);
        foreach ($records as $record) {
            $record->itemid = $h5pgradeitemid;
            $DB->insert_record('grade_grades', $record);
            $count++;
        }
    }

    private static function create_h5pactivity_attempts(int $hvpid, stdClass $h5pactivitycm) {
        global $DB;

        $records = $DB->get_records('hvp_xapi_results', ['content_id' => $hvpid], 'user_id ASC');
        $currentuser = 0;
        $attempt = null;
        foreach ($records as $record) {
            // If the user is different to the current one, an attempt has to be created.
            if ($record->user_id != $currentuser) {
                // As the new_attempt method is only using the $user->id, an object is created with this information only
                // in order to save some DB calls to get all the user information.
                $user = (object) ['id' => $record->user_id];
                $attempt = attempt::new_attempt($user, $h5pactivitycm);
                $currentuser = $record->user_id;
            }

            // Copy all the xapi_results.
            // TODO: Should we create a statement and call $attempt->save_statement() instead of copying manually this information?.
            // For now, this is the straight approach without using it because it's not easy to create the statement to save.
            $subcontent = null;
            $additionals = json_decode($record->additionals, true);
            if ($additionals && key_exists('extensions', $additionals) &&
                key_exists('http://h5p.org/x-api/h5p-subContentId', $additionals['extensions'])) {
                $subcontent = $additionals['extensions']['http://h5p.org/x-api/h5p-subContentId'];
            }

            $result = new stdClass();
            $result->attemptid = $attempt->get_id();
            $result->subcontent = $subcontent;
            $result->timecreated = time();
            $result->interactiontype = $record->interaction_type;
            $result->description = $record->description;
            $result->correctpattern = $record->correct_responses_pattern;
            $result->response = $record->response;
            $result->additionals = $record->additionals;
            $result->rawscore = $record->raw_score;
            $result->maxscore = $record->max_score;
            // This information wasn't stored by the mod_hvp plugin, so no value can be added here.
            $result->duration = 0;
            // By default, all the results stored in hvp_xpai_results table can be considered as completed.
            $result->completion = 1;
            // This information wasn't stored by the mod_hvp plugin, so no value can be added here.
            $result->success = 0;

            $DB->insert_record('h5pactivity_attempts_results', $result);

            // The entry without parent is the main one, so the attempt grade will be upgraded using its information.
            if (is_null($record->parent_id)) {
                if (!empty($record->raw_score)) {
                    // The $attempt class can't be used here because there is no way to update the required scoreupdated setting.
                    // That's why we need to get the attempt from DB and then upgrade the grading values.
                    $attemptrecord = $DB->get_record('h5pactivity_attempts', ['id' => $attempt->get_id()]);
                    $attemptrecord->timemodified = time();
                    $attemptrecord->rawscore = $record->raw_score;
                    $attemptrecord->maxscore = $record->max_score;
                    $attemptrecord->scaled = $attemptrecord->rawscore / $attemptrecord->maxscore;
                    $attemptrecord->completion = 1;
                    $DB->update_record('h5pactivity_attempts', $attemptrecord);
                }
            }
        }
    }

    private static function trigger_migration_event(stdClass $hvp, stdClass $h5pactivity) {
        global $USER, $DB;

        $hvpmodule = $DB->get_record('modules', ['name' => 'hvp'], '*', MUST_EXIST);
        $params = ['module' => $hvpmodule->id, 'instance' => $hvp->id];
        $hvpcm = $DB->get_record('course_modules', $params, '*', MUST_EXIST);

        $record = new stdClass();
        $record->hvpid = $hvp->id;
        $record->userid = $USER->id;
        $record->contextid = $hvpcm->id;
        $record->h5pactivityid = $h5pactivity->id;
        $record->h5pactivitycmid = $h5pactivity->cm->id;
        $event = hvp_migrated::create_from_record($record);
        $event->trigger();
    }
}
