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

namespace paygw_transfer\local\webhook;

use core\exception\coding_exception;
use core\exception\moodle_exception;
use paygw_transfer\exceptions\secret_key_exception;
use paygw_transfer\local\messages\message;
use paygw_transfer\local\order\order;
use paygw_transfer\local\utils\utils;
use Throwable;

/**
 * Class utils
 *
 * @package    paygw_transfer
 * @copyright  2026 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class webhook {
    /**
     * The extracted amount from the message.
     * @var float
     */
    protected float $amount = 0.0;
    /**
     * Extracted sender number or id from the message.
     * @var string
     */
    protected string $sender = '';
    /**
     * Gateway type, one of utils::GATEWAY_
     * @var int
     */
    protected int $gateway;

    /**
     * The database table that saves messages.
     * @var string
     */
    public const MESSAGES_TABLE = 'paygw_transfer_messages';
    /**
     * The database table that saves recurrence data.
     * @var string
     */
    public const SAVED_TABLE = 'paygw_transfer_saved';

    // phpcs:disable moodle.Files.LineLength.MaxExceeded
    // phpcs:disable moodle.Files.LineLength.TooLong
    /**
     * Extract transferred amount regex.
     * @var string
     */
    protected const AMOUNT_REGEX = '/(استلام|إستلام|إيداع|ايداع)\s(?:مبلغ)\s*([\d.]+)|(Received\s*(EGP|LE|L.E)|(EGP|LE|L.E)|Received|receieved)\s*(:|)\s*(\d+(?:\.\d+)?)|(\s+(\d+\.\d+)?)\s*(L.E|LE|EGP|جنيهاً|جنيه|جنيها)/i';
    /**
     * Extracting sender regex.
     * @var string
     */
    protected const SENDER_WALLET_REGEX = '/(from|wallet|number|account|من|رقم|حساب|هاتف|محفطة)\s*(002|\+2|2|)\s*(01\d{9})|\((01\d{9})\)/u';
    /**
     * Extracting sender regex.
     * @var string
     */
    protected const SENDER_INSTAPAY_REGEX = '/([\p{L}\p{M}\p{N}_\.-]+)@instapay/u';

    /**
     * Extracting the remaining balance regex.
     * @var string
     */
    protected const BALANCE_REGEX = '/(available|current|remaining|)*\sbalance(:*\s|\s)\d+(?:\.\d+)?|رصيدك (الحالي|الحاى) \d+(?:\.\d+)?|رصيدك (المتبقى|المتبقي) \d+(?:\.\d+)?|الرصيد (المتبقى|المتبقي) \d+(?:\.\d+)?/i';
    // phpcs:enable moodle.Files.LineLength.MaxExceeded
    // phpcs:enable moodle.Files.LineLength.TooLong

    /**
     * Constructor for message object.
     * @param string $message
     */
    public function __construct(
        /** @var string The message content. */
        protected string $message
    ) {
        $this->process();
    }
    /**
     * Check the secret key.
     * @return void
     */
    protected function check_secret() {
        $secret = get_config('paygw_transfer', 'secret');
        if (!empty($secret)) {
            if ((!strstr($this->message, $secret) || !PHPUNIT_TEST)
                // Todo: only rely on headers in the future.
                && (utils::get_headers()['Authorization'] ?? '') !== $secret
                && optional_param('secret', '', PARAM_TEXT) !== $secret
            ) {
                throw new secret_key_exception($this->message);
            }
        }

        if (!empty($secret)) {
            $this->message = str_replace($secret, '', $this->message);
        }
    }
    /**
     * Extract the transferred amount.
     * @return void
     */
    protected function extract_amount() {
        // Extract the amount.
        preg_match(static::AMOUNT_REGEX, $this->message, $matchesamount);
        foreach ($matchesamount as $k => $amount) {
            if ($k == 0) {
                continue;
            }

            $amount = clean_param($amount, PARAM_FLOAT);
            if (!empty($amount)) {
                $this->amount = $amount;
                break;
            }
        }

        if (PHPUNIT_TEST && empty($this->amount)) {
            throw new coding_exception("Cannot extract the amount sent from the message.");
        }
    }

    /**
     * Extract the sender id.
     * @return void
     */
    protected function extract_sender() {
        // Extract the sender number from the message using regex.
        if (!$instapay = preg_match(static::SENDER_INSTAPAY_REGEX, $this->message, $matchessender)) {
            preg_match(static::SENDER_WALLET_REGEX, $this->message, $matchessender);
        }

        $this->gateway = $instapay ? utils::GATEWAY_INSTAPAY : utils::GATEWAY_WALLET;

        foreach ($matchessender as $k => $sender) {
            if ($k < 1) {
                continue;
            }

            if (preg_match('/^[from|wallet|number|account|من|رقم|حساب|هاتف|محفطة]$/', $sender)) {
                continue;
            }

            $sender = clean_param($sender, PARAM_TEXT);
            $sender = ($this->gateway === utils::GATEWAY_WALLET)
                    ? utils::clean_wallet_number($sender)
                    : utils::clean_instapay_account($sender);

            if (!empty($sender)) {
                $this->sender = $sender;
                break;
            }
        }

        if (PHPUNIT_TEST && empty($this->sender)) {
            throw new coding_exception("Cannot extract the sender from the message", $this->message);
        }
    }

    /**
     * Extract and clear the remaining balance from the message.
     * @return void
     */
    protected function clear_balance() {
        // Remove the balance.
        $this->message = trim(preg_replace(static::BALANCE_REGEX, ' ', $this->message));
    }

    /**
     * Get the extracted amount from the message.
     * @return float
     */
    public function get_amount(): float {
        return $this->amount;
    }
    /**
     * Get the extracted sender id.
     * @return string
     */
    public function get_sender(): string {
        return $this->sender;
    }
    /**
     * Get the message after cleaning.
     * @return string
     */
    public function get_message(): string {
        return $this->message;
    }

    /**
     * Save the message data in database.
     * @return message
     */
    public function save(): message {
        $message = message::create($this);
        return $message;
    }
    /**
     * Check if there is saved data and deliver the order automatically.
     * @param message $message
     */
    public function check_saved(message $message): bool {
        global $DB;
        $sender = $message->sender;

        $params = [
                'sender' => $sender,
            ];
        if (!empty($sender) && $saved = $DB->get_records(static::SAVED_TABLE, $params)) {
            if (\count($saved) > 1) {
                return false;
            }
            $saved = reset($saved);
            // Automatically deliver the order.
            // This is a bit tricky as the user could have two or more orders.
            // for now just deliver if the amount equals the exact amount of the order.
            $orders = order::get_orders_by_user($saved->userid, true, $message->get_amount());
            $msgamount = $message->get_amount();
            foreach ($orders as $order) {
                $cost = $order->get_cost();
                if ($cost >= (floor($msgamount) - 0.01) && $cost <= (ceil($msgamount) + 0.01)) {
                    $order->set_messageid($message->id);
                    $order->payment_complete();
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Process the message.
     * @return void
     */
    protected function process(): void {
        $this->check_secret();
        $this->extract_amount();
        $this->extract_sender();
        $this->clear_balance();
    }

    /**
     * Handle any exception in the webhook.
     * @param Throwable $e
     * @return never
     */
    public static function handle_error(Throwable $e): never {
        http_response_code(400);
        $error = $e->getMessage();
        $error .= "\n" . $e->getTraceAsString();
        echo '(' . userdate(time()) . "):\n$error"; // Log the error before throw the error.
        die;
    }
}
