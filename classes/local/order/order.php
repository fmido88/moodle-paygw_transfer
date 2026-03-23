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

use paygw_transfer\local\messages\message;

/**
 * Class order
 *
 * @package    paygw_transfer
 * @copyright  2024 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class order extends base {
    /**
     * The message id used by this order.
     * @var int
     */
    protected int $messageid;

    #[\Override]
    public static function gateway(): string {
        return 'transfer';
    }

    /**
     * Setter for the message id.
     * @param int $messageid
     * @param bool $updaterecord
     * @return void
     */
    public function set_messageid(int $messageid, bool $updaterecord = true): void {
        $this->messageid = $messageid;
        if ($updaterecord) {
            $this->update_record();
        }
    }

    /**
     * Get the message id associated with this order.
     * @return int|null
     */
    public function get_messageid(): ?int {
        return $this->messageid ?? null;
    }

    #[\Override()]
    protected static function get_extra_fields(): array {
        return [
            new field('messageid', 'int', 'get_messageid', true, titleidentifier: 'messageid', setter: 'set_messageid'),
        ];
    }

    #[\Override()]
    public static function get_successful_statuses(): array {
        $statuses = parent::get_successful_statuses();
        $statuses[] = 'done';
        return array_unique($statuses);
    }
    #[\Override()]
    public function notify_success(): void {
        // Notify user.
        \core\notification::success(get_string('payment_successful', 'paygw_transfer'));
    }
    #[\Override()]
    public function payment_complete(bool $checkrecord = false, bool $checksuccess = true): void {
        parent::payment_complete($checkrecord, $checksuccess);
        if (!$msgid = $this->get_messageid()) {
            return;
        }
        $message = new message($msgid);
        $message->completed($this->get_userid(), 0, "Completed by payment with id: " . $this->get_paymentid());
    }
}

