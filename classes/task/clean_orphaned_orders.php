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

namespace paygw_transfer\task;
use core\output\progress_trace\text_progress_trace;
use core\task\scheduled_task;
use dml_missing_record_exception;
use enrol_wallet\local\utils\timedate;
use paygw_transfer\local\order\order;
/**
 * Class clean_orphaned_orders
 *
 * @package    paygw_transfer
 * @copyright  2026 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class clean_orphaned_orders extends scheduled_task {
    public function get_name() {
        return get_string('cleanorphanedorders', 'paygw_transfer');
    }
    public function execute() {
        global $DB;
        $trace = new text_progress_trace();
        $table = order::get_orders_table_name();
        $sql = "SELECT ord.id
                FROM {$table} ord
            LEFT JOIN {payment} p ON ord.paymentid = p.id
                WHERE (ord.paymentid IS NULL OR ord.paymentid = :empt)
                  AND ord.timemodified < :time1";
        $params = [
            'empt'  => '',
            'time1' => timedate::time(),
        ];
        $orders = $DB->get_records_sql($sql, $params);
        // Check if the item is deleted.
        $tobedeleted = [];
        foreach ($orders as $order) {
            try {
                new order($order->id);
            } catch (dml_missing_record_exception $e) {
                $tobedeleted[] = $order->id;
            } 
        }
        $trace->output("Found " . \count($tobedeleted) . " orphaned orders to be deleted.");

        foreach($tobedeleted as $id) {
            $DB->delete_records($table, ['id' => $id]);
        }
        $trace->finished();
    }
}
