<?php

use core_payment\helper;
use paygw_transfer\local\messages\message;
use paygw_transfer\local\order\order;
use paygw_transfer\local\utils\utils;
use paygw_transfer\local\webhook\testing;

/*
 * Data generator for paygw_transfer tests.
 */

defined('MOODLE_INTERNAL') || die();

class paygw_transfer_generator extends \testing_data_generator {
    public function create_message(
        float $amount = 100.0,
        string $sender = '01012345678',
        ?int $timecreated = null,
        bool $done = false
    ): int {
        global $DB;

        if (!$timecreated) {
            $timecreated = time();
        }

        $sender = utils::clean_instapay_account($sender);
        if (utils::validate_wallet_number($sender)) {
            $message = testing::mock_vodafone_cash_message($amount, $sender);
        } else {
            $message = testing::mock_instapay_message($amount, $sender);
        }

        $record               = new stdClass();
        $record->message      = $message;
        $record->sender       = $sender;
        $record->amount       = $amount;
        $record->done         = $done ? 1 : 0;
        $record->timecreated  = $timecreated;
        $record->timemodified = $timecreated;
        $record->subject      = '';
        $record->receiverid   = 0;
        $record->chargerid    = 0;

        return $DB->insert_record('paygw_transfer_messages', $record);
    }

    public function create_order(?int $messageid = null): int {
        $payable = self::get_payable_item();
        $order   = order::create_order(
            $payable->component,
            $payable->paymentarea,
            $payable->itemid
        );

        if ($messageid) {
            $order->set_messageid($messageid, true);
        }

        return $order->get_id();
    }

    public function get_payable_item(float $cost = 250, string $currency = 'EGP'): stdClass {
        global $DB;
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $feeplugin   = enrol_get_plugin('fee');
        $generator   = phpunit_util::get_data_generator();
        \core\plugininfo\paygw::enable_plugin('transfer', 1);

        /**
         * @var core_payment_generator
         */
        $paymentgen = $generator->get_plugin_generator('core_payment');
        $account    = $paymentgen->create_payment_account(['gateways' => 'paypal,transfer']);
        $course     = $generator->create_course();

        $data = [
            'courseid'   => $course->id,
            'customint1' => $account->get('id'),
            'cost'       => $cost,
            'currency'   => $currency,
            'roleid'     => $studentrole->id,
        ];
        $itemid  = $feeplugin->add_instance($course, $data);
        $payable = helper::get_payable('enrol_fee', 'fee', $itemid);

        return (object)[
            'itemid'      => $itemid,
            'amount'      => $payable->get_amount(),
            'currency'    => $payable->get_currency(),
            'accountid'   => $payable->get_account_id(),
            'component'   => 'enrol_fee',
            'paymentarea' => 'fee',
        ];
    }
}
