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
 * Upgrade functions for Transfer to Mobile Wallet or InstaPay
 *
 * @package    paygw_transfer
 * @copyright  2026 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Migrate messages data from the old table vc_sms.
 * @return void
 */
function paygw_transfer_migrate() {
    global $DB;
    $dbman = $DB->get_manager();
    if ($dbman->table_exists('vc_sms')) {
        $records = $DB->get_records('vc_sms');
        foreach ($records as $record) {
            $id = $record->id;
            $record->timecreated = $record->time;
            $DB->insert_record('paygw_transfer_messages', $record);
            $DB->delete_records('vc_sms', ['id' => $id]);
        }
    }
}
