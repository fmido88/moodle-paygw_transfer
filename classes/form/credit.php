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
 * Credit form.
 *
 * @package    paygw_transfer
 * @copyright  2023 Mo Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_transfer\form;

use core\exception\coding_exception;
use core\url;
use paygw_transfer\local\messages\message;
use paygw_transfer\local\order\order;
use paygw_transfer\local\utils\utils;
use paygw_transfer\local\webhook\webhook;
use stdClass;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir.'/formslib.php');

/**
 * Credit form that ables the user to self complete its payment process
 * or charge the wallet.
 *
 * @package paygw_transfer
 */
class credit extends \moodleform {
    /**
     * Using the form for processing payment.
     * @var string
     */
    public const PAYMENT = 'payment';

    /**
     * Using the form for topping up the wallet.
     * @var string
     */
    public const WALLET = 'wallet';

    /**
     * The order to be completed.
     * not set in case of wallet topup.
     * @var order
     */
    protected order $order;

    /**
     * What this form used for.
     * @var string
     */
    protected string $usetype = self::PAYMENT;

    /**
     * Form definition.
     * @return void
     */
    public function definition() {
        global $DB, $OUTPUT;
        $mform = $this->_form;

        $this->usetype = $this->_customdata['type'] ?? self::PAYMENT;

        $mform->addElement('select', 'gateway',  get_string('gateway', 'paygw_transfer'), utils::get_gateways_options());

        $mform->addElement('html', $OUTPUT->heading(get_string('pluginname', 'paygw_transfer'), 3));
        $mform->addElement('static', 'wallet_description',
                        get_string('wallet_description_label', 'paygw_transfer'),
                        utils::get_wallet_description());
        $mform->hideIf('wallet_description', 'gateway', 'neq', utils::GATEWAY_WALLET);

        $mform->addElement('static', 'instapay_description',
                        get_string('instapay_description_label', 'paygw_transfer'),
                        utils::get_instapay_description());
        $mform->hideIf('instapay_description', 'gateway', 'neq', utils::GATEWAY_INSTAPAY);

        $mform->addElement('text', 'sender', get_string('sendernumber', 'paygw_transfer'), ['id' => 'vc_sendernumber']);
        $mform->setType('sender', PARAM_ALPHANUM);
        $mform->addHelpButton('sender', 'sendernumber', 'paygw_transfer');
        $mform->hideIf('sender', 'gateway', 'neq', utils::GATEWAY_WALLET);

        $mform->addElement('text', 'address', get_string('instapay_address', 'paygw_transfer'));
        $mform->setType('address', PARAM_TEXT);
        $mform->addHelpButton('address', 'instapay_address', 'paygw_transfer');
        $mform->hideIf('address', 'gateway', 'neq', utils::GATEWAY_INSTAPAY);

        $mform->addElement('text', 'amount', get_string('exact_amount', 'paygw_transfer'), ['id' => 'vc_amount']);
        $mform->setType('amount', PARAM_FLOAT);
        $mform->addRule('amount', get_string('required'), 'required', null, 'client');
        $mform->addRule('amount', 'Numbers only', 'numeric', null, 'client');
        $mform->addHelpButton('amount', 'exact_amount', 'paygw_transfer');

        $year = (int)userdate(time(), '%Y');
        $options = [
            'startyear' => $year - 3,
            'stopyear'  => $year + 3,
            'timezone'  => 99,
            'optional'  => false,
        ];
        $mform->addElement('date_selector', 'day', get_string('exact_day', 'paygw_transfer'), $options);
        $mform->addHelpButton('day', 'exact_day', 'paygw_transfer');

        $warning = get_string('creditwarning', 'paygw_transfer');
        $mform->addElement('static', 'warning', get_string('warning'), $warning);
        $mform->updateElementAttr('warning' , ['class' => 'alert alert-warning']);

        switch ($this->usetype) {
            case self::WALLET:
                if (!empty(get_config('enrol_wallet', 'catbalance'))) {
                    $cats = \enrol_wallet\local\utils\catoptions::get_all_options_with_discount();
                    if (!empty($cats)) {
                        $options = [];
                        $options[0] = get_string('site');
                        $options += $cats;
                        $mform->addElement('select', 'category', get_string('category'), $options);
                    }
                }
                $mform->addElement('hidden', 'return');
                $mform->setType('return', PARAM_LOCALURL);
                $mform->setDefault('return', qualified_me());
                break;
            case self::PAYMENT:
                $this->order = new order($this->_customdata['orderid']);
                $mform->addElement('hidden', 'orderid');
                $mform->setType('orderid', PARAM_INT);
                $mform->setDefault('orderid', $this->order->get_id());
                break;
            default:
                throw new coding_exception("Unexpected \$usetype {$this->usetype} passed to the form.");
        }

        $mform->addElement('submit', 'submit', get_string('submit'));

        $this->set_display_vertical();
    }

