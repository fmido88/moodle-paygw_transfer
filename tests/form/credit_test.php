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

namespace paygw_transfer\form;

use advanced_testcase;
use paygw_transfer\form\credit;
use paygw_transfer\local\order\order as transfer_order;
use paygw_transfer_generator;

class credit_test extends advanced_testcase {
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

    public function test_validation_valid_wallet_data() {
        $time = time();
        $this->generator->create_message(100.0, '01012345678', $time);

        $mform = new credit(null, ['type' => credit::WALLET]);
        $data = [
            'gateway' => 1, // WALLET
            'sender'  => '01012345678',
            'amount'  => 100.0,
            'day'     => $time,
        ];
        $errors = $mform->validation($data, []);
        ob_start();
        var_dump($errors);
        $this->assertEmpty($errors, ob_get_clean());
    }

    public function test_validation_invalid_sender() {
        $mform = new credit(null, ['type' => credit::WALLET]);
        $data = ['gateway' => 1, 'sender' => 'invalid', 'amount' => 100, 'day' => time()];
        $errors = $mform->validation($data, []);
        $this->assertArrayHasKey('sender', $errors);
    }

    public function test_validation_no_match() {
        $mform = new credit(null, ['type' => credit::WALLET]);
        $data = ['gateway' => 1, 'sender' => '01099999999', 'amount' => 999, 'day' => time()];
        $errors = $mform->validation($data, []);
        $this->assertArrayHasKey('sender', $errors); // nomatchdata
    }

    public function test_validation_multiple_matches() {
        $time = time();
        $this->generator->create_message(100.0, '01012345678', $time);
        $this->generator->create_message(100.0, '01012345678', $time);

        $mform = new credit(null, ['type' => credit::WALLET]);
        $data = ['gateway' => 1, 'sender' => '01012345678', 'amount' => 100, 'day' => $time];
        $errors = $mform->validation($data, []);
        $this->assertArrayHasKey('sender', $errors); // multiplemsgs
    }

    public function test_validation_already_done() {
        $time = time();
        $this->generator->create_message(100.0, '01012345678', $time, true);

        $mform = new credit(null, ['type' => credit::WALLET]);
        $data = ['gateway' => 1, 'sender' => '01012345678', 'amount' => 100, 'day' => $time];
        $errors = $mform->validation($data, []);
        $this->assertArrayHasKey('sender', $errors); // usedbefore
    }

    public function test_process_wallet_topup() {
        $this->resetAfterTest(false); // Allow DB changes to persist for assert
        $time = time();
        $msgid = $this->generator->create_message(200.0, '01012345678', $time);
        $data = [
            'gateway' => 1,
            'sender' => '01012345678',
            'amount' => 200.0,
            'day' => $time,
            'return' => '/testreturn',
            'category' => 0,
        ];
        credit::mock_submit($data);
        $mform = new credit(null, ['type' => credit::WALLET]);

        $mform->process(); // Private, test via reflection or observe DB

        $msg = new \paygw_transfer\local\messages\message($msgid);
        $this->assertTrue($msg->is_done());
    }

    // Note: Full process_payment requires enrol_wallet integration; test DB state changes
}

