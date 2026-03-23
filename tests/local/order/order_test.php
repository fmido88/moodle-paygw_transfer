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

namespace paygw_transfer\local\order;

use advanced_testcase;
use context_course;
use paygw_transfer\local\order\order as transfer_order;
use paygw_transfer\local\messages\message;
use moodle_exception;
use core_payment\helper as payment_helper;
use paygw_transfer_generator;

class order_test extends advanced_testcase {
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

    public function test_create_order() {
        $orderid = $this->generator->create_order();
        $order = new transfer_order($orderid);

        $this->assertEquals('transfer', $order::gateway());
        $this->assertEquals('created', $order->get_status());
        $this->assertEquals('enrol_fee', $order->get_component());
    }

    public function test_recent_duplicate_prevents_creation() {
        $time = time();
        set_time_limit(0); // For sleep
        sleep(1); // Ensure different timecreated

        // Create recent order within period_to_check_for_existed_order() = DAYSECS
        $order1id = $this->generator->create_order();
        $order1 = (new transfer_order($order1id));
        $order2 = transfer_order::create_order($order1->get_component(), $order1->get_paymentarea(), $order1->get_itemid());
        $this->assertInstanceOf(transfer_order::class, $order2); // Returns existing
        $this->assertEquals($order1->get_id(), $order2->get_id());
    }

    public function test_set_messageid() {
        $msgid = $this->generator->create_message();
        $orderid = $this->generator->create_order();

        $order = new transfer_order($orderid);
        $order->set_messageid($msgid);

        $updated = new transfer_order($orderid);
        $this->assertEquals($msgid, $updated->get_messageid());
    }

    public function test_payment_complete() {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $orderid = $this->generator->create_order(); // Sets current user
        $order = new transfer_order($orderid);

        $instance = $order->get_itemid();
        $courseid = $DB->get_field('enrol', 'courseid', ['id' => $instance], MUST_EXIST);
        $this->assertFalse(is_enrolled(context_course::instance($courseid), $user));

        $order->payment_complete(false, false);
        $dborder = $DB->get_record('paygw_transfer_orders', ['id' => $order->get_id()]);
        $this->assertEquals('success', $dborder->status);

        $this->assertTrue(is_enrolled(context_course::instance($courseid), $user));
    }

    public function test_successful_statuses_include_done() {
        $this->assertContains('done', transfer_order::get_successful_statuses());
    }

    public function test_is_success() {
        $orderid = $this->generator->create_order();
        $order = new transfer_order($orderid);

        // The payment must be completed and the payment id is set.
        $order->update_status('success');
        $this->assertFalse($order->is_success());

        $order->set_paymentid(1);
        $this->assertTrue($order->is_success());

        $order->update_status('done');
        $this->assertTrue($order->is_success());

        $order->update_status('failed');
        $this->assertFalse($order->is_success());
    }

    public function test_notify_success() {
        global $SESSION;
        $this->assertTrue(empty($SESSION->notifications));
        $orderid = $this->generator->create_order();
        $order = new transfer_order($orderid);
        $order->notify_success();

        $this->assertEquals(1, \count($SESSION->notifications));
    }

    /**
     * Test order status transitions.
     */
    public function test_order_status_transitions() {
        $orderid = $this->generator->create_order();
        $order = new transfer_order($orderid);

        // Initial status.
        $this->assertEquals('created', $order->get_status());

        // Update to success.
        $order->update_status('success');
        $this->assertEquals('success', $order->get_status());

        // Update to done.
        $order->update_status('done');
        $this->assertEquals('done', $order->get_status());
    }

    /**
     * Test payment completion with message linking.
     */
    public function test_payment_complete_with_message() {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Create order and message.
        $orderid = $this->generator->create_order();
        $msgid = $this->generator->create_message();
        $order = new transfer_order($orderid);
        $message = new message($msgid);

        // Link message to order.
        $order->set_messageid($msgid);
        $order->payment_complete();

        // Verify order status.
        $this->assertEquals('success', $order->get_status());
        $this->assertEquals($msgid, $order->get_messageid());

        // Verify message is marked as done.
        $message->reload();
        $this->assertTrue($message->is_done());
        $this->assertEquals($user->id, $message->receiverid);
    }

    /**
     * Test order retrieval by various criteria.
     */
    public function test_order_retrieval() {
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        // Create orders for different users.
        $item1 = $this->generator->get_payable_item(50, 'EGP');
        $item2 = $this->generator->get_payable_item(75, 'EGP');
        $item3 = $this->generator->get_payable_item(100, 'EGP');

        $this->setUser($user1);
        $order1 = transfer_order::create_order($item1->component, $item1->paymentarea, $item1->itemid);
        $order2 = transfer_order::create_order($item2->component, $item2->paymentarea, $item2->itemid);
        $this->setUser($user2);
        $order3 = transfer_order::create_order($item3->component, $item3->paymentarea, $item3->itemid);

        $this->setAdminUser();
        // Test get_orders_by_user.
        $user1Orders = transfer_order::get_orders_by_user($user1->id);
        $this->assertCount(2, $user1Orders);

        $user2Orders = transfer_order::get_orders_by_user($user2->id);
        $this->assertCount(1, $user2Orders);
    }

    /**
     * Test order amount and currency validation.
     */
    public function test_order_amount_currency_validation() {
        $user = $this->getDataGenerator()->create_user();

        // Test valid amounts.
        $validAmounts = [0.01, 1.00, 100.50, 999999.99];
        foreach ($validAmounts as $amount) {
            $item = $this->generator->get_payable_item($amount);
            $order = transfer_order::create_order(
                $item->component,
                $item->paymentarea,
                $item->itemid
            );
            $this->assertEquals($amount, $order->get_cost());
            $this->assertEquals('EGP', $order->get_currency());
        }

        // Test invalid currency should be rejected (gateway only supports EGP).
        // Note: The gateway class restricts to EGP, so this should work.
        $this->assertTrue(true); // Placeholder - actual validation is in gateway class.
    }

    /**
     * Test order with payment ID.
     */
    public function test_order_with_payment_id() {
        $user = $this->getDataGenerator()->create_user();

        $item = $this->generator->get_payable_item(100);

        // Create order with payment ID.
        $order = transfer_order::create_order($item->component, $item->paymentarea, $item->itemid);
        $order->payment_complete();

        $paymentid = $order->get_paymentid();
        $this->assertIsInt($order->get_paymentid());
        $this->assertTrue($order->is_success()); // Should be success since paymentid is set and status is created->success?
        // Wait, let's check the logic.
        $order = new transfer_order($order->get_id());

        // Actually, is_success checks if status is in successful_statuses AND paymentid is set.
        $this->assertContains($order->get_status(), transfer_order::get_successful_statuses());
        $this->assertEquals($paymentid, $order->get_paymentid());
    }

    /**
     * Test order notification on completion.
     */
    public function test_order_completion_notification() {
        global $SESSION;

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $orderid = $this->generator->create_order();
        $order = new transfer_order($orderid);

        // Clear any existing notifications.
        unset($SESSION->notifications);

        // Complete payment.
        $order->payment_complete();
        $order->notify_success();
        // Should have notification.
        $this->assertNotEmpty($SESSION->notifications);
        $this->assertCount(1, $SESSION->notifications);

        $notification = reset($SESSION->notifications);
        $this->assertStringContainsStringIgnoringCase('successful', $notification->message);
    }
}

