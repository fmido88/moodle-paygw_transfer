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
 * TODO describe file orders
 *
 * @package    paygw_transfer
 * @copyright  2026 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use paygw_transfer\reportbuilder\orders;

require('../../../config.php');

require_login();
$context = context_system::instance();
$url = new moodle_url('/payment/gateway/transfer/orders.php');
$PAGE->set_url($url);
$PAGE->set_context($context);

$report = core_reportbuilder\system_report_factory::create(orders::class, $context);
$title = get_string('orders', 'paygw_transfer');
$PAGE->set_heading($title);
$PAGE->set_title($title);

echo $OUTPUT->header();
echo $report->output();
echo $OUTPUT->footer();
