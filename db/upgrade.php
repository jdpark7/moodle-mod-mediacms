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
 * Upgrade code for the mediacms module.
 *
 * @package     mod_mediacms
 * @copyright   2026 JD Park, Pai-Chai University & UNSIA
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade the mediacms module
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_mediacms_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026011501) {
        // Define field completionminview to be added to mediacms.
        $table = new xmldb_table('mediacms');
        $field = new xmldb_field('completionminview', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timemodified');

        // Conditionally launch add field completionminview.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define table mediacms_progress to be created.
        $table = new xmldb_table('mediacms_progress');

        // Adding fields to table mediacms_progress.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('mediacmsid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('progress', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table mediacms_progress.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('mediacmsid', XMLDB_KEY_FOREIGN, ['mediacmsid'], 'mediacms', ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        // Adding indexes to table mediacms_progress.
        $table->add_index('mediacmsid_userid', XMLDB_INDEX_UNIQUE, ['mediacmsid', 'userid']);

        // Conditionally launch create table mediacms_progress.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Mediacms savepoint reached.
        upgrade_mod_savepoint(true, 2026011501, 'mediacms');
    }

    return true;
}
