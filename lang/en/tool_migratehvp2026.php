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
 * Plugin strings are defined here.
 *
 * @package     tool_migratehvp2026
 * @category    string
 * @copyright   2026 Andreas Giesen (fork, based on original work by Sara Arjona <sara@moodle.com>)
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['attempted'] = 'Attempted users';
$string['cannot_migrate'] = 'Cannot migrate the activity';
$string['contenttype'] = 'Content-type';
$string['copy2cb'] = 'Should these contents be added to the content bank?';
$string['copy2cb_no'] = 'No, they should be only created in the activity.';
$string['copy2cb_yeswithlink'] = 'Yes, and a link to these files should be used in the activity';
$string['copy2cb_yeswithoutlink'] = 'Yes, but a copy will be used in the activity (changes in the content bank won\'t be reflected in the activity)';
$string['error_contenttypeh5p_disabled'] = "H5P contentbank type is disabled. It must be enabled to migrate activities from mod_hvp
and add them to the content bank too. You can enable this contentytype from 'Site administration | Plugins | Content bank | Manage
content types' or run again the migration tool and select 'No, they should be only created in the activity.' (or 'copy2cb=0' if
you're running CLI) to avoid creating files in content bank.";
$string['error_modh5pactivity_disabled'] = 'H5P activity is disabled. It must be enabled to migrate activities from mod_hvp';
$string['event_hvp_migrated'] = 'mod_hvp migrated to mod_h5pactivity';
$string['failedmigration_reset'] = 'Reset status';
$string['failedmigration_retry'] = 'Retry';
$string['failedmigrationnotfound'] = 'No migration status row found for HVP id {$a}.';
$string['failedmigrationresetdone'] = 'Migration status was reset for HVP id {$a}.';
$string['failedmigrationretryqueued'] = 'HVP id {$a} can now be retried from the migration list.';
$string['failedmigrations'] = 'Failed or incomplete migrations';
$string['failedmigrations_desc'] = 'This report lists migration rows marked as failed or still in started state. Use Retry to make an activity appear again in the pending migration list.';
$string['failedmigrations_none'] = 'No failed or incomplete migration rows were found.';
$string['failedmigrations_tablenotfound'] = 'Migration status table not found yet. Run Moodle upgrade to create it.';
$string['filters'] = 'Filters';
$string['filterallcontenttypes'] = 'All content types';
$string['filterallcourses'] = 'All courses';
$string['filtercontenttype'] = 'H5P content type';
$string['filtercourse'] = 'Course';
$string['filterperpage'] = 'Per page';
$string['graded'] = 'Graded users';
$string['hvpactivities'] = 'Pending mod_hvp activities';
$string['id'] = 'Id';
$string['keeporiginal'] = 'Select what to do with the original activity once migrated';
$string['keeporiginal_delete'] = 'Delete the original activity';
$string['keeporiginal_hide'] = 'Hide the original activity';
$string['keeporiginal_nothing'] = 'Leave the original activity as it is';
$string['migrate'] = 'Migrate';
$string['migrate_fail'] = 'Error migrating hvp activity with id {$a}';
$string['migrate_gradesoverridden'] = 'Original mod_hvp activity "{$a->name}", with id {$a->id}, migrated successfully. However,
    it has some grading information overridden, such as feedback, which hasn\'t been migrated because the original activity is
    configured with an invalid maximum grade (it has to be higher than 0 in order to be migrated to the gradebook).';
$string['migrate_gradesoverridden_notdelete'] = 'Original mod_hvp activity "{$a->name}", with id {$a->id}, migrated successfully.
    However, it has some grading information overridden, such as feedback, which hasn\'t been migrated because the original activity
    is configured with an invalid maximum grade (it has to be higher than 0 in order to be migrated to the gradebook).
    The original activity has been hidden instead of removing it.';
$string['migrate_success'] = 'Hvp activity with id {$a} migrated successfully';
$string['message'] = 'Message';
$string['hidesuffix_hidden'] = 'Suffix for hidden original activity';
$string['hidesuffix_kept'] = 'Suffix for kept original activity';
$string['hidesuffix_placeholder_hidden'] = '(old hidden copy)';
$string['hidesuffix_placeholder_kept'] = '(old kept copy)';
$string['nohvpactivities'] = 'There are no mod_hvp activities to migrate to the mod_h5pactivity.';
$string['pluginname'] = 'Migrate content from mod_hvp to mod_h5pactivity (2026 fork)';
$string['privacy:metadata'] = 'Migrate content from mod_hvp to mod_h5pactivity does not store any personal data';
$string['applyfilters'] = 'Apply';
$string['resetfilters'] = 'Reset';
$string['savedstate'] = 'Saved state';
$string['selecthvpactivity'] = 'Select {$a} mod_hvp activity';
$string['preserveavailability'] = 'Preserve original visibility and availability settings in migrated activity';
$string['settings'] = 'Migration settings';
