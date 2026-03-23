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

use core\exception\coding_exception;
use paygw_transfer\local\utils\utils;
use paygw_transfer\local\webhook\webhook;
use stdClass;

/**
 * Class message.
 *
 * @property-read int $id
 * @property string $subject
 * @property string $message
 * @property string $sender
 * @property float  $amount
 * @property bool   $done
 * @property int    $timecreated
 * @property int    $timemodified
 * @property int    $receiverid
 * @property int    $chargerid
 * @package    paygw_transfer
 * @copyright  2026 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class message {
    /**
     * Database table for messages.
     */
    public const TABLE = webhook::MESSAGES_TABLE;

    /**
     * The message record object.
     * @var stdClass
     */
    protected stdClass $record;

    /**
     * Constructor.
     * @param int $id
     */
    public function __construct(
        /** @var int The message id. */
        public readonly int $id
    ) {
        global $DB;
        $this->record = $DB->get_record(self::TABLE, ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Magic getter.
     * @param  string           $name
     * @throws coding_exception
     */
    public function __get($name) {
        if (property_exists($this->record, $name)) {
            return $this->record->$name;
        }

        throw new coding_exception("Attempt to get unknown property $name");
    }

    /**
     * Magic setter.
     * @param  string           $name
     * @param  mixed            $value
     * @throws coding_exception
     * @return void
     */
    public function __set($name, $value) {
        if (\in_array($name, ['id', 'timecreated', 'sender', 'amount', 'message'])) {
            throw new coding_exception("The property '\${$name}' is immutable.");
        }

        if (property_exists($this->record, $name)) {
            $this->record->$name = $value;
        } else {
            throw new coding_exception("Attempt to assign unknown property $name");
        }
    }

    /**
     * Get the rounded amount.
     * @return float
     */
    public function get_amount() {
        return round($this->record->amount, 2);
    }

    /**
     * If this message is used and done.
     * @return bool
     */
    public function is_done() {
        return (bool)$this->record->done;
    }

    /**
     * Set this message as done.
     * @param  bool $done
     * @return void
     */
    public function set_done(bool $done = true) {
        $this->record->done = $done;
    }

    /**
     * Mark this message as completed.
     * @param  int  $receiverid
     * @param  int  $chargerid
     * @return void
     */
    public function completed(int $receiverid = 0, int $chargerid = 0, string $subject = ''): void {
        global $USER;
        $this->receiverid = ($receiverid > 0) ? $receiverid : $USER->id;
        $this->chargerid  = ($chargerid > 0) ? $chargerid : $USER->id;
        if (!empty($subject)) {
            $this->subject = $subject;
        }
        $this->set_done();
        $this->update();
    }

    /**
     * Update the database record.
     * @return bool
     */
    public function update(): bool {
        global $DB;
        $this->timemodified = time();

        return $DB->update_record(self::TABLE, $this->record);
    }

    /**
     * Reload the record from database.
     * @return void
     */
    public function reload(): void {
        global $DB;
        $this->record = $DB->get_record(self::TABLE, ['id' => $this->id], '*', MUST_EXIST);
    }

    /**
     * Delete this message.
     * @return bool
     */
    public function delete(): bool {
        global $DB;
        require_capability('paygw/transfer:delete', \core\context\system::instance());
        $done = $DB->delete_records(self::TABLE, ['id' => $this->id]);

        if ($done) {
            unset($this->record);
        }

        return $done;
    }

    /**
     * Create and save new message to the database.
     * @param  webhook $message
     * @return message
     */
    public static function create(webhook $message): static {
        global $DB;
        $record              = new stdClass();
        $record->message     = $message->get_message();
        $record->sender      = $message->get_sender();
        $record->amount      = $message->get_amount();
        $record->timecreated = $record->timemodified = time();
        $record->subject     = '';
        $record->done        = 0;
        $id                  = $DB->insert_record(self::TABLE, $record);

        return new static($id);
    }

    /**
     * Get the message records that could be matching for this data.
     * @param  float  $amount
     * @param  string $sender
     * @param  int    $day
     * @param  bool   $undoneonly
     * @param  bool   $idonly
     * @return array
     */
    public static function get_message_records(
        ?float $amount = null,
        ?string $sender = null,
        ?int $day = null,
        bool $undoneonly = true,
        bool $idonly = false
    ) {
        global $DB;

        $where  = '1=1';
        $params = [];

        if (!empty($day)) {
            $where .= ' AND timecreated BETWEEN :timefrom AND :timeto';
            $params += [
                'timefrom' => $day - 1,
                'timeto'   => $day + DAYSECS + 1,
            ];
        }

        if (!empty($amount)) {
            $where .= ' AND amount = :amount';
            $params['amount'] = $amount;
        }

        if (!empty($sender)) {
            $where .= ' AND sender = :sender';
            $params['sender'] = $sender;
        }

        if ($undoneonly) {
            $where .= ' AND done != 1';
        }

        return $DB->get_records_select(self::TABLE, $where, $params, '', $idonly ? 'id' : '*');
    }

    /**
     * Get messages from submitted data.
     * @param  ?float    $amount
     * @param  ?string   $sender
     * @param  ?int      $day
     * @param  bool      $undoneonly
     * @return message[]
     */
    public static function get_messages(?float $amount = null, ?string $sender = null, ?int $day = null, bool $undoneonly = true) {
        $records = self::get_message_records($amount, $sender, $day, $undoneonly, true);

        return array_map(fn ($record) => new message($record->id), $records);
    }

    /**
     * Get message by searching its content.
     * @param  string $search
     * @param  int    $page
     * @param  int    $perpage
     * @param  bool   $doneonly
     *
     * @return message[]
     */
    public static function search_messages(string $search, int $page, int $perpage, $doneonly = true) {
        global $DB;
        $searchfield = $DB->sql_compare_text('message');
        $where       = $DB->sql_like($searchfield, ':search', false, false);

        $search = $DB->sql_like_escape($search);
        $search = $DB->sql_compare_text("%{$search}%");
        $params = ['search' => $search];

        if ($doneonly) {
            $where .= ' AND done != 1';
        }

        $count    = $DB->count_records_select(self::TABLE, $where, $params);
        $pageinfo = utils::pagination($count, $page, $perpage);

        $records = $DB->get_records_select(
            self::TABLE,
            $where,
            $params,
            '',
            'id',
            $pageinfo->limitfrom,
            $pageinfo->limitnum
        );
        $messages = array_map(fn($record) => new message($record->id), $records);

        return $messages;
    }
}
