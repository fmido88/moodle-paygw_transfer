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
 * Section links block
 *
 * @package    paygw_transfer
 * @copyright  2023 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use paygw_transfer\admin\admin_setting_confightmleditorwithimages;

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    // Enable or disable self-credit.
    $settings->add(new admin_setting_configcheckbox('paygw_transfer/enablecredit',
                                                    get_string('enablecredit', 'paygw_transfer'),
                                                    get_string('enablecredit_desc', 'paygw_transfer'), 0));
    // Define limitation between successive tries.
    $settings->add(new admin_setting_configduration('paygw_transfer/limitbetween',
                                                    get_string('limitbetween', 'paygw_transfer'),
                                                    get_string('limitbetween_desc', 'paygw_transfer'),
                                                    12 * HOURSECS,
                                                    HOURSECS));

    $settings->add(new admin_setting_configtext('paygw_transfer/othersite',
                                                get_string('anothersite', 'paygw_transfer'),
                                                get_string('anothersite_desc', 'paygw_transfer'),
                                                '',
                                                PARAM_URL));
    $settings->add(new admin_setting_configtext('paygw_transfer/othertoken',
                                                get_string('anothersite_token', 'paygw_transfer'),
                                                get_string('anothersite_toke_desc', 'paygw_transfer'),
                                                '',
                                                PARAM_TEXT));

    $settings->add(new admin_setting_configtext('paygw_transfer/secret',
                                                get_string('secretkey', 'paygw_transfer'),
                                                get_string('secretkey_desc', 'paygw_transfer'), '', PARAM_TEXT));

    $settings->add(new admin_setting_confightmleditorwithimages('paygw_transfer/walletnumbers',
                                                      get_string('walletnumbers_config', 'paygw_transfer'),
                                                      get_string('walletnumbers_config_desc', 'paygw_transfer'),
                                                      ''));
    $settings->add(new admin_setting_confightmleditorwithimages('paygw_transfer/instapayaddresses',
                                                      get_string('instapayaddresses_config', 'paygw_transfer'),
                                                      get_string('instapayaddresses_config_desc', 'paygw_transfer'),
                                                      ''));
    \core_payment\helper::add_common_gateway_settings($settings, 'paygw_transfer');
}

$url = new moodle_url('/payment/gateway/transfer/orders.php');
$report = new admin_externalpage('paygw_transfer_orders',
                                get_string('orders_report', 'paygw_transfer'),
                                $url, 'paygw/transfer:viewreport');
$ADMIN->add('reports', $report);

$url = new moodle_url('/payment/gateway/transfer/result.php');
$report = new admin_externalpage('paygw_transfer_messages',
                                get_string('messages_report', 'paygw_transfer'),
                                $url, 'paygw/transfer:viewsms');
$ADMIN->add('reports', $report);

$url = new moodle_url('/payment/gateway/transfer/saved_senders.php');
$report = new admin_externalpage('paygw_transfer_senders',
                                get_string('saved_senders', 'paygw_transfer'),
                                $url, 'paygw/transfer:markdone');
$ADMIN->add('reports', $report);
