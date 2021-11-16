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

defined('MOODLE_INTERNAL') || die();

namespace tool_migratehvp2h5p;

use tool_migratehvp2h5p\api;
use advanced_testcase;

/**
 * Tests for migrating HVP instances to H5Pactivity
 *
 * @package tool_migratehvp2h5p
 * @copyright   2020 Sara Arjona <sara@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_test extends advanced_testcase {

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
        if (empty($this->modid)) {
            $mod = $DB->get_record('modules', [ 'name' => 'hvp' ], '*', MUST_EXIST);
            $this->modid = $mod->id;
        }

        # store a HVP instance
        $now = time();
        $hvp = (object) [
            'course' => $course->id,
            'name' => 'Test HVP',
            'slug' => 'test-hvp',
            'intro' => 'Intro text for Test HVP',
            'introformat' => 1,
            'json_content' => '{}',
            'filtered' => '{}',
            'embed_type' => 'div',
            'completionpass' => 0,
            'main_library_id' => 0,
            # 'disable' => 11,
            # 'content_type' => '',
            # 'authors' => '',
            # 'source' => '',
            # 'year_from' => '',
            # 'year_to' => '',
            # 'license' => '',
            # 'license_version' => '',
            # 'changes' => '',
            # 'license_extras' => '',
            # 'author_comments' => '',
            # 'default_language' => '',
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $id = $DB->insert_record('hvp', $hvp);
        $hvp = $DB->get_record('hvp', [ 'id' => $id ], '*', MUST_EXIST);

        $cm = (object) [
            'course' => $course->id,
            'module' => $this->modid,
            'instance' => $hvp->id,
            'added' => $now,
        ];
        $id = $DB->insert_record('course_modules', $cm);
        $cm->id = 1;
        $hvp->cm = $cm;
        return $hvp;
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

    public function test_no_author_is_tool_runner() {
        # No log entry; HVP has no files; course has no enrolled teachers; fall back to tool runner.

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
        $this->assertTrue($author == 2);
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
        $this->assertTrue($author == $teacher->id);
    }
}
