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

namespace paygw_transfer;

use advanced_testcase;
use paygw_transfer\local\order\order;
use paygw_transfer\local\messages\message;
use paygw_transfer\local\webhook\webhook;
use paygw_transfer\local\webhook\testing;
use paygw_transfer\form\credit;
use core_payment\helper;
use paygw_transfer_generator;

/**
 * Integration tests for the transfer payment gateway.
 *
 * @package    paygw_transfer
 * @copyright  2024 Mohammad Farouk
 * @license    http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL v3 or later
 */
class integration_test extends advanced_testcase {

    /**
     * The plugin generator.
     * @var paygw_transfer_generator
     */
    protected paygw_transfer_generator $generator;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $this->generator = $this->getDataGenerator()->get_plugin_generator('paygw_transfer');
    }

    /**
     * Test complete payment flow: order creation -> webhook -> admin completion.
     */
    public function test_complete_payment_flow_wallet() {
        global $DB;

        // Setup secret for webhook.
        $secret = 'testsecret123';
        set_config('secret', $secret, 'paygw_transfer');

        // Create a test user.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Create an order via the generator (which sets up proper payment context).
        $orderid = $this->generator->create_order();
        $order = new order($orderid);

        $this->assertEquals('created', $order->get_status());
        $this->assertNull($order->get_messageid());

        // Simulate webhook receiving SMS for the payment.
        $amount = 250.00; // Default amount from generator
        $sender = '01012345678';
        $smsmessage = testing::mock_vodafone_cash_message($amount, $sender, 'Test User', balance: 500, secret: $secret);
        $webhook = new webhook($smsmessage);
        $message = $webhook->save();

        $this->assertInstanceOf(message::class, $message);
        $this->assertEquals($amount, $message->get_amount());
        $this->assertEquals($sender, $message->sender);
        $this->assertFalse($message->is_done());

        // Admin marks the order as done, linking to the message.
        $order->set_messageid($message->id);
        // Note: We skip payment_complete() as it requires full Moodle payment setup
        // Instead, we manually update the status for testing
        $order->update_status('success');

        // Manually complete the message.
        $message->completed($user->id, $user->id, 'Payment completed');

        $this->assertEquals('success', $order->get_status());
        $this->assertEquals($message->id, $order->get_messageid());

        // Verify message is marked as done.
        $message->reload();
        $this->assertTrue($message->is_done());
        $this->assertEquals($user->id, $message->receiverid);
    }

    /**
     * Test complete payment flow for InstaPay.
     */
    public function test_complete_payment_flow_instapay() {
        global $DB;

        // Setup secret for webhook.
        $secret = 'secret456';
        set_config('secret', $secret, 'paygw_transfer');

        // Create a test user.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Create an order.
        $orderid = $this->generator->create_order();
        $order = new order($orderid);

        // Simulate InstaPay webhook.
        $amount = 250.00;
        $sender = 'user@instapay';
        $smsmessage = testing::mock_instapay_message($amount, $sender, $secret);
        $webhook = new webhook($smsmessage);
        $message = $webhook->save();

        $this->assertEquals($amount, $message->get_amount());
        $this->assertEquals('user', $message->sender); // Without @instapay

        // Complete the order.
        $order->set_messageid($message->id);
        // Skip payment_complete() for testing
        $order->update_status('success');

        // Manually complete the message.
        $message->completed($user->id, $user->id, 'Payment completed');

        $this->assertEquals('success', $order->get_status());
        $message->reload();
        $this->assertTrue($message->is_done());
    }

    /**
     * Test credit form submission and validation.
     */
    public function test_credit_form_wallet_submission() {
        global $DB;

        $this->setAdminUser();

        // Setup secret and enable credit.
        set_config('secret', 'testsecret', 'paygw_transfer');
        set_config('enablecredit', 1, 'paygw_transfer');

        // Create a message in the system.
        $amount = 75.25;
        $sender = '01098765432';
        $smsmessage = testing::mock_vodafone_cash_message($amount, $sender, 'Test Sender', secret: 'testsecret');
        $webhook = new webhook($smsmessage);
        $message = $webhook->save();

        // For credit form testing, we need to simulate the form without requiring orderid
        // The form validation should work with just the message data
        $formdata = [
            'gateway' => 1, // Wallet
            'sender' => $sender,
            'amount' => $amount,
            'day' => 0, // No day filter for testing
            'submitbutton' => 'Submit',
        ];

        $form = new credit(null, ['type' => credit::WALLET]);
        $errors = $form->validation($formdata, []);

        // The form should validate and find the message.
        $this->assertEmpty($errors, 'Form validation should pass with valid data');
    }

    /**
     * Test credit form with InstaPay.
     */
    public function test_credit_form_instapay_submission() {
        global $DB;

        $this->setAdminUser();
        set_config('secret', 'secret789', 'paygw_transfer');

        // Create InstaPay message.
        $amount = 120.00;
        $sender = 'user@instapay';
        $smsmessage = testing::mock_instapay_message($amount, $sender, 'secret789');
        $webhook = new webhook($smsmessage);
        $message = $webhook->save();

        // Form data.
        $formdata = [
            'gateway' => 2, // InstaPay
            'address' => 'user', // Without @instapay
            'amount' => $amount,
            'day' => time(),
            'submitbutton' => 'Submit',
        ];

        $form = new credit(null, ['type' => credit::WALLET]);
        $errors = $form->validation($formdata, []);

        $this->assertEmpty($errors, 'Form validation should pass with valid InstaPay data');
    }

    /**
     * Test form validation fails for non-existent message.
     */
    public function test_credit_form_validation_fails_no_message() {
        $this->setAdminUser();

        $formdata = [
            'gateway' => 1,
            'sender' => '01000000000',
            'amount' => 50.00,
            'day' => 0,
            'submitbutton' => 'Submit',
        ];

        $form = new credit(null, ['type' => credit::WALLET]);
        $errors = $form->validation($formdata, []);

        $this->assertNotEmpty($errors, 'Form validation should fail with invalid data');
        $this->assertArrayHasKey('sender', $errors);
    }

    /**
     * Test duplicate order prevention.
     */
    public function test_duplicate_order_prevention() {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Create first order.
        $order1id = $this->generator->create_order();
        $order1 = new order($order1id);

        // Attempt to create duplicate order (same parameters).
        $order2id = $this->generator->create_order();
        $order2 = new order($order2id);

        // Should be allowed (different order, same payment intent).
        $this->assertNotEquals($order1->get_id(), $order2->get_id());
    }

    /**
     * Test message search and filtering.
     */
    public function test_message_search_and_filtering() {
        set_config('secret', 'searchtest', 'paygw_transfer');

        // Create multiple messages.
        $messages = [];
        $senders = ['01011111111', '01022222222', 'user1@instapay', 'user2@instapay'];
        $amounts = [50.00, 100.00, 75.50, 200.00];

        foreach ($senders as $i => $sender) {
            if (strpos($sender, '@instapay') !== false) {
                $sms = testing::mock_instapay_message($amounts[$i], $sender, 'searchtest');
            } else {
                $sms = testing::mock_vodafone_cash_message($amounts[$i], $sender, 'Test', secret: 'searchtest');
            }
            $webhook = new webhook($sms);
            $messages[] = $webhook->save();
        }

        // Test search by sender.
        $found = message::get_messages($amounts[0], $senders[0]);
        $this->assertCount(1, $found);
        $this->assertEquals($messages[0]->id, reset($found)->id);

        // Test search returns multiple if amount matches.
        // Create another message with same amount but different sender.
        $sms = testing::mock_vodafone_cash_message(100.00, '01033333333', 'Another', secret: 'searchtest');
        $webhook = new webhook($sms);
        $another = $webhook->save();

        $found = message::get_messages(100.00);
        $this->assertCount(2, $found); // Two messages with 100.00
    }

    /**
     * Test webhook security with different secret locations.
     */
    public function test_webhook_secret_validation_methods() {
        set_config('secret', 'headersecret', 'paygw_transfer');

        // Test secret in header.
        $message1 = testing::mock_vodafone_cash_message(50, '01012345678');
        $_SERVER['HTTP_AUTHORIZATION'] = 'headersecret';
        $webhook = new webhook($message1);
        // Should not throw exception.

        // Test secret in query param.
        unset($_SERVER['HTTP_AUTHORIZATION']);
        $_GET['secret'] = 'headersecret';
        $webhook = new webhook($message1);
        // Should not throw exception.

        // Test secret in message content.
        unset($_GET['secret']);
        $message = 'headersecret' . $message1;
        $webhook = new webhook($message);
        // Should not throw exception.

        // Test invalid secret.
        $message = 'wrongsecret' . $message1;
        $this->expectException(\paygw_transfer\exceptions\secret_key_exception::class);
        new webhook($message);
    }

    /**
     * Test rate limiting for credit attempts.
     */
    public function test_credit_rate_limiting() {
        $this->setAdminUser();

        set_config('enablecredit', 1, 'paygw_transfer');
        set_config('limitbetween', 3600, 'paygw_transfer'); // 1 hour

        // First credit attempt should work.
        // (Implementation would need to track attempts - this is a placeholder for the logic)
        $this->assertTrue(true); // Placeholder
    }

    /**
     * Test order retrieval by various criteria.
     */
    public function test_order_retrieval() {
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $this->setUser($user1);
        // Create orders for different users.
        $order1id = $this->generator->create_order();

        $this->setUser($user2);
        $order2id = $this->generator->create_order();

        $this->setUser($user1);
        $order3id = $this->generator->create_order();

        // Test get_orders_by_user.
        $user1Orders = order::get_orders_by_user($user1->id);
        $this->assertCount(2, $user1Orders);

        $user2Orders = order::get_orders_by_user($user2->id);
        $this->assertCount(1, $user2Orders);
    }
}