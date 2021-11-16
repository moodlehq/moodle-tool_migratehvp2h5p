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
 * Tests for migrating HVP instances to H5Pactivity
 *
 * @package tool_migratehvp2h5p
 * @copyright   2020 Sara Arjona <sara@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_migratehvp2h5p;

defined('MOODLE_INTERNAL') || die();

use tool_migratehvp2h5p\api;
use advanced_testcase;

/**
 * Tests for determining the author of the HVP instances when importing to H5Pactivity
 *
 * @package tool_migratehvp2h5p
 * @copyright   2020 Sara Arjona <sara@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class author_test extends advanced_testcase {

    /**
     * @var int The HVP module id.
     */
    protected $modid;

    /**
     * Create a fake mod_hvp instance and assign it to a course.
     * TODO: Can't currently rely on a HVP generator as there isn't one (yet).
     *
     * @param object $course a Moodle course object.
     * @return object A faked mod_hvp object.
     */
    private function fake_hvp(object $course): object {
        global $DB;

        # Check that mod_hvp activity type is installed, save its id.
        if (empty($this->modid)) {
            $mod = $DB->get_record('modules', [ 'name' => 'hvp' ], '*', IGNORE_MISSING);
            if (empty($mod)) {
                $this->fail("The 'mod_hvp' plugin must be installed for these tests to succeed.");
            }
            $this->modid = $mod->id;
        }

        # store a HVP instance
        $now = time();
        $hvp = (object) [
            'course'            => $course->id,
            'name'              => 'Test HVP',
            'slug'              => 'test-hvp',
            'intro'             => 'Intro text for Test HVP',
            'introformat'       => 1,
            'json_content'      => '{}',
            'main_library_id'   => 0,
            'timecreated'       => $now,
            'timemodified'      => $now,
            #'content_type'      => '',
            #'default_language'  => '',
            #'filtered'          => '{}',
            #'embed_type'        => 'div',
            #'completionpass'    => 0,
            #'disable'           => 0,
            #'year_from'         => '',
            #'year_to'           => '',
            #'authors'           => '',
            #'author_comments'   => '',
            #'source'            => '',
            #'license'           => '',
            #'license_version'   => '',
            #'license_extras'    => '',
            #'changes'           => '',
        ];
        $id = $DB->insert_record('hvp', $hvp);
        $hvp = $DB->get_record('hvp', [ 'id' => $id ], '*', MUST_EXIST);

        # Minimally add it to the course.
        $cm = (object) [
            'course'    => $course->id,
            'module'    => $this->modid,
            'instance'  => $hvp->id,
            'added'     => $now,
        ];
        $cm->id = $DB->insert_record('course_modules', $cm);
        $hvp->cm = $cm;

        # Fake a minimal viable module context.
        $context = (object) [
            'contextlevel' => CONTEXT_MODULE,
            'instanceid'   => $hvp->cm->id,
            'depth'        => 0,
            'path'         => null,
            'locked'       => 0,
        ];
        $context->id = $DB->insert_record('context', $context);
        $hvp->context = $context;

        return $hvp;
    }

    /**
     * Associate a file to an HVP activity.
     * @param object $hvp The HVP activity.
     * @param string $filename The name of the file.
     * @param int $userid The user associated with the file.
     * @param string $filename The file content. Defaults to 'hello' if not specified.
     * @return object a Moodle file record.
     */
    private function fake_file(object $hvp, string $filename, int $userid, string $content='hello'): object {
        $filerecord = [
            'filename'  => $filename,
            'filepath'  => '/',
            'filearea'  => 'content',
            'component' => 'mod_hvp',
            'itemid'    => $hvp->id,
            'contextid' => $hvp->context->id,
            'userid'    => $userid,
        ];
        $fs = get_file_storage();
        return $fs->create_file_from_string($filerecord, $content);
    }

    /**
     * Fake a log entry for adding a course module.
     * @param object $hvp The HVP activity.
     * @return object a Moodle log record.
     */
    private function fake_log(object $hvp, int $userid): object {
        global $DB;
        $log = (object) [
            'timecreated' => time(),
            'eventname' => '\core\event\course_module_created',
            'edulevel' => 0,
            'component' => 'core',
            'target'    => 'course_module',
            'action'    => 'created',
            'courseid'  => $hvp->course,
            'objectid'  => $hvp->cm->id,
            'userid' => $userid,
            'contextid' => $hvp->context->id,
            'contextlevel' => $hvp->context->contextlevel,
            'contextinstanceid' => $hvp->context->instanceid,
        ];
        $log->id = $DB->insert_record('logstore_standard_log', $log);
        return $log;
    }

    /**
     * Set up access to the log store.
     * @return void
     */
    private function setup_logs() {
        $this->preventResetByRollback();
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('buffersize', 0, 'logstore_standard');
        get_log_manager(true);
    }

    public function test_log_user() {
        # Log entry exists; HVP has no files; course has enrolled editing teachers.

        # Arrange:
        $this->setup_logs();
        $this->resetAfterTest(true);
        $dg = $this->getDataGenerator();

        $course = $dg->create_course();
        $teacher = $dg->create_user([ 'username' => 'teacher1' ]);
        $loguser = $dg->create_user([ 'username' => 'loguser' ]);
        $dg->enrol_user($teacher->id, $course->id, 'editingteacher');
        $hvp = $this->fake_hvp($course);
        $log = $this->fake_log($hvp, $loguser->id);
        $this->setAdminUser();

        # Test:
        $author = api::get_hvp_author($hvp);

        # We expect everything to fall through to the current (admin) user.
        $this->assertTrue($author == $loguser->id);
    }

    public function test_tool_runner() {
        # No log entry; HVP has no files; course has no enrolled teachers.

        # Arrange:
        $this->setup_logs();
        $this->resetAfterTest(true);
        $dg = $this->getDataGenerator();

        $course = $dg->create_course();
        $student = $dg->create_user([ 'username' => 'student1' ]);
        $dg->enrol_user($student->id, $course->id, 'student');
        $hvp = $this->fake_hvp($course);
        $this->setAdminUser();

        # Test:
        $author = api::get_hvp_author($hvp);

        # We expect everything to fall through to the current (admin) user.
        $this->assertTrue($author == 2);
    }

    public function test_nonediting_file_user() {
        # No log entry; HVP has a file, but owner is not an editing teacher.

        # Arrange:
        $this->setup_logs();
        $this->resetAfterTest(true);
        $dg = $this->getDataGenerator();

        $course = $dg->create_course();
        $teacher = $dg->create_user([ 'username' => 'teacher' ]);
        $fileuser = $dg->create_user([ 'username' => 'fileuser' ]);
        $dg->enrol_user($teacher->id, $course->id, 'editingteacher');
        $dg->enrol_user($fileuser->id, $course->id, 'teacher');
        $hvp = $this->fake_hvp($course);
        $this->fake_file($hvp, 'hello.txt', $fileuser->id);
        $this->setAdminUser();

        # Test:
        $author = api::get_hvp_author($hvp);

        # We expect the first editing teacher, since file owner is not an editing teacher.
        $this->assertTrue($author == $teacher->id);
    }

    public function test_editing_file_user() {
        # No log entry; HVP has a file, owner is an editing teacher.

        # Arrange:
        $this->setup_logs();
        $this->resetAfterTest(true);
        $dg = $this->getDataGenerator();

        $course = $dg->create_course();
        $teacher = $dg->create_user([ 'username' => 'teacher' ]);
        $fileuser = $dg->create_user([ 'username' => 'fileuser' ]);
        $dg->enrol_user($teacher->id, $course->id, 'editingteacher');
        $dg->enrol_user($fileuser->id, $course->id, 'editingteacher');
        $hvp = $this->fake_hvp($course);
        $this->fake_file($hvp, 'hello.txt', $fileuser->id);
        $this->setAdminUser();

        # Test:
        $author = api::get_hvp_author($hvp);

        # We expect the file owner, since they are an editing teacher.
        $this->assertTrue($author == $fileuser->id);
    }

    public function test_first_editingteacher_assigned() {
        # No log entry; HVP has no files; course has an enrolled teacher.

        # Arrange:
        $this->setup_logs();
        $this->resetAfterTest(true);
        $dg = $this->getDataGenerator();

        $course = $dg->create_course();
        $teacher = $dg->create_user([ 'username' => 'teacher1' ]);
        $dg->enrol_user($teacher->id, $course->id, 'editingteacher');
        $hvp = $this->fake_hvp($course);
        $this->setAdminUser();

        # Test:
        $author = api::get_hvp_author($hvp);

        # We expect the editing teacher.
        $this->assertTrue($author == $teacher->id);
    }
}
