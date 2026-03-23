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

namespace paygw_transfer\local\utils;

use core\exception\coding_exception;
use core_payment\account;
use core_payment\helper;
use core_payment\local\entities\payable;
use paygw_transfer\local\order\order;
use stdClass;

/**
 * The payment gateway config object.
 *
 * @property-read bool $enabled
 * @package    paygw_transfer
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class config {
    /**
     * The payment gateway configuration.
     * @var stdClass
     */
    protected stdClass $config;
    /**
     * Constructor for payment gateway configuration object.
     * @param stdClass|array $config
     */
    public function __construct(stdClass|array $config) {
        $this->config = (object)$config;
    }
    /**
     * Magic getter for the payment gateway configuration.
     * @param mixed $name
     * @throws coding_exception
     */
    public function __get($name) {
        if (property_exists($this->config, $name)) {
            return $this->config->$name;
        }

        throw new coding_exception("The property $name not exist in the payment gateway configuration.");
    }
    /**
     * Create config from account id.
     * @param int $accountid
     * @return config
     * @throws \moodle_exception
     */
    public static function made_from_accountid(int|account $account): config {
        if (empty($account)) {
            throw new coding_exception('Invalid account id');
        }

        if (!($account instanceof account)) {
            $account = new account($account);
        }

        if ($account && $account->get('enabled')) {
            $gateway = $account->get_gateways()[order::gateway()] ?? null;
        }
        if (empty($gateway)) {
            throw new \moodle_exception('gatewaynotfound', 'payment');
        }
        return new static((object)$gateway->get_configuration());
    }
    /**
     * Make an instance from payable.
     * @param payable $payable
     * @return config
     */
    public static function made_from_payable(payable $payable): config {
        return static::made_from_accountid($payable->get_account_id());
    }
    /**
     * Create config from item.
     * @param string $component
     * @param string $paymentarea
     * @param int    $itemid
     * @return config
     * @throws \moodle_exception
     */
    public static function made_from_item(string $component, string $paymentarea, int $itemid): config {
        $config = (object)helper::get_gateway_configuration(
            $component,
            $paymentarea,
            $itemid,
            order::gateway()
        );
        return new static($config);
    }

    /**
     * Create config from order.
     * @param order $order
     * @return config
     * @throws \moodle_exception
     */
    public static function made_from_order(order $order): config {
        return static::made_from_item(
            $order->get_component(),
            $order->get_paymentarea(),
            $order->get_itemid()
        );
    }
}
