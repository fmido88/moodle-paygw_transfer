<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software...

namespace paygw_transfer\external;

use advanced_testcase;
use paygw_transfer\external\report;
use paygw_transfer_generator;

class report_test extends advanced_testcase {
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

    public function test_search_messages_external() {
        $this->generator->create_message(100.0);
        $this->generator->create_message(200.0, '010456', null, true); // Done excluded

        $result = report::search_messages('010', 0, 10);
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals(100.0, reset($result)['amount']);
    }

    public function test_mark_done_external() {
        $id = $this->generator->create_message();
        $result = report::mark_done($id, true);
        $this->assertTrue($result);

        $msg = new \paygw_transfer\local\messages\message($id);
        $this->assertTrue($msg->is_done());
    }

    public function test_other_mark_done() {
        $time = time();
        $this->generator->create_message(150.0, '010789', $time);

        $result = report::other_mark_done('010789', 150.0, $time, $time + DAYSECS - 1, 'Test subject');
        $this->assertEquals('done', $result['status'], $result['msg'] ?? '');
    }
}

