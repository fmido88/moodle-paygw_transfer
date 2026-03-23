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

use advanced_testcase;
use core\exception\coding_exception;
use paygw_transfer\local\messages\message;
use paygw_transfer\exceptions\secret_key_exception;
use paygw_transfer\local\utils\utils;

class webhook_test extends advanced_testcase {

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    public function test_webhook_vodafone_parse() {
        $secret = 'testsecret';
        set_config('secret', $secret, 'paygw_transfer');
        $message = testing::mock_vodafone_cash_message(150.5, '01012345678', 'John Doe', balance: 782, secret: $secret, temp: 'ar');
        $webhook = new webhook($message);

        $this->assertEquals(150.5, $webhook->get_amount());
        $this->assertEquals('01012345678', $webhook->get_sender());
        $this->assertStringNotContainsString(782, $webhook->get_message()); // Balance cleared

        $message = testing::mock_vodafone_cash_message(150.5, '01012345678', 'John Doe', balance: 782, secret: $secret, temp: 'en');
        $webhook = new webhook($message);

        $this->assertEquals(150.5, $webhook->get_amount());
        $this->assertEquals('01012345678', $webhook->get_sender());
        $this->assertStringNotContainsString(782, $webhook->get_message()); // Balance cleared
    }

    public function test_lot_random_messages() {
        global $DB;
        testing::generate_random_instapay_messages(500, true);
        testing::generate_random_vodafone_cash_messages(500, true);
        $this->assertEquals(1000, $DB->count_records(message::TABLE));
        // No error thrown. that's good.
    }
    public function test_webhook_instapay_parse() {
        $secret = 'secret123';
        set_config('secret', $secret, 'paygw_transfer');
        $message = testing::mock_instapay_message(250.0, 'john.doe@instapay', $secret);
        $webhook = new webhook($message);

        $this->assertEquals(250.0, $webhook->get_amount());
        $this->assertEquals('john.doe', $webhook->get_sender()); // Without @instapay
    }

    public function test_webhook_no_secret() {
        $message = testing::mock_vodafone_cash_message();
        $webhook = new webhook($message); // No config secret, should parse
        $this->assertNotEmpty($webhook->get_amount());
    }

    public function test_webhook_invalid_secret() {
        set_config('secret', 'requiredsecret', 'paygw_transfer');
        $message = 'wrongsecret You have received 100 EGP'; // Wrong secret

        $this->expectException(secret_key_exception::class);
        new webhook($message);
    }

    public function test_webhook_save_creates_message() {
        $secret = 'test';
        set_config('secret', $secret, 'paygw_transfer');
        $message = testing::mock_vodafone_cash_message(secret: $secret, temp: 'ar');
        $webhook = new webhook($message);
        $msg = $webhook->save();

        $this->assertInstanceOf(message::class, $msg);
        $this->assertEquals($webhook->get_amount(), $msg->get_amount());

        $message = testing::mock_vodafone_cash_message(secret: $secret, temp: 'en');
        $webhook = new webhook($message);
        $msg = $webhook->save();

        $this->assertInstanceOf(message::class, $msg);
        $this->assertEquals($webhook->get_amount(), $msg->get_amount());

        $message = testing::mock_instapay_message(secret: $secret);
        $webhook = new webhook($message);
        $msg = $webhook->save();

        $this->assertInstanceOf(message::class, $msg);
        $this->assertEquals($webhook->get_amount(), $msg->get_amount());
    }

    public function test_generate_random_messages() {
        $vfmessages = testing::generate_random_vodafone_cash_messages(3);
        $this->assertCount(3, $vfmessages);
        $this->assertInstanceOf(webhook::class, $vfmessages[0]);

        $instamessages = testing::generate_random_instapay_messages(2, false);
        $this->assertCount(2, $instamessages);
    }

    public function test_helpers() {
        $name = testing::get_random_name();
        $this->assertMatchesRegularExpression('/[\pL\pM\pN\s-]/u', $name); // Arabic/English names

        $phone = testing::get_random_phone_number();
        $this->assertMatchesRegularExpression('/^01[0125]\d{8}$/', $phone);

        $instapay = testing::get_random_instapay_sender();
        $this->assertMatchesRegularExpression('/^[a-z0-9.\-_]{7,15}$/', $instapay);
    }

    /**
     * Test webhook parsing with various Arabic amount formats.
     */
    public function test_webhook_arabic_amount_formats() {
        $secret = 'test';
        set_config('secret', $secret, 'paygw_transfer');

        // Test different Arabic amount patterns.
        $testCases = [
            'استلام مبلغ 100.50 جنيه مصري من' => 100.50,
            'استلام مبلغ 250 جنيه من' => 250.00,
            'استلام مبلغ 75.25 جنيه مصري من' => 75.25,
            'استلام مبلغ 1000 جنيه مصري من' => 1000.00,
        ];

        foreach ($testCases as $message => $expectedAmount) {
            $fullMessage = $secret . ' ' . $message . ' 01012345678';
            $webhook = new webhook($fullMessage);
            $this->assertEquals($expectedAmount, $webhook->get_amount(), "Failed for message: $message");
        }
    }

    /**
     * Test webhook parsing with English amount formats.
     */
    public function test_webhook_english_amount_formats() {
        $secret = 'test';
        set_config('secret', $secret, 'paygw_transfer');

        $testCases = [
            'You have received 50 EGP from' => 50.00,
            'You have received 125.75 L.E from' => 125.75,
            'You have received 200 LE from' => 200.00,
            'You have received 99.99 EGP from' => 99.99,
        ];

        foreach ($testCases as $message => $expectedAmount) {
            $fullMessage = $secret . ' ' . $message . ' 01012345678';
            $webhook = new webhook($fullMessage);
            $this->assertEquals($expectedAmount, $webhook->get_amount(), "Failed for message: $message");
        }
    }

