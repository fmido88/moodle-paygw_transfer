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
 * Endpoint page to receive sms.
 *
 * @package    paygw_transfer
 * @copyright  2023 Mohammad Farouk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.RequireLogin.Missing
use paygw_transfer\local\webhook\webhook;

require_once(__DIR__.'/../../../config.php');
// Cannot call require_login() here as it is a server callback page.

try {
    $msg = required_param('message', PARAM_TEXT); // The message forwarded by sms forwarder application.
} catch (moodle_exception $e) {
    webhook::handle_error($e);
}

try {
    $webhook = new webhook($msg);
    $message = $webhook->save();

    http_response_code(200);
    die;
} catch (Throwable $e) {
    webhook::handle_error($e);
}
