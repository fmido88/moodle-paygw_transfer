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
 * The page that display the messages's data along with filtration form and credit form.
 *
 * @package    paygw_transfer
 * @copyright  2023 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\output\html_writer;
use paygw_transfer\local\messages\message;
use paygw_transfer\local\utils\form;
use paygw_transfer\local\utils\utils;

require_once('../../../config.php');
require_once($CFG->dirroot.'/payment/gateway/transfer/lib.php');

// Any error displaying casing page to not redirect and charging operation may be processed twice.
if (strstr($CFG->wwwroot, 'localhost') === false) {
    set_debugging(DEBUG_NONE, false);
}

$context = context_system::instance();

require_login(null, false);
require_all_capabilities(['paygw/transfer:viewsms', 'enrol/wallet:creditdebit'], $context);

$url = new moodle_url('/payment/gateway/transfer/result.php');

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_title(get_string('pluginname', 'paygw_transfer'));
$PAGE->set_heading(get_string('vc_transactions', 'paygw_transfer'));

$mform = new enrol_wallet\form\charger_form(null, ['paygw_transfer' => true], 'post');

$process = form::process_charges_form($mform, $url);

if ($process instanceof moodle_url) {
    redirect($process);
} else if (!empty($process) && is_string($process)) {
    echo $OUTPUT->header();

    echo $process;

    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->header();

echo utils::get_messages_table();

$errors = optional_param_array('errors', '', PARAM_RAW);

if (!empty($errors)) {

    $result = html_writer::start_tag('ul', ['class' => 'alert alert-danger']);
    $result .= html_writer::start_tag('li');
    $separator = html_writer::end_tag('li') . html_writer::start_tag('li');
    $result .= implode($separator, $errors);
    $result .= html_writer::end_tag('li');
    $result .= html_writer::end_tag('ul');
 
    echo $OUTPUT->box($result);
}

$mform->display();

echo $OUTPUT->footer();