    /**
     * Test webhook parsing with mixed Arabic/English content.
     */
    public function test_webhook_mixed_language_messages() {
        $secret = 'mixed';
        set_config('secret', $secret, 'paygw_transfer');

        $message = testing::mock_vodafone_cash_message('150', '01055556666', secret: $secret);
        $webhook = new webhook($message);

        $this->assertEquals(150.00, $webhook->get_amount());
        $this->assertEquals('01055556666', $webhook->get_sender());
    }

    /**
     * Test webhook parsing with various sender formats.
     */
    public function test_webhook_sender_formats() {
        $secret = 'sender';
        set_config('secret', $secret, 'paygw_transfer');

        // Vodafone wallet numbers.
        $testCases = [
            '01012345678' => '01012345678',
            '01198765432' => '01198765432',
            '01234567890' => '01234567890',
            '+201012345678' => '01012345678', // Should normalize
        ];

        foreach ($testCases as $sender => $expected) {
            $message = $secret . ' You have received 100 EGP from ' . $sender;
            $webhook = new webhook($message);
            $this->assertEquals($expected, $webhook->get_sender(), "Failed for sender: $sender");
        }

        // InstaPay addresses.
        $instapayCases = [
            'user.name@instapay' => 'user.name',
            'test_user123@instapay' => 'test_user123',
            'simple@instapay' => 'simple',
        ];

        foreach ($instapayCases as $sender => $expected) {
            $message = $secret . ' You have received 100 EGP from ' . $sender;
            $webhook = new webhook($message);
            $this->assertEquals($expected, $webhook->get_sender(), "Failed for InstaPay sender: $sender");
        }
    }

    /**
     * Test balance clearing functionality.
     */
    public function test_webhook_balance_clearing() {
        $secret = 'balance';
        set_config('secret', $secret, 'paygw_transfer');

        $message = testing::mock_vodafone_cash_message(200, balance: 1500, secret: $secret, temp: 'ar');
        $webhook = new webhook($message);

        $this->assertEquals(200.00, $webhook->get_amount());
        $this->assertStringNotContainsString('1500', $webhook->get_message());
        $this->assertStringNotContainsString('balance', $webhook->get_message());

        $message = testing::mock_vodafone_cash_message(200, balance: 1500, secret: $secret, temp: 'en');
        $webhook = new webhook($message);

        $this->assertEquals(200.00, $webhook->get_amount());
        $this->assertStringNotContainsString('1500', $webhook->get_message());
        $this->assertStringNotContainsString('balance', $webhook->get_message());
    }

    /**
     * Test webhook with extreme amounts.
     */
    public function test_webhook_extreme_amounts() {
        $secret = 'extreme';
        set_config('secret', $secret, 'paygw_transfer');

        $testCases = [
            'You have received 0.01 EGP from 01012345678' => 0.01,
            'You have received 999999.99 EGP from 01012345678' => 999999.99,
            'استلام مبلغ 1 جنيه مصري من 01012345678' => 1.00,
            'استلام مبلغ 1000000 جنيه من 01012345678' => 1000000.00,
        ];

        foreach ($testCases as $message => $expectedAmount) {
            $fullMessage = $secret . ' ' . $message;
            $webhook = new webhook($fullMessage);
            $this->assertEquals($expectedAmount, $webhook->get_amount(), "Failed for amount: $expectedAmount");
        }
    }

    /**
     * Test webhook parsing failures.
     */
    public function test_webhook_parsing_failures() {
        $secret = 'fail';
        set_config('secret', $secret, 'paygw_transfer');

        // Messages without amounts should fail gracefully.
        $messages = [
            $secret . ' Some random message without amount',
            $secret . ' استلام من دون مبلغ',
            $secret . ' Empty amount field',
        ];

        foreach ($messages as $message) {
            $this->expectException(coding_exception::class);
            $webhook = new webhook($message);
            $this->assertNull($webhook->get_amount(), "Should not parse amount from: $message");
        }
    }

    /**
     * Test webhook with typos and variations.
     */
    public function test_webhook_typos_and_variations() {
        $secret = 'typo';
        set_config('secret', $secret, 'paygw_transfer');

        // Test common typos in Arabic.
        $message = $secret . ' استلام مبلغ 100 جنيه مصرى من 01012345678'; // مصرى instead of مصري
        $webhook = new webhook($message);
        $this->assertEquals(100.00, $webhook->get_amount());

        // Test LE variation.
        $message = $secret . ' You have received 75 L.E from 01012345678';
        $webhook = new webhook($message);
        $this->assertEquals(75.00, $webhook->get_amount());
    }

    /**
     * Test concurrent webhook processing.
     */
    public function test_concurrent_webhook_processing() {
        $secret = 'concurrent';
        set_config('secret', $secret, 'paygw_transfer');

        // Simulate multiple webhooks with same data (should create separate messages).
        $message1 = testing::mock_vodafone_cash_message(100.00, '01011111111', 'User1', secret: $secret);
        $message2 = testing::mock_vodafone_cash_message(100.00, '01011111111', 'User1', secret: $secret);

        $webhook1 = new webhook($message1);
        $webhook2 = new webhook($message2);

        $msg1 = $webhook1->save();
        $msg2 = $webhook2->save();

        $this->assertNotEquals($msg1->id, $msg2->id);
        $this->assertEquals($msg1->get_amount(), $msg2->get_amount());
        $this->assertEquals($msg1->sender, $msg2->sender);
    }
}

