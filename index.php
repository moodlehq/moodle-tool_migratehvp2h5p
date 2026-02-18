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
 * This tool can upgrade mod_hvp activities (Joubel) to the new mod_h5p activity (Moodle HQ).
 *
 * The upgrade can be done on any HVP activity instance.
 * The new HP5activity module was introduced in Moodle 3.9 and although it almost reproduces
 * the features of the existing mod_hvp, it wasn't designed to replace it entirely as there
 * are some features than the current mod_h5pactivity doesn't support, such as saving status or H5P hub.
 *
 * This screen is the main entry-point to the plugin, it gives the admin a list
 * of options available to them.
 *
 * @package     tool_migratehvp2026
 * @copyright   2020 Sara Arjona <sara@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\output\notification;
use tool_migratehvp2026\output\hvpactivities_table;
use tool_migratehvp2026\output\listnotmigrated;
use tool_migratehvp2026\api;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$context = context_system::instance();

$activityids = optional_param_array('activityids', [], PARAM_INT);
$keeporiginal = optional_param('keeporiginal', api::HIDEORIGINAL, PARAM_INT);
$copy2cb = optional_param('copy2cb', api::COPY2CBYESWITHLINK, PARAM_INT);
$hidesuffix = trim(optional_param('hidesuffix', '', PARAM_TEXT));
$preserveavailability = optional_param('preserveavailability', 1, PARAM_INT);
$preserveavailability = ($preserveavailability === 0) ? 0 : 1;
$courseid = optional_param('courseid', 0, PARAM_INT);
$contenttype = trim(optional_param('contenttype', '', PARAM_TEXT));
$perpage = optional_param('perpage', 50, PARAM_INT);

// Restrict page size to safe values.
$perpage = min(max($perpage, 10), 500);

if ($keeporiginal === api::DELETEORIGINAL) {
    $hidesuffix = '';
}

$urlparams = [
    'courseid' => $courseid,
    'contenttype' => $contenttype,
    'perpage' => $perpage,
];
$urlparams = array_filter($urlparams, function($value) {
    return $value !== '' && $value !== 0;
});
$url = new moodle_url('/admin/tool/migratehvp2026/index.php', $urlparams);

// This calls require_login and checks moodle/site:config.
admin_externalpage_setup('migratehvp2026');

$notices = [];
if (!empty($activityids)) {
    foreach ($activityids as $activityid) {
        try {
            $messages = api::migrate_hvp2h5p(
                $activityid,
                $keeporiginal,
                $copy2cb,
                $hidesuffix,
                $preserveavailability
            );
            if (empty($messages)) {
                // Use the default message when no message is raised by the migration method.
                $notices[] = [get_string('migrate_success', 'tool_migratehvp2026', $activityid), notification::NOTIFY_SUCCESS];
            } else {
                // Merge message with previous notices.
                $notices = array_merge($messages, $notices);
            }
        } catch (moodle_exception $e) {
            $errormsg = get_string('migrate_fail', 'tool_migratehvp2026', $activityid);
            $errormsg .= ': '.$e->getMessage();
            $notices[] = [$errormsg, notification::NOTIFY_ERROR];
        }
    }
} else {
    try {
        api::check_requirements($copy2cb);
    } catch (moodle_exception $e) {
        $notices[] = [$e->getMessage(), notification::NOTIFY_ERROR];
    }
}

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');

$PAGE->set_title(get_string('pluginname', 'tool_migratehvp2026'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('hvpactivities', 'tool_migratehvp2026'));

foreach ($notices as $notice) {
    echo $OUTPUT->notification($notice[0], $notice[1]);
}

$table = new hvpactivities_table();
$table->baseurl = $url;
$table->filtercourseid = $courseid;
$table->filtercontenttype = $contenttype;
$table->filterperpage = $perpage;
$table->keeporiginal = $keeporiginal;
$table->copy2cb = $copy2cb;
$table->hidesuffix = $hidesuffix;
$table->preserveavailability = $preserveavailability;
$activitylist = new listnotmigrated($table);
echo $OUTPUT->render($activitylist);

echo $OUTPUT->footer();
