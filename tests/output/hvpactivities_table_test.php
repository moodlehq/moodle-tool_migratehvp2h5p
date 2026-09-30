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

namespace tool_migratehvp2h5p\output;

/**
 * Tests for the pending migration table.
 *
 * @package    tool_migratehvp2h5p
 * @copyright  2026 ISB Bayern
 * @author     Dr. Peter Mayer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_migratehvp2h5p\output\hvpactivities_table
 */
final class hvpactivities_table_test extends \advanced_testcase {
    #[\PHPUnit\Framework\Attributes\Group('baseline')]
    /**
     * Markup in the course full name must not reach the table cell.
     */
    public function test_course_name_markup_is_removed(): void {
        $html = $this->render_course_cell('Biology <img src=x onerror=alert(1)>');

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('Biology', $html);
    }

    #[\PHPUnit\Framework\Attributes\Group('baseline')]
    /**
     * A regular course full name is shown as before.
     */
    public function test_course_name_is_shown(): void {
        $html = $this->render_course_cell('Biologie & Chemie 9b');

        $this->assertSame('Biologie & Chemie 9b', html_entity_decode($html));
    }

    /**
     * Render the course cell of the pending migration table.
     *
     * @param string $fullname Course full name
     * @return string Rendered cell content
     */
    private function render_course_cell(string $fullname): string {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['fullname' => $fullname]);
        $table = new hvpactivities_table();
        $row = (object) [
            'id' => 1,
            'instanceid' => 1,
            'courseid' => $course->id,
            'course' => $course->fullname,
            'name' => 'Activity',
            'contenttype' => 'H5P.MultiChoice',
            'savedstate' => 0,
        ];
        return $table->format_row($row)['course'];
    }
}
