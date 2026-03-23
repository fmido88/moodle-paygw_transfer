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

namespace paygw_transfer\local\messages;

use advanced_testcase;
use paygw_transfer\local\messages\message;
use coding_exception;
use paygw_transfer\local\utils\utils;
use paygw_transfer_generator;

class message_test extends advanced_testcase {

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

    public function test_create_message_object() {
        $id = $this->generator->create_message(150.5, '01098765432', time() - 3600);
        $msg = new message($id);

        $this->assertEquals($id, $msg->id);
        $this->assertEquals('01098765432', $msg->sender);
        $this->assertEquals(150.5, $msg->amount);
        $this->assertEquals(150.5, $msg->get_amount());
        $this->assertFalse($msg->is_done());
    }

    public function test_magic_get_set() {
        $id = $this->generator->create_message();
        $msg = new message($id);

        // Test __get
        $this->assertNotEmpty($msg->message);
        $this->assertNotEmpty($msg->timecreated);

        // Test __set (modifiable fields)
        $msg->receiverid = 123;
        $msg->update();
        $updated = new message($id);
        $this->assertEquals(123, $updated->receiverid);

        // Test immutable fields throw exception
        $this->expectException(coding_exception::class);
        $msg->sender = 999;
    }

    public function test_set_done() {
        $id = $this->generator->create_message();
        $msg = new message($id);
        $msg->set_done(true);
        $this->assertTrue($msg->is_done());

        $msg->update();
        $updated = new message($id);
        $this->assertTrue($updated->is_done());
    }

    public function test_completed() {
        $this->setUser(0); // Guest
        $id = $this->generator->create_message(200.0);
        $msg = new message($id);
        $msg->completed(456, 789, 'Test completion');

        $this->assertEquals(456, $msg->receiverid);
        $this->assertEquals(789, $msg->chargerid);
        $this->assertEquals('Test completion', $msg->subject);
        $this->assertTrue($msg->is_done());
    }

    public function test_get_message_records() {
        $time = time();
        $this->generator->create_message(100.0, '01011111111', $time - 1);
        $this->generator->create_message(100.0, '01022222222', $time);
        $this->generator->create_message(200.0, '01011111111', $time + 1, true); // Done

        $records = message::get_message_records(100.0, '01011111111', $time);
        $this->assertCount(1, $records); // Undone only by default? Wait, default undoneonly=true

        $all = message::get_message_records(100.0, null, null, false);
        $this->assertCount(2, $all);
    }

    public function test_get_messages() {
        $time = time();
        $this->generator->create_message(150.0);
        $this->generator->create_message(150.0, 'diff-sender');
        $this->generator->create_message(300.0, 'diff-sender');

        $msgs = message::get_messages(150.0, null, $time);
        $this->assertCount(2, $msgs);
        $this->assertInstanceOf(message::class, reset($msgs));
    }

    public function test_search_messages() {
        $this->generator->create_message(100.0, '010123', null, false);
        $this->generator->create_message(200.0, '010456', null, true); // Done, excluded

        $results = message::search_messages('010123', 0, 10);
        $this->assertCount(1, $results);
        $this->assertEquals('010123', reset($results)->sender);
    }

    /**
     * Test message creation with various data.
     */
    public function test_message_creation_variations() {
        // Test with different amounts and senders.
        $testCases = [
            [50.00, '01011111111', 'Test message 1'],
            [100.50, 'user@test@instapay', 'Test message 2'],
            [250.75, '01222222222', 'Test message 3'],
            [0.01, 'simple@instapay', 'Minimal amount'],
            [99999.99, '01099999999', 'Maximum amount'],
        ];

        foreach ($testCases as $i => $case) {
            list($amount, $sender, $messageText) = $case;
            $id = $this->generator->create_message($amount, $sender, time() + $i, false);
            $msg = new message($id);

            $sender = utils::clean_instapay_account($sender);
            $this->assertEquals($amount, $msg->get_amount());
            $this->assertEquals($sender, $msg->sender);
            $this->assertFalse($msg->is_done());
        }
    }

    /**
     * Test message update functionality.
     */
    public function test_message_update() {
        $id = $this->generator->create_message(100.00, '01012345678');
        $msg = new message($id);

        // Update modifiable fields.
        $msg->receiverid = 123;
        $msg->chargerid = 456;
        $msg->subject = 'Updated subject';
        $msg->update();

        // Reload and verify.
        $updated = new message($id);
        $this->assertEquals(123, $updated->receiverid);
        $this->assertEquals(456, $updated->chargerid);
        $this->assertEquals('Updated subject', $updated->subject);
    }

    /**
     * Test message deletion.
     */
    public function test_message_deletion() {
        global $DB;

        $id = $this->generator->create_message();
        $this->assertTrue($DB->record_exists(message::TABLE, ['id' => $id]));

        $msg = new message($id);
        $msg->delete();

        $this->assertFalse($DB->record_exists(message::TABLE, ['id' => $id]));
    }

