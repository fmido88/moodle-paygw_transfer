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
 * The page that handle the auto credit of users using Vodafone cash sms.
 *
 * @package    paygw_transfer
 * @copyright  2023 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use paygw_transfer\form\credit;

require_once(__DIR__.'/../../config.php');
require_once($CFG->dirroot.'/payment/gateway/transfer/lib.php');

require_login(null, false);

$form = new credit(customdata: ['type' => credit::WALLET]);
$form->process();

// Should never reach here.
throw new moodle_exception('No submitted data found');
