<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software...

namespace paygw_transfer\local\utils;

use advanced_testcase;
use core_payment\local\entities\payable;
use paygw_transfer_generator;

class utils_test extends advanced_testcase {

    public function test_wallet_number_validation() {
        $this->assertTrue(utils::validate_wallet_number('01012345678'));
        $this->assertTrue(utils::validate_wallet_number('01 012 345 678'));
        $this->assertFalse(utils::validate_wallet_number('0123456789')); // Too short
        $this->assertFalse(utils::validate_wallet_number('01912345678')); // Invalid operator
        $this->assertEquals('01012345678', utils::clean_wallet_number('+201012345678'));
    }

    public function test_get_gateways_options() {
        $options = utils::get_gateways_options();
        $this->assertArrayHasKey(1, $options); // WALLET
        $this->assertArrayHasKey(2, $options); // INSTAPAY
    }

    /**
     * Test wallet number cleaning and validation extensively.
     */
    public function test_wallet_number_cleaning_validation() {
        // Valid numbers.
        $validNumbers = [
            '01012345678',
            '01198765432',
            '01234567890',
            '01555556666',
            '010 123 456 78', // With spaces
            '+201012345678',  // International format
            '00201112345678',  // International with 00
        ];

        foreach ($validNumbers as $number) {
            $this->assertTrue(utils::validate_wallet_number($number), "Should validate: $number");
            $cleaned = utils::clean_wallet_number($number);
            $this->assertMatchesRegularExpression('/^01[0125]\d{8}$/', $cleaned, "Cleaned number should be valid: $cleaned");
        }

        // Invalid numbers.
        $invalidNumbers = [
            '0123456789',     // Too short
            '010123456789',   // Too long
            '01912345678',    // Invalid operator (9)
            '01312345678',    // Invalid operator (3)
            '01612345678',    // Invalid operator (6)
            '01712345678',    // Invalid operator (7)
            '01812345678',    // Invalid operator (8)
            'abc12345678',    // Non-numeric
            '0101234567',     // Too short
            '',               // Empty
        ];

        foreach ($invalidNumbers as $number) {
            $this->assertFalse(utils::validate_wallet_number($number), "Should not validate: $number");
        }
    }

    /**
     * Test InstaPay account cleaning and validation.
     */
    public function test_instapay_account_handling() {
        // Test cleaning (should be lowercase, no @instapay).
        $testCases = [
            'USER@INSTAPAY' => 'user',
            'User.Name@InstaPay' => 'user.name',
            'test_user123@instapay' => 'test_user123',
            'simple' => 'simple',
            'user@other.com' => 'userother.com', // Should not remove if not @instapay
        ];

        foreach ($testCases as $input => $expected) {
            $cleaned = utils::clean_instapay_account($input);
            $this->assertEquals($expected, $cleaned, "Cleaning failed for: $input");
        }
    }

    /**
     * Test gateway descriptions.
     */
    public function test_gateway_descriptions() {
        $this->resetAfterTest();
        // Set up config for descriptions.
        set_config('walletnumbers', '<p>Wallet instructions</p>', 'paygw_transfer');
        set_config('instapayaddresses', '<p>InstaPay instructions</p>', 'paygw_transfer');

        $walletDesc = utils::get_wallet_description();
        $this->assertStringContainsString('Wallet instructions', $walletDesc);

        $instapayDesc = utils::get_instapay_description();
        $this->assertStringContainsString('InstaPay instructions', $instapayDesc);
    }

    /**
     * Test HTTP header extraction.
     */
    public function test_http_header_extraction() {
        $this->resetAfterTest();
        // Mock server variables.
        $_SERVER['HTTP_X_CUSTOM_HEADER'] = 'test_value';
        $_SERVER['HTTP_X_SECRET_KEY'] = 'secret123';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer token456';

        $headers = utils::get_headers();

        $this->assertEquals('test_value', $headers['X-Custom-Header']);
        $this->assertEquals('secret123', $headers['X-Secret-Key']);
        $this->assertEquals('Bearer token456', $headers['Authorization']);
    }

    /**
     * Test form data extraction from various sources.
     */
    public function test_form_data_extraction() {
        // Test date range parsing.
        $formData = (object)[
            'datefrom' => ['day' => 15, 'month' => 3, 'year' => 2024],
            'dateto' => ['day' => 20, 'month' => 3, 'year' => 2024],
        ];

        $dateRange['from'] = utils::extract_date_from_data($formData, 'from');
        $dateRange['to'] = utils::extract_date_from_data($formData, 'to');

        // Should be Unix timestamps.
        $this->assertIsInt($dateRange['from']);
        $this->assertIsInt($dateRange['to']);
        $this->assertGreaterThan($dateRange['from'], $dateRange['to']); // From should be before to? Wait, logic.
        // Actually, depending on implementation, but should be valid timestamps.
    }

    /**
     * Test gateway constants.
     */
    public function test_gateway_constants() {
        $this->assertEquals(1, utils::GATEWAY_WALLET);
        $this->assertEquals(2, utils::GATEWAY_INSTAPAY);

        $options = utils::get_gateways_options();
        $this->assertEquals('Mobile Wallet', $options[utils::GATEWAY_WALLET]);
        $this->assertEquals('InstaPay', $options[utils::GATEWAY_INSTAPAY]);
    }

    /**
     * Test config class functionality.
     */
    public function test_config_class() {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        // Create payment account.
        /**
         * @var paygw_transfer_generator
         */
        $gen = $this->getDataGenerator()->get_plugin_generator('paygw_transfer');

        $item = $gen->get_payable_item();
        // Test config creation from account.
        $config = config::made_from_accountid($item->accountid);
        $this->assertInstanceOf(config::class, $config);

        // Test config from payable.
        $payable = new payable($item->amount, $item->currency, $item->accountid);
        $config2 = config::made_from_payable($payable);
        $this->assertInstanceOf(config::class, $config2);

        // Test config from item.
        $config3 = config::made_from_item($item->component, $item->currency, $item->itemid);
        $this->assertInstanceOf(config::class, $config3);
    }

    /**
     * Test edge cases for wallet number validation.
     */
    public function test_wallet_number_edge_cases() {
        // Test boundary cases.
        $this->assertTrue(utils::validate_wallet_number('01000000000')); // Min valid
        $this->assertTrue(utils::validate_wallet_number('01599999999')); // Max valid

        // Test with various separators.
        $this->assertTrue(utils::validate_wallet_number('010 123 456 78'));
        $this->assertFalse(utils::validate_wallet_number('010-123-456-78'));
        $this->assertFalse(utils::validate_wallet_number('010.123.456.78'));

        // Test international formats.
        $this->assertEquals('01012345678', utils::clean_wallet_number('+20 10 1234 5678'));
        $this->assertEquals('01198765432', utils::clean_wallet_number('00201198765432'));
    }

    /**
     * Test InstaPay validation (basic email-like format).
     */
    public function test_instapay_validation() {
        // Valid InstaPay formats (basic checks).
        $validInstapay = [
            'user@instapay',
            'user.name@instapay',
            'user_name@instapay',
            'user-name@instapay',
            'user123@instapay',
        ];

        foreach ($validInstapay as $account) {
            $cleaned = utils::clean_instapay_account($account);
            $this->assertStringNotContainsString('@instapay', $cleaned); // Should remove @instapay
        }

        // Test that other domains are preserved.
        $this->assertEquals('userother.com', utils::clean_instapay_account('user@other.com'));
    }
}