    /**
     * Test message search with pagination.
     */
    public function test_message_search_pagination() {
        // Create multiple messages.
        for ($i = 0; $i < 10; $i++) {
            $this->generator->create_message(100.00 + $i, '010' . str_pad($i, 8, '0'), null, false);
        }

        // Test pagination.
        $page1 = message::search_messages('', 0, 5);
        $this->assertCount(5, $page1);

        $page2 = message::search_messages('', 5, 5);
        $this->assertCount(5, $page2);

        // Verify different results.
        $page1Ids = array_column($page1, 'id');
        $page2Ids = array_column($page2, 'id');
        $this->assertEmpty(array_intersect($page1Ids, $page2Ids));
    }

    /**
     * Test message filtering by done status.
     */
    public function test_message_filtering_by_done_status() {
        // Create mix of done and undone messages.
        $undoneIds = [];
        $doneIds = [];

        for ($i = 0; $i < 5; $i++) {
            $undoneIds[] = $this->generator->create_message(100.00, 'undone' . $i, null, false);
            $doneIds[] = $this->generator->create_message(100.00, 'done' . $i, null, true);
        }

        // Search should exclude done messages by default.
        $results = message::search_messages('', 0, 20);
        $resultIds = array_column($results, 'id');

        foreach ($undoneIds as $id) {
            $this->assertContains($id, $resultIds);
        }

        foreach ($doneIds as $id) {
            $this->assertNotContains($id, $resultIds);
        }

        // Search with include done should include all.
        $allResults = message::search_messages('', 0, 20, false);
        $this->assertCount(10, $allResults);
    }

    /**
     * Test message get_messages with date filtering.
     */
    public function test_message_get_messages_with_date_filter() {
        $baseTime = time();

        // Create messages at different times.
        $pastId = $this->generator->create_message(100.00, '01011111111', $baseTime - 2 * DAYSECS);
        $presentId = $this->generator->create_message(100.00, '01011111111', $baseTime);
        $futureId = $this->generator->create_message(100.00, '01011111111', $baseTime + 2 * DAYSECS);

        // Get messages around present time.
        $messages = message::get_messages(100.00, '01011111111', $baseTime);

        // Should find messages within reasonable time window.
        $messageIds = array_column($messages, 'id');
        $this->assertContains($presentId, $messageIds);
        $this->assertNotContains($pastId, $messageIds);
        $this->assertNotContains($futureId, $messageIds);
        // The exact behavior depends on the implementation's time window.
    }

    /**
     * Test message completed method with all parameters.
     */
    public function test_message_completed_full() {
        $id = $this->generator->create_message(200.00, '01012345678');
        $msg = new message($id);

        $receiverId = 123;
        $chargerId = 456;
        $subject = 'Payment completed via transfer';

        $msg->completed($receiverId, $chargerId, $subject);

        $this->assertEquals($receiverId, $msg->receiverid);
        $this->assertEquals($chargerId, $msg->chargerid);
        $this->assertEquals($subject, $msg->subject);
        $this->assertTrue($msg->is_done());
        $this->assertGreaterThan(0, $msg->timemodified);
    }

    /**
     * Test message magic methods for immutable fields.
     */
    public function test_message_immutable_fields() {
        $id = $this->generator->create_message(100.00, '01012345678', null, false);
        $msg = new message($id);

        // Immutable fields should throw exception on set.
        $immutableFields = ['sender', 'amount', 'message', 'timecreated'];

        foreach ($immutableFields as $field) {
            try {
                $msg->$field = 'new value';
                $this->fail("Setting immutable field '$field' should throw exception");
            } catch (coding_exception $e) {
                $this->assertStringContainsString('immutable', $e->getMessage());
            }
        }
    }

    /**
     * Test message reload functionality.
     */
    public function test_message_reload() {
        $id = $this->generator->create_message();
        $msg1 = new message($id);

        // Modify in database.
        global $DB;
        $DB->update_record(message::TABLE, (object)['id' => $id, 'receiverid' => 999]);

        // Original object should not reflect change.
        $this->assertNotEquals(999, $msg1->receiverid);

        // Reload should reflect change.
        $msg1->reload();
        $this->assertEquals(999, $msg1->receiverid);
    }

    /**
     * Test message search with special characters.
     */
    public function test_message_search_special_characters() {
        // Create messages with special characters in sender.
        $specialSenders = [
            'user.name@instapay',
            'user_name@instapay',
            'user-name@instapay',
            'user.name87@instapay',
            'user_name31@instapay',
            'user-name20@instapay',
        ];

        foreach ($specialSenders as $sender) {
            $this->generator->create_message(100.00, $sender);
        }

        // Search should work with special characters.
        foreach ($specialSenders as $sender) {
            $results = message::search_messages($sender, 0, 10, false);
            $this->assertCount(1, $results, $sender);
            $sender = utils::clean_instapay_account($sender);
            $this->assertEquals($sender, reset($results)->sender);
        }
    }
}

