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
 * Report page for failed/stale migrations.
 *
 * @package     tool_migratehvp2026
 * @copyright   2026 Andreas Giesen
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\output\notification;
use tool_migratehvp2026\api;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$retry = optional_param('retry', 0, PARAM_INT);
$reset = optional_param('reset', 0, PARAM_INT);

$url = new moodle_url('/admin/tool/migratehvp2026/failed.php');

admin_externalpage_setup('migratehvp2026_failures');
require_capability('moodle/site:config', context_system::instance());

if (!empty($retry) || !empty($reset)) {
    require_sesskey();
}

$notices = [];

$dbman = $DB->get_manager();
$maptable = new xmldb_table('tool_migratehvp2026_map');
if (!$dbman->table_exists($maptable)) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('failedmigrations', 'tool_migratehvp2026'));
    echo $OUTPUT->notification(get_string('failedmigrations_tablenotfound', 'tool_migratehvp2026'), notification::NOTIFY_WARNING);
    echo $OUTPUT->footer();
    exit;
}

if (!empty($reset)) {
    $DB->delete_records('tool_migratehvp2026_map', ['hvpid' => $reset]);
    $notices[] = [get_string('failedmigrationresetdone', 'tool_migratehvp2026', $reset), notification::NOTIFY_SUCCESS];
}

if (!empty($retry)) {
    $record = $DB->get_record('tool_migratehvp2026_map', ['hvpid' => $retry], '*', IGNORE_MISSING);
    if (!$record) {
        $notices[] = [get_string('failedmigrationnotfound', 'tool_migratehvp2026', $retry), notification::NOTIFY_WARNING];
    } else {
        $DB->delete_records('tool_migratehvp2026_map', ['hvpid' => $retry]);
        $notices[] = [get_string('failedmigrationretryqueued', 'tool_migratehvp2026', $retry), notification::NOTIFY_SUCCESS];
    }
}

$sql = "SELECT mm.id, mm.hvpid, mm.h5pid, mm.status, mm.message, mm.timemodified,
               h.name AS hvpname, h.course AS courseid, c.fullname AS coursename, cc.name AS category,
               cm.id AS hvpcmid
          FROM {tool_migratehvp2026_map} mm
     LEFT JOIN {hvp} h ON h.id = mm.hvpid
     LEFT JOIN {course} c ON c.id = h.course
     LEFT JOIN {course_categories} cc ON cc.id = c.category
     LEFT JOIN {modules} m ON m.name = 'hvp'
     LEFT JOIN {course_modules} cm ON cm.module = m.id AND cm.instance = h.id AND cm.course = h.course
         WHERE mm.status = :failed OR mm.status = :started
      ORDER BY mm.timemodified DESC";

$records = $DB->get_records_sql($sql, [
    'failed' => api::MIGRATION_FAILED,
    'started' => api::MIGRATION_STARTED,
]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('failedmigrations', 'tool_migratehvp2026'));

foreach ($notices as $notice) {
    echo $OUTPUT->notification($notice[0], $notice[1]);
}

echo html_writer::tag('p', get_string('failedmigrations_desc', 'tool_migratehvp2026'));

if (empty($records)) {
    echo $OUTPUT->notification(get_string('failedmigrations_none', 'tool_migratehvp2026'), notification::NOTIFY_INFO);
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = [
    get_string('id', 'tool_migratehvp2026'),
    get_string('name'),
    get_string('course'),
    get_string('category'),
    get_string('status'),
    get_string('message', 'tool_migratehvp2026'),
    get_string('timemodified', 'tool_migratehvp2026'),
    get_string('actions'),
];

foreach ($records as $record) {
    $name = format_string($record->hvpname ?? get_string('deleted'));
    if (!empty($record->hvpcmid)) {
        $name = html_writer::link(new moodle_url('/mod/hvp/view.php', ['id' => $record->hvpcmid]), $name, ['target' => '_blank']);
    }

    $retryurl = new moodle_url($url, ['retry' => $record->hvpid, 'sesskey' => sesskey()]);
    $reseturl = new moodle_url($url, ['reset' => $record->hvpid, 'sesskey' => sesskey()]);
    $actions = html_writer::link($retryurl, get_string('failedmigration_retry', 'tool_migratehvp2026'),
        ['class' => 'btn btn-sm btn-primary mr-1']);
    $actions .= ' ';
    $actions .= html_writer::link($reseturl, get_string('failedmigration_reset', 'tool_migratehvp2026'),
        ['class' => 'btn btn-sm btn-secondary']);

    $table->data[] = [
        $record->hvpid,
        $name,
        format_string($record->coursename ?? '-'),
        format_string($record->category ?? '-'),
        s($record->status),
        s($record->message ?? ''),
        userdate((int)$record->timemodified),
        $actions,
    ];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
