# Migrate mod_hvp to mod_h5pactivity #

Moodle plugin allowing to migrate activities created with the mod_hvp plugin created by Joubel to the new mod_h5pactivity created by Moodle HQ since Moodle 3.9.

Some limitations to consider before using this plugin:

* Currently it's still not possible to save the current status with the mod_h5pactivity. The mod_hvp supports it (although it's disabled by default) so, before migrating the activities, consider students might loose these unfinished attempts.
* The new mod_h5pactivity hasn't any global settings to define the default behaviour so general settings defined in mod_hvp, such as the default display options or whether to use or not the hub, are not migrated.

## License ##

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <http://www.gnu.org/licenses/>.
