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
 *
 * @package    paygw_transfer
 * @copyright  2023 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once("{$CFG->libdir}/db/upgradelib.php");

/**
 * Upgrade.
 * @param int $oldversion
 */
function xmldb_paygw_transfer_upgrade($oldversion = 0) {
    global $DB, $CFG;
    require_once(__DIR__ . "/upgradelib.php");

    $dbman = $DB->get_manager();
    if ($oldversion < 2026032002) {

        // Changing type of field message on table paygw_transfer_messages to text.
        $table = new xmldb_table('paygw_transfer_messages');
        $field = new xmldb_field('message', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null, 'subject');

        // Launch change of type for field message.
        $dbman->change_field_type($table, $field);

        // Transfer savepoint reached.
        upgrade_plugin_savepoint(true, 2026032002, 'paygw', 'transfer');
    }
    paygw_transfer_migrate();

    return true;
}
