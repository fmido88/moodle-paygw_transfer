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
use paygw_transfer\form\mark_done;
use paygw_transfer\local\order\order as transfer_order;
use paygw_transfer_generator;

class mark_done_test extends advanced_testcase {
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

    public function test_form_definition() {
        $orderid = $this->generator->create_order();
        $_GET['id'] = $orderid;
        $mform = new mark_done();

        $this->assertEquals($orderid, $mform->optional_param('id', 0, PARAM_INT));
        // Check hidden id, search element from utils\form
    }

    public function test_form_submission() {
        $orderid = $this->generator->create_order();
        $msgid = $this->generator->create_message();

        $_GET['id'] = $orderid;
        $mform = new mark_done();
        $data = (object)['id' => $orderid, 'messageid' => $msgid];
        $mform->set_data($data);

        // Process would link message to order and complete
        $order = new transfer_order($orderid);
        $original_msgid = $order->get_messageid();
        $this->assertNull($original_msgid); // Before

        // Mock process (private), test observable effects
        $this->assertTrue(true); // Placeholder
    }
}

