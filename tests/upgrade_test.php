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

use paygw_transfer\local\webhook\testing;
use stdClass;
use xmldb_table;

/**
 * Tests for Transfer to Mobile Wallet or InstaPay
 *
 * @package    paygw_transfer
 * @category   test
 * @copyright  2026 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class upgrade_test extends \advanced_testcase {
    public function test_migrate(): void {
        global $DB, $CFG;
        $this->resetAfterTest();
        require_once("{$CFG->dirroot}/payment/gateway/transfer/db/upgradelib.php");

        $this->check_sms_table();
        $this->generate_smss();

        $smss = $DB->get_records('vc_sms');

        $this->assertEquals(300, $DB->count_records('vc_sms'));
        $this->assertEquals(0, $DB->count_records('paygw_transfer_messages'));

        paygw_transfer_migrate();

        $this->assertEquals(0, $DB->count_records('vc_sms'));
        $this->assertEquals(300, $DB->count_records('paygw_transfer_messages'));

        $messages = $DB->get_records('paygw_transfer_messages');
        $i = 0;
        foreach ($messages as $new) {
            $i++;
            $old = array_find($smss, function(stdClass $sms) use($new) {
                return $sms->amount == $new->amount
                    && $sms->sender == $new->sender
                    && $sms->receiverid == $new->receiverid
                    && $sms->done == $new->done
                    && $sms->time == $new->timecreated
                    && $sms->timemodified == $new->timemodified
                    && $sms->message == $new->message;
            });

            if ($old === null) {
                var_dump($new, array_values($smss)[($i - 1)]);
            }
            $this->assertNotEmpty($old, "Record $i ");
            unset($smss[$old->id]);
        }
        $this->assertEmpty($smss);
    }

    protected function generate_smss() {
        global $DB;
        $messages1 = testing::generate_random_instapay_messages(100);
        $messages2 = testing::generate_random_vodafone_cash_messages(200);

        $users = [];
        for ($i = 0; $i < 50; $i++) {
            $users[] = $this->getDataGenerator()->create_user();
        }

        $messages = \array_merge($messages1, $messages2);
        \shuffle($messages);

        $smss = [];

        $subjects = ['', 'Added from other site', 'manually marked as done', 'Marked done by user'];
        foreach ($messages as $message) {
            $smsmessage = new stdClass();
            $smsmessage->sender = $message->get_sender();
            $smsmessage->amount = $message->get_amount();
            $smsmessage->message = $message->get_message();
            $smsmessage->subject = $subjects[rand(0, 3)];
            $smsmessage->time = time() + rand(-30, 30) * DAYSECS;
            $smsmessage->timemodified = $smsmessage->time + rand(0, 5) * DAYSECS;
            $smsmessage->done = rand(0, 1);
            $smsmessage->receiverid = null;
            $smsmessage->chargerid = null;
            if ($smsmessage->done) {
                $user = $users[array_rand($users)];
                $smsmessage->receiverid = $user->id;
                $chargers = [$user->id, 2];
                $smsmessage->chargerid = $chargers[rand(0, 1)];
            }
            $smsmessage->id = $DB->insert_record('vc_sms', $smsmessage);
            $smss[] = $smsmessage;
        }

        return $smss;
    }
    protected static function check_sms_table() {
        global $DB, $CFG;
        $dbman = $DB->get_manager();
        if ($dbman->table_exists('vc_sms')) {
            return;
        }

        require_once("{$CFG->libdir}/db/upgradelib.php");

        // Define table vc_sms to be created.
        $table = new xmldb_table('vc_sms');

        // Adding fields to table vc_sms.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('subject', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('message', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('amount', XMLDB_TYPE_NUMBER, '5, 2', null, null, null, null);
        $table->add_field('sender', XMLDB_TYPE_CHAR, '25', null, null, null, null);
        $table->add_field('chargerid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('receiverid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('time', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('done', XMLDB_TYPE_INTEGER, '1', null, null, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        // Adding keys to table vc_sms.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for vc_sms.
        $dbman->create_table($table);
    }
}

if (!function_exists('array_find')) {
    /**
     * Porting of PHP 8.4 function
     *
     * @template TValue of mixed
     * @template TKey of array-key
     *
     * @param array<TKey, TValue> $array
     * @param callable(TValue $value, TKey $key): bool $callback
     * @return ?TValue
     *
     * @see https://www.php.net/manual/en/function.array-find.php
     */
    function array_find(array $array, callable $callback): mixed {
        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return null;
    }
}
