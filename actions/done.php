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
 * TODO describe file done
 *
 * @package    paygw_transfer
 * @copyright  2026 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use paygw_transfer\form\mark_done;
use paygw_transfer\local\messages\message;

require_once('../../../../config.php');

require_login(null, false);

$context = context_system::instance();
require_capability('paygw/transfer:markdone', $context);

$id = required_param('id', PARAM_INT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/payment/gateway/transfer/actions/done.php', ['id' => $id]));

$title = get_string('markdone', 'paygw_transfer');
$PAGE->set_title($title);
$PAGE->set_heading($title);

$form = new mark_done();

$redirect = new moodle_url('/payment/gateway/transfer/orders.php');
if ($form->is_cancelled()) {
    redirect($redirect);
}

if ($data = $form->get_data()) {
    if (!empty($data->messageid)) {
        $message = new message($data->messageid);
        $form->order->set_messageid($data->messageid);
        $message->completed($form->order->get_userid());
    }
    $form->order->payment_complete();
    redirect($redirect);
}

echo $OUTPUT->header();

$form->display();

echo $OUTPUT->footer();
