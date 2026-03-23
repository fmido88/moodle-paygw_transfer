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
 * TODO describe file delete
 *
 * @package    paygw_transfer
 * @copyright  2026 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use paygw_transfer\local\order\order;

require_once('../../../../config.php');
require_login(null, false);

$context = context_system::instance();
require_capability('paygw/transfer:delete', $context);

$id = required_param('id', PARAM_INT);

$url = new moodle_url('/payment/gateway/transfer/actions/delete.php', ['id' => $id]);
$PAGE->set_context($context);
$PAGE->set_url($url);

$title = get_string('markdone', 'paygw_transfer');
$PAGE->set_title($title);
$PAGE->set_heading($title);

$confirm = optional_param('confirm', false, PARAM_BOOL);
$redirect = new core\url('/payment/gateway/transfer/orders.php');

if ($confirm && confirm_sesskey()) {
    $DB->delete_records(order::get_orders_table_name(), ['id' => $id]);
    redirect($redirect);
} else if (optional_param('cancel', false, PARAM_BOOL)) {
    redirect($redirect);
}

$order = new order($id);

$confirmoutput = $OUTPUT->confirm(
    get_string('confirmdeleteorder', 'paygw_transfer'),
    new moodle_url($url, ['confirm' => true, 'sesskey' => sesskey()]),
    $redirect
);

echo $OUTPUT->header();

echo $order;
echo $confirmoutput;

echo $OUTPUT->footer();
