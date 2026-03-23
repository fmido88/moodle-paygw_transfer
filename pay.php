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
 * Payment process page.
 *
 * @package    paygw_transfer
 * @copyright  2026 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use paygw_transfer\form\credit;

require_once('../../../config.php');

require_login(null, false);

$orderid = required_param('orderid', PARAM_INT);
$url = new moodle_url('/payment/gateway/transfer/pay.php', ['orderid' => $orderid]);
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());

$title = get_string('paybytransfer_title', 'paygw_transfer');
$PAGE->set_heading($title);
$PAGE->set_title($title);

$form = new credit(null, ['orderid' => $orderid]);

if ($form->is_cancelled()) {
    redirect($url);
}
$form->process();

echo $OUTPUT->header();

echo $form->render();

echo $OUTPUT->footer();
