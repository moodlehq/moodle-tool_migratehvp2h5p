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
 * List of not migrated HVP activities.
 *
 * @package     tool_migratehvp2026
 * @category    output
 * @copyright   2020 Sara Arjona <sara@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_migratehvp2026\output;

use tool_migratehvp2026\api;
use hvpactivities_table;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * List of not migrated HVP activities.
 *
 * @copyright   2020 Sara Arjona <sara@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class listnotmigrated implements renderable, templatable {

    /** @var \tool_migratehvp2026\output\hvpactivities_table $table The data requests table. */
    protected $table;

    /**
     * Contructor.
     *
     * @param \tool_migratehvp2026\output\hvpactivities_table $table The data requests table.
     */
    public function __construct(\tool_migratehvp2026\output\hvpactivities_table $table) {
        $this->table = $table;
    }

    /**
     * Export the page data for the mustache template.
     *
     * @param renderer_base $output renderer to be used to render the page elements.
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        global $DB;

        $courses = [];
        $contenttypes = [];

        list($filtersql, $filterparams) = api::get_sql_hvp_to_migrate(false, 'c.fullname ASC, hl.machine_name ASC');
        $records = $DB->get_records_sql($filtersql, $filterparams);
        foreach ($records as $record) {
            $courses[$record->courseid] = $record->course;
            $contenttypes[$record->contenttype] = $record->contenttype;
        }

        $courseoptions = [[
            'value' => 0,
            'text' => get_string('filterallcourses', 'tool_migratehvp2026'),
            'selected' => empty($this->table->filtercourseid),
        ]];
        foreach ($courses as $id => $fullname) {
            $courseoptions[] = [
                'value' => $id,
                'text' => format_string($fullname),
                'selected' => ((int)$this->table->filtercourseid === (int)$id),
            ];
        }

        $contenttypeoptions = [[
            'value' => '',
            'text' => get_string('filterallcontenttypes', 'tool_migratehvp2026'),
            'selected' => ($this->table->filtercontenttype === ''),
        ]];
        foreach ($contenttypes as $machinename) {
            $contenttypeoptions[] = [
                'value' => $machinename,
                'text' => $machinename,
                'selected' => ($this->table->filtercontenttype === $machinename),
            ];
        }

        $perpagevalues = [10, 25, 50, 100, 250, 500];
        $perpageoptions = [];
        foreach ($perpagevalues as $value) {
            $perpageoptions[] = [
                'value' => $value,
                'text' => $value,
                'selected' => ((int)$this->table->filterperpage === $value),
            ];
        }

        $data = (object)[
            'filterformaction' => 'index.php',
            'reseturl' => 'index.php',
            'formaction' => $this->table->baseurl->out(false),
            'filtercourseoptions' => $courseoptions,
            'filtercontenttypeoptions' => $contenttypeoptions,
            'filterperpageoptions' => $perpageoptions,
            'settings' => [
                [
                    'name' => 'keeporiginal',
                    'label' => get_string('keeporiginal', 'tool_migratehvp2026'),
                    'suffixcontrol' => true,
                    'options' => [
                        [
                            'value' => api::HIDEORIGINAL,
                            'text' => get_string('keeporiginal_hide', 'tool_migratehvp2026'),
                            'selected' => ((int)$this->table->keeporiginal === api::HIDEORIGINAL),
                        ],
                        [
                            'value' => api::DELETEORIGINAL,
                            'text' => get_string('keeporiginal_delete', 'tool_migratehvp2026'),
                            'selected' => ((int)$this->table->keeporiginal === api::DELETEORIGINAL),
                        ],
                        [
                            'value' => api::KEEPORIGINAL,
                            'text' => get_string('keeporiginal_nothing', 'tool_migratehvp2026'),
                            'selected' => ((int)$this->table->keeporiginal === api::KEEPORIGINAL),
                        ],
                    ],
                ],
                [
                    'name' => 'copy2cb',
                    'label' => get_string('copy2cb', 'tool_migratehvp2026'),
                    'options' => [
                        [
                            'value' => api::COPY2CBYESWITHLINK,
                            'text' => get_string('copy2cb_yeswithlink', 'tool_migratehvp2026'),
                            'selected' => ((int)$this->table->copy2cb === api::COPY2CBYESWITHLINK),
                        ],
                        [
                            'value' => api::COPY2CBYESWITHOUTLINK,
                            'text' => get_string('copy2cb_yeswithoutlink', 'tool_migratehvp2026'),
                            'selected' => ((int)$this->table->copy2cb === api::COPY2CBYESWITHOUTLINK),
                        ],
                        [
                            'value' => api::COPY2CBNO,
                            'text' => get_string('copy2cb_no', 'tool_migratehvp2026'),
                            'selected' => ((int)$this->table->copy2cb === api::COPY2CBNO),
                        ],
                    ],
                ],
                [
                    'name' => 'preserveavailability',
                    'label' => get_string('preserveavailability', 'tool_migratehvp2026'),
                    'options' => [
                        [
                            'value' => 1,
                            'text' => get_string('yes'),
                            'selected' => ((int)$this->table->preserveavailability === 1),
                        ],
                        [
                            'value' => 0,
                            'text' => get_string('no'),
                            'selected' => ((int)$this->table->preserveavailability === 0),
                        ],
                    ],
                ],
            ],
            'hidesuffix' => $this->table->hidesuffix,
        ];

        $hidesuffixcontext = [
            'visible' => false,
            'label' => '',
            'placeholder' => '',
            'labelhidden' => get_string('hidesuffix_hidden', 'tool_migratehvp2026'),
            'labelkept' => get_string('hidesuffix_kept', 'tool_migratehvp2026'),
            'placeholderhidden' => get_string('hidesuffix_placeholder_hidden', 'tool_migratehvp2026'),
            'placeholderkept' => get_string('hidesuffix_placeholder_kept', 'tool_migratehvp2026'),
        ];

        if ((int)$this->table->keeporiginal === api::HIDEORIGINAL) {
            $hidesuffixcontext = [
                'visible' => true,
                'label' => get_string('hidesuffix_hidden', 'tool_migratehvp2026'),
                'placeholder' => get_string('hidesuffix_placeholder_hidden', 'tool_migratehvp2026'),
                'labelhidden' => get_string('hidesuffix_hidden', 'tool_migratehvp2026'),
                'labelkept' => get_string('hidesuffix_kept', 'tool_migratehvp2026'),
                'placeholderhidden' => get_string('hidesuffix_placeholder_hidden', 'tool_migratehvp2026'),
                'placeholderkept' => get_string('hidesuffix_placeholder_kept', 'tool_migratehvp2026'),
            ];
        } else if ((int)$this->table->keeporiginal === api::KEEPORIGINAL) {
            $hidesuffixcontext = [
                'visible' => true,
                'label' => get_string('hidesuffix_kept', 'tool_migratehvp2026'),
                'placeholder' => get_string('hidesuffix_placeholder_kept', 'tool_migratehvp2026'),
                'labelhidden' => get_string('hidesuffix_hidden', 'tool_migratehvp2026'),
                'labelkept' => get_string('hidesuffix_kept', 'tool_migratehvp2026'),
                'placeholderhidden' => get_string('hidesuffix_placeholder_hidden', 'tool_migratehvp2026'),
                'placeholderkept' => get_string('hidesuffix_placeholder_kept', 'tool_migratehvp2026'),
            ];
        }

        $data->hidesuffixcontrol = $hidesuffixcontext;

        ob_start();
        $this->table->out($this->table->filterperpage, true);
        $hvpactivities = ob_get_contents();
        ob_end_clean();
        $data->hvpactivities = $hvpactivities;

        return $data;
    }
}
