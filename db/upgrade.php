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
 * Database upgrade steps for quizaccess_liveproctoring.
 *
 * @package    quizaccess_liveproctoring
 * @copyright  2026 Energy & Water Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade code for quizaccess_liveproctoring.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_quizaccess_liveproctoring_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026102507) {
        $table = new xmldb_table('quizaccess_liveproctoring_live');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('quizid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('attemptid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('teacherid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'closed');
        $table->add_field('offer', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('answer', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('student_candidates', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('teacher_candidates', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('latest_frame', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('custom_warning', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('terminate_flag', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('attemptid', XMLDB_INDEX_UNIQUE, ['attemptid']);
        $table->add_index('quizid_userid', XMLDB_INDEX_NOTUNIQUE, ['quizid', 'userid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026102507, 'quizaccess', 'liveproctoring');
    }

    return true;
}
