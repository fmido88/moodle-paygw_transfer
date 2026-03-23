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

namespace paygw_transfer\external;

use core\context\system;
use core\url;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_assign\external\external_api;
use paygw_transfer\local\order\order;

/**
 * Class process
 *
 * @package    paygw_transfer
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process extends external_api {
    /**
     * Processing payment parameters.
     * @return external_function_parameters
     */
    public static function process_payment_parameters() {
        return new external_function_parameters([
            'component'   => new external_value(PARAM_COMPONENT, 'The component at which the payment belongs to.'),
            'paymentarea' => new external_value(PARAM_AREA, 'The payment area'),
            'itemid'      => new external_value(PARAM_INT, 'The payment item id.'),
            'description' => new external_value(PARAM_TEXT, 'Item description.'),
        ]);
    }
    /**
     * Process the payment by transfer.
     * @param  string $component
     * @param  string $paymentarea
     * @param  int    $itemid
     * @param  string $description
     * @return array{success: bool, url: string}|array{success: bool}
     */
    public static function process_payment($component, $paymentarea, $itemid, $description) {
        global $USER;
        [
            'component'   => $component,
            'paymentarea' => $paymentarea,
            'itemid'      => $itemid,
            'description' => $description,
        ] = self::validate_parameters(
                    self::process_payment_parameters(),
                    compact('component', 'paymentarea', 'itemid', 'description')
                );

        self::validate_context(system::instance());

        try {
            $order = order::create_order($component, $paymentarea, $itemid);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'reason' => $e->getMessage() . " " . $e->getTraceAsString(),
            ];
        }

        return [
            'success' => true,
            'url'     => (new url('/payment/gateway/transfer/pay.php', ['orderid' => $order->get_id()]))->out(false),
        ];
    }

    /**
     * Return values of process_payment.
     * @return external_single_structure
     */
    public static function process_payment_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'If the payment is success'),
            'url'     => new external_value(PARAM_URL, 'The success url', VALUE_DEFAULT, ''),
            'reason'  => new external_value(PARAM_TEXT, 'Reason of unsuccess.', VALUE_DEFAULT, ''),
        ]);
    }
}
