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
 * Renderer class for tool_migratehvp2h5p
 *
 * @package     tool_migratehvp2h5p
 * @copyright   2020 Sara Arjona <sara@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_migratehvp2h5p\output;

use coding_exception;
use html_writer;
use moodle_exception;
use listnotmigrated;
use plugin_renderer_base;

/**
 * Renderer class for tool_migratehvp2h5p
 *
 * @package     tool_migratehvp2h5p
 * @copyright   2020 Sara Arjona <sara@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {

    /**
     * Render the list of not migrated HVP activities.
     *
     * @param  listnotmigrated $page
     * @return string html for the page
     * @throws moodle_exception
     */
    public function render_not_migrated_hvp(\tool_migratehvp2h5p\output\listnotmigrated $page): string {
        $data = $page->export_for_template($this);
        return parent::render_from_template('tool_migratehvp2h5p/listnotmigrated', $data);
    }

}
