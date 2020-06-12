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

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot. '/course/lib.php');
require_once($CFG->dirroot. '/mod/hvp/locallib.php');
require_once($CFG->dirroot . '/tag/lib.php');
require_once($CFG->dirroot . '/lib/completionlib.php');

use context_course;
use context_module;
use context_user;
use stdClass;
use stored_file;
use mod_h5pactivity\local\attempt;
use tool_migratehvp2h5p\event\hvp_migrated;
use moodle_exception;
use core_tag_tag;
use completion_info;
use core_competency\api as competencyapi;

/**
 * Class containing helper methods for processing mod_hvp migrations.
 *
 * @copyright   2020 Sara Arjona <sara@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class api {

    /** @var value to indicate the original hvp activity must be deleted after migration */
    public const DELETEORIGINAL = 0;

    /** @var value to indicate to keep the original hvp activity after migration */
    public const KEEPORIGINAL = 1;

    /** @var value to indicate to hide the original hvp activity after migration */
    public const HIDEORIGINAL = 2;

    /**
     * Migrates a mod_hvp activity to a mod_h5pactivity.
     *
     * @param int $hvpid The mod_hvp of the activity to migrate.
     * @param int $keeporiginal 0 delete the original hvp, 1 keep it, 2 hides it
     * @return bool if the activity is migrated
     */
    public static function migrate_hvp2h5p(int $hvpid, int $keeporiginal = self::KEEPORIGINAL): bool {
        global $DB, $USER;

        $transaction = $DB->start_delegated_transaction();

        try {
            // Get the mod_hvp activity information.
            $hvp = $DB->get_record('hvp', ['id' => $hvpid]);
            $hvpgradeitem = $DB->get_record('grade_items', ['itemtype' => 'mod', 'itemmodule' => 'hvp', 'iteminstance' => $hvpid]);

            $hvpmodule = $DB->get_record('modules', ['name' => 'hvp'], '*', MUST_EXIST);
            $params = ['module' => $hvpmodule->id, 'instance' => $hvp->id];
            $hvp->cm = $DB->get_record('course_modules', $params, '*', MUST_EXIST);

            // Create mod_h5pactivity.
            $h5pactivity = self::create_mod_h5pactivity($hvp, $hvpgradeitem);
            if (empty($h5pactivity)) {
                return false;
            }

            // Create attempt and upgrade grades.
            self::duplicate_grades($hvpgradeitem->id, $h5pactivity->gradeitem->id);
            self::create_h5pactivity_attempts($hvpid, $h5pactivity->cm);

            h5pactivity_update_grades($h5pactivity);

            self::trigger_migration_event($hvp, $h5pactivity);

            self::finish_migration($hvp, $keeporiginal);

        } catch (moodle_exception $e) {

            try {
                $transaction->rollback($e);
            } catch (moodle_exception $e) {
                // Catch the re-thrown exception.
                return false;
            }

            return false;
        }
        $transaction->allow_commit();

        return true;
    }

    /**
     * Return the SQL to select the hvp activies pending to migrate.
     *
     * This method is used by tool_migratehvp2h5p\output\hvpactivities_table and by
     * the CLI migrate.php command. The SQL is quite complex so having it in one place
     * is a good idea.
     *
     * @param  bool $count when true, returns the count SQL.
     * @return array containing sql to use and an array of params.
     */
    public static function get_sql_hvp_to_migrate(bool $count = false, ?string $sort = null): array {

        self::fix_duplicated_hvp();

        if ($count) {
            $select = "COUNT(1)";
            $groupby = '';
        } else {
            $select = 'h.id, h.course as courseid, c.fullname as course, h.name, hl.machine_name as contenttype,' .
            'COUNT(hc.id) as savedstate, cm.id as instanceid';
            $groupby = 'GROUP BY h.id, h.course, c.fullname, h.name, hl.machine_name, cm.id';
        }

        // We need to select the hvp activities which are not migrated but ignoring the activities in the recycle bin.
        // The most efficient way seems to have a subtable with all non-delete h5p activities.
        $sql = "SELECT $select
                  FROM {hvp} h
                  JOIN {hvp_libraries} hl ON h.main_library_id = hl.id
                  LEFT JOIN {hvp_content_user_data} hc ON hc.hvp_id = h.id
                  JOIN {modules} m ON m.name = 'hvp'
                  JOIN {course_modules} cm ON cm.module = m.id AND h.course = cm.course
                       AND h.id = cm.instance AND cm.deletioninprogress = 0
                  JOIN {course} c ON c.id = h.course
                  LEFT JOIN (
                             SELECT i.id, i.name, i.timecreated, i.course
                             FROM {h5pactivity} i
                             JOIN {modules} m2 ON m2.name = 'h5pactivity'
                             JOIN {course_modules} mgrcm ON mgrcm.module = m2.id AND i.course = mgrcm.course
                                 AND i.id = mgrcm.instance AND mgrcm.deletioninprogress = 0
                       ) mgr
                       ON mgr.name = h.name AND mgr.timecreated = h.timecreated AND mgr.course = h.course
                 WHERE mgr.id IS NULL
                       $groupby";

        if (!empty($sort)) {
            $sql .= " ORDER BY " . $sort;
        }

        return [$sql, []];
    }

    /**
     * Fix duplicated hvp repeated timcreate.
     *
     * To know if a hvp is migrated we check for same name, course and timecreated. This way
     * the system is flexible enough without creating any new table or checking the logstore.
     * The only scenario when this can fail is with duplicated activities. To solve this, we fix
     * first all the duplicated hvp by incrementing it's timecreated one second.
     *
     * - Hey! But this modify the original activity!
     *
     * - Yes, I know. We made the activity one second younger. You'll get over it.
     *
     */
    private static function fix_duplicated_hvp(): void {
        global $DB;

        $sql = "SELECT h.name, h.course, h.timecreated, COUNT(*) as num
                  FROM {hvp} h
                 GROUP BY h.name, h.course, h.timecreated
                HAVING COUNT(*) > 1";

        $records = $DB->get_records_sql($sql, []);

        foreach ($records as $key => $record) {
            $repeated = $DB->get_records(
                'hvp',
                ['name' => $record->name, 'course' => $record->course, 'timecreated' => $record->timecreated],
                '', 'id, timecreated'
            );
            $increment = 0;
            foreach ($repeated as $fix) {
                $DB->set_field('hvp', 'timecreated', $fix->timecreated + $increment, ['id' => $fix->id]);
                $increment ++;
            }
        }
    }

    /**
     * Create an h5pactivity copying information from the existing $hvp activity.
     *
     * @param  stdClass $hvp The mod_hvp activity to be migrated from.
     * @param  stdClass $hvpgradeitem This information is required to update the h5pactivity grading information.
     * @return stdClass|null The new h5pactivity created from the $hvp activity.
     */
    private static function create_mod_h5pactivity(stdClass $hvp, stdClass $hvpgradeitem): ?stdClass {
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
        $h5pactivity->grademethod = 1; // Use highest attempt result for grading.

        $h5pactivity->displayoptions = $hvp->disable;
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

        if (empty($h5pactivity->id)) {
            throw new moodle_exception("Cannot create H5P activity");
        }

        // We use the same timecreated as hvp to know what is the original activity.
        $DB->set_field('h5pactivity', 'timecreated', $hvp->timecreated, ['id' => $h5pactivity->id]);

        // Copy intro files.
        self::copy_area_files($hvp, $hvpcm, $h5pactivity);

        // Copy grade-item.
        $h5pactivity->gradeitem = self::duplicate_grade_item($hvpgradeitem, $h5pactivity);

        // Update couse_module information.
        $h5pcm = self::add_course_module_to_section($hvpcm, $h5pactivity->cm->id);

        // TODO: completion.
        self::copy_tags($hvpcm, $h5pactivity);
        self::copy_competencies($hvpcm, $h5pactivity);
        self::copy_completion($hvpcm, $h5pactivity);

        return $h5pactivity;
    }

    /**
     * Copy tags from hvp to h5pactivity
     *
     * @param type $hvpcm the hvp course_module
     * @param type $h5pactivity the new activity object
     */
    private static function copy_tags($hvpcm, $h5pactivity): void {
        $tags = core_tag_tag::get_item_tags_array('core', 'course_modules', $hvpcm->id);
        $h5pcontext = context_module::instance($h5pactivity->coursemodule);
        core_tag_tag::set_item_tags('core', 'course_modules', $h5pactivity->coursemodule, $h5pcontext, $tags);
    }

    /**
     * Copy comptetences from hvp to h5pactivity
     *
     * @param type $hvpcm the hvp course_module
     * @param type $h5pactivity the new activity object
     */
    private static function copy_competencies($hvpcm, $h5pactivity): void {
        $modulecompetencies = competencyapi::list_course_module_competencies_in_course_module($hvpcm->id);
        foreach ($modulecompetencies as $mcid => $modulecompetency) {
            $ccm = competencyapi::add_competency_to_course_module($h5pactivity->cm, $modulecompetency->get('competencyid'));
        }
    }

    /**
     * Copy completion from hvp to h5pactivity
     *
     * @param type $hvpcm the hvp course_module
     * @param type $h5pactivity the new activity object
     */
    private static function copy_completion($hvpcm, $h5pactivity): void {
        $course = get_course($hvpcm->course);
        $completion = new completion_info($course);
        if ($completion->is_enabled($hvpcm)) {
            $users = $completion->get_tracked_users();
            foreach ($users as $user) {
                $status = $completion->get_data($hvpcm, false, $user->id);
                if ($status->viewed) {
                    $completion->set_module_viewed($h5pactivity->cm, $status->userid);
                }
                $completion->update_state($h5pactivity->cm, $status->completionstate, $status->userid);
            }
        }
    }


    /**
     * Helper function to create draft .h5p file from an existing mod_hvp activity.
     *
     * @param stdClass $hvp mod_hvp object with, at least, id, slug and course.
     * @return stored_file the stored file instance.
     */
    private static function prepare_draft_file_from_hvp(stdClass $hvp): stored_file {
        global $USER, $CFG, $DB, $COURSE;

        // The hvp saves all exports files int the course context instead. There is no real reason
        // for this and, in fact, it is probably a bug.
        $coursecontext = context_course::instance($hvp->course);

        // Get or generate the H5P pacakge.
        $exportfilename = $hvp->slug . '-' . $hvp->id . '.h5p';
        $fs = get_file_storage();
        $exportfile = $fs->get_file($coursecontext->id, 'mod_hvp', 'exports', 0, '/', $exportfilename);
        mtrace($exportfilename);

        if (empty($exportfile)) {
            // We need to generate the H5P file.
            // There are two scenarios where hvp don't create the package file:
            // - If $CFG->mod_hvp_export is defined and disabled (by default, it does not exist)
            // - If the activity is duplicated but nobody access it (the export file is generated on display).
            if (isset($CFG->mod_hvp_export)) {
                unset($CFG->mod_hvp_export);
            }

            // We need to fake the course variable.
            $course = $DB->get_record('course', ['id' => $hvp->course], '*', MUST_EXIST);
            $COURSE = $course;
            // Trigger a fake visualization will create the export file.
            $view    = new \mod_hvp\view_assets($hvp->cm, $course);
            // Slug value can change on export.
            $hvp->slug = $DB->get_field('hvp', 'slug', ['id' => $hvp->id]);
            $exportfilename = $hvp->slug . '-' . $hvp->id . '.h5p';
            $exportfile = $fs->get_file($coursecontext->id, 'mod_hvp', 'exports', 0, '/', $exportfilename);
        }

        if (empty($exportfile)) {
            throw new moodle_exception("Cannot generate H5P package");
        }

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

        // We don't want to leave behind this files in the course context.
        $exportfile->delete();

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

    /**
     * Execute what to do with the original hvp activity.
     *
     * @param stdClass $hvp The mod_hvp of the activity to migrate.
     * @param int $keeporiginal 0 delete the original hvp, 1 keep it, 2 hides it
     * @return bool if the activity was migrated
     */
    public static function finish_migration(stdClass $hvp, int $keeporiginal): void {
        global $DB;
        $hvpmodule = $DB->get_record('modules', ['name' => 'hvp'], '*', MUST_EXIST);
        $params = ['module' => $hvpmodule->id, 'instance' => $hvp->id];
        $hvpcmid = $DB->get_field('course_modules', 'id', $params);
        switch ($keeporiginal) {
            case self::DELETEORIGINAL:
                course_delete_module($hvpcmid);
                break;
            case self::HIDEORIGINAL:
                set_coursemodule_visible($hvpcmid, 0);
                break;
        }
    }
}