    /**
     * Return the order object belongs to this payment form.
     * Note: don't use it in case of topping up the wallet.
     * @return order|null
     */
    public function get_order(): ?order {
        return $this->order ?? null;
    }

    /**
     * Dummy stub method - override if you needed to perform some extra validation.
     * If there are errors return array of errors ("fieldname"=>"error message"),
     * otherwise true if ok.
     * Server side rules do not work for uploaded files, implement serverside rules here if needed.
     * @param $data: array of ("fieldname"=>value) of submitted data
     * @param $files: array of uploaded files "element_name"=>tmp_file_path
     */
    public function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);

        $gateway = $data['gateway'];
        if ($gateway === utils::GATEWAY_WALLET) {
            if (empty($data['sender'])) {
                $errors['sender'] = get_string('sender_required', 'paygw_transfer');
            } else if (!utils::validate_wallet_number($data['sender'])) {
                $errors['sender'] = get_string('sender_invalid', 'paygw_transfer');
            }
        } else if ($gateway === utils::GATEWAY_INSTAPAY && empty($data['address'])) {
            $errors['address'] = get_string('address_required', 'paygw_transfer');
        }

        if (!empty($data['amount']) && $data['amount'] <= 0) {
            $errors['amount'] = get_string('amount_invalid', 'paygw_transfer');
        }

        if ($data['day'] > time()) {
            $errors['day'] = get_string('nofuture', 'paygw_transfer');
        }

        if ($remain = static::is_blocked_try()) {
            $errors['submit'] = $remain;
        }

        if (!empty($errors)) {
            return $errors;
        }

        $valid = static::validate_data($data);
        if ($valid !== true) {
            $errors['sender'] =
            $errors['address'] =
            $errors['amount'] =
            $errors['day'] = $valid;
        }
        return $errors;
    }

    /**
     * Validate that the submitted data existed in the messages stored.
     * @param array|stdClass $data
     * @return true|string true if valid or error string.
     */
    public function validate_data(array|stdClass $data): true|string {
        $records = static::get_message_records($data);
        if (empty($records)) {
            return get_string('nomatchdata', 'paygw_transfer'); // No match.
        } else if (\count($records) > 1) {
            return get_string('multiplemsgs', 'paygw_transfer'); // Multiple transactions with the same data.
        } else {
            $record = array_pop($records);
            if (!empty($record->done)) {
                return get_string('usedbefore', 'paygw_transfer');
            }
            if ($order = $this->get_order()) {
                $cost = $order->get_cost();
                if ($data['amount'] < $cost - 1) {
                    $a = ['amount' => $data['amount'], 'cost' => $cost];
                    return get_string('insufficentamount', 'paygw_transfer', $a);
                }
            }
        }
        return true;
    }

    /**
     * Get the messages records that matches the submitted data.
     * @param array|stdClass $data
     * @return array
     */
    public static function get_message_records(array|stdClass $data) {
        $data = (object)$data;
        $sender = $data->sender ?? $data->address ?? '';
        if (!empty($sender)) {
            if (($data->gateway ?? 1) == utils::GATEWAY_WALLET) {
                $sender = utils::clean_wallet_number($sender);
            } else {
                $sender = utils::clean_instapay_account($sender);
            }
        }
        return message::get_message_records($data->amount, $sender, $data->day);
    }

    /**
     * Check if self-crediting is blocked due to multiple tries or not.
     * @return bool|string false if not blocked, or error string.
     */
    public static function is_blocked_try(): false|string {
        global $USER, $DB;
        $limit = get_config('paygw_transfer', 'limitbetween');

        if (empty($limit)) {
            return false;
        }

        $lasttry = $DB->get_records('paygw_transfer_tries', ['userid' => $USER->id], 'id DESC, timecreated DESC', '*', 0, 1);

        if (!empty($lasttry)) {
            $try = array_pop($lasttry);
            if (time() - $try->timecreated < $limit) {
                $remain = $limit + $try->timecreated - time();
                $hours = (int)($remain / HOURSECS);
                $min   = (int)(($remain % HOURSECS) / MINSECS);
                $sec   = (($remain % HOURSECS) % MINSECS);
                return get_string('cannotusenow', 'paygw_transfer', ['hours' => $hours, 'min' => $min, 'sec' => $sec]);
            }
        }

        return false;
    }

    /**
     * Register a try.
     * @return void
     */
    public static function add_try() {
        global $DB, $USER;
        // First clean old tries.
        $DB->delete_records('paygw_transfer_tries', ['userid' => $USER->id]);
        $DB->insert_record('paygw_transfer_tries', ['userid' => $USER->id, 'timecreated' => time()]);
    }

    /**
     * Process the payment.
     * @throws coding_exception
     * @return void
     */
    public function process() {
        global $USER, $DB, $SITE;
        if (!$data = $this->get_data()) {
            return;
        }
        $records = static::get_message_records($data);

        $message = new message(array_pop($records)->id);
        switch ($this->usetype) {
            case self::PAYMENT:
                $order = new order($data->orderid);
                $order->set_messageid($message->id, false);
                $order->payment_complete();
                $msg = get_string('payment_successful', 'paygw_transfer');
                $redirect = $order->get_redirect_url();
                break;
            case self::WALLET:
                $op = new \enrol_wallet\local\wallet\balance_op(0, optional_param('category', 0, PARAM_INT));
                $balancebefore = $op->get_valid_balance();
                $op->credit($message->get_amount(), $op::C_VC, 0, get_string('topup_desc', 'paygw_transfer'));
                $balanceafter = $op->get_valid_balance();

                $a = [
                    'amount' => $message->get_amount(),
                    'before' => $balancebefore,
                    'after'  => $balanceafter,
                    'credit' => $balanceafter - $balancebefore, // In case of conditional discount
                                                                // this may be different than the amount received.
                ];
                $msg = get_string('creditsuccess', 'paygw_transfer', $a);
                $redirect = new url($data->return);
                break;
            default:
                break; // Already thrown error in the definition.
        }

        $message->completed(subject: "Added by " . fullname($USER));

        $params = [
                    'sender'   => $data->sender ?? $data->address,
                    'amount'   => $data->amount,
                    'timefrom' => $data->day,
                    'timeto'   => $data->day + DAYSECS - 1,
                    'subject'  => "$message->subject in $SITE->fullname",
                ];
        paygw_transfer_other_mark_done($params);

        static::save_sender($data->sender ?? $data->address);
        if (!PHPUNIT_TEST) {
            redirect($redirect, $msg, null, 'success');
        }
    }

    /**
     * Save the sender data if it frequently used by the same user.
     * @param string $sender
     * @param int $userid
     * @param bool $modify Modify existence sender or add a new one.
     * @param int $minimum Minimum existence message to save this one, use 0 or -1 for forcing.
     * @return void
     */
    public static function save_sender(string $sender, int $userid = 0, bool $modify = false, $minimum = 3) {
        global $DB, $USER;
        if (!$userid) {
            $userid = $USER->id;
        }

        // Check if this sender is used by another user.
        $select = "sender = :sender AND receiverid IS NOT NULL AND receiverid != '' AND receiverid != :userid";
        $params = ['sender' => $sender, 'userid' => $userid];
        $notvalid1 = $DB->record_exists_select(webhook::MESSAGES_TABLE, $select, $params);

        // Check if the sender is already saved for another user.
        $select = "sender = :sender AND userid != :userid";
        $notvalid2 = $DB->record_exists_select(webhook::SAVED_TABLE, $select, $params);

        $valid = !$notvalid1 && !$notvalid2;
        if ($valid
            && ($smss = $DB->get_records(webhook::MESSAGES_TABLE, ['receiverid' => $userid, 'sender' => $sender]))
            && \count($smss) >= $minimum
            ) {
            if ($modify && $oldrecord = $DB->get_record(webhook::SAVED_TABLE, ['userid' => $userid])) {
                $oldrecord->sender = $sender;
                $oldrecord->timemodified = time();
                $DB->update_record(webhook::SAVED_TABLE, $oldrecord);
            } else if ($oldrecord = $DB->get_record(webhook::SAVED_TABLE, ['userid' => $userid, 'sender' => $sender])) {
                // Record with the same sender already existed.
                return;
            } else {
                $newrecord = new stdClass;
                $newrecord->sender = $sender;
                $newrecord->timemodified = time();
                $newrecord->timecreated = time();
                $newrecord->userid = $userid;
                $DB->insert_record(webhook::SAVED_TABLE, $newrecord);
            }
        }
    }
}
