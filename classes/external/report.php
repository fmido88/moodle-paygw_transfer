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

/**
 * wallet enrol plugin external functions
 *
 * @package    paygw_transfer
 * @copyright  2023 Mo Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_transfer\external;

use context_system;
use core_external\external_multiple_structure;
use paygw_transfer\local\messages\message;
use paygw_transfer\local\utils\utils;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/payment/gateway/transfer/lib.php");

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use core_external\external_description;
use moodle_exception;
use stdClass;
/**
 * wallet enrolment external functions.
 *
 * @package   paygw_transfer
 * @copyright 2023 Mo Farouk <phun.for.physics@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report extends external_api {

    /**
     * Returns description of other_mark_done() parameters.
     *
     * @return external_function_parameters
     */
    public static function other_mark_done_parameters() {
        return new external_function_parameters(
                [
                    'sender'   => new external_value(PARAM_TEXT, 'The sender phone number or instapay address without @instapay.'),
                    'amount'   => new external_value(PARAM_FLOAT, 'The exact amount sent.'),
                    'timefrom' => new external_value(PARAM_INT, 'search from this time.'),
                    'timeto'   => new external_value(PARAM_INT, 'search to this time.'),
                    'subject'  => new external_value(PARAM_TEXT, 'Who and where did this message used.'),
                ]
            );
    }

    /**
     * Mark that a  certain message marked as done, used from other sites to mark that
     * messages with this data already used their.
     *
     * @param string $sender
     * @param float $amount
     * @param int $from
     * @param int $to
     * @param string $subject
     * @return array instance information.
     * @throws moodle_exception
     */
    public static function other_mark_done($sender, $amount, $from, $to, $subject) {
        global $DB;

        $params = [
            'sender'     => $sender,
            'amount'     => $amount,
            'timefrom'   => $from,
            'timeto'     => $to,
            'subject'    => $subject,
        ];

        try {
            $params = self::validate_parameters(self::other_mark_done_parameters(), $params);
            $where = 'amount = :amount AND sender = :sender AND timecreated BETWEEN :timefrom AND :timeto';
            $records = $DB->get_records_select('paygw_transfer_messages', $where, $params);
        } catch (moodle_exception $e) {
            $error = $e->getMessage();
            $trace = $e->getTraceAsString();
            $msg = "$error \n Trace: $trace";
            return [
                'status' => 'error',
                'msg'    => $msg,
            ];
        }

        if (empty($records)) {
            $msg = "Record Not found, Sender: $sender, Amount: $amount, timefrom: $from, timeto: $to";
            return [
                'status' => 'error',
                'msg'    => $msg,
            ];
        }

        foreach ($records as $record) {
            $data = new stdClass;
            $data->id = $record->id;
            $data->done = 1;
            $data->subject = $params['subject'];
            $data->timeupdated = time();

            try {
                $DB->update_record('paygw_transfer_messages', $data); // Mark it as done.
            } catch (moodle_exception $e) {
                $error = $e->getMessage();
                $error .= "\n" . $e->getTraceAsString();
                return [
                    'status' => 'error',
                    'msg'    => $error,
                ];
            }
        }

        return [
            'status' => 'done',
        ];
    }

    /**
     * Returns description of other_mark_done() result value.
     *
     * @return external_description
     */
    public static function other_mark_done_returns() {
        return new external_single_structure(
            [
                'status' => new external_value(PARAM_TEXT, 'done or error'),
                'msg'    => new external_value(PARAM_TEXT, 'Error', VALUE_OPTIONAL),
            ]
        );
    }

    /**
     * Parameters descriptions for mark_done()
     * @return external_function_parameters
     */
    public static function mark_done_parameters() {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'The message id'),
            'done' => new external_value(PARAM_BOOL, 'Mark as done or no', VALUE_DEFAULT, true),
        ]);
    }

    /**
     * Mark a message as done. This is a forcing hack to not allow a message to be used.
     * @param int $id
     * @return bool
     */
    public static function mark_done($id, $done) {
        [
            'id'   => $id,
            'done' => $done,
        ] = self::validate_parameters(self::mark_done_parameters(), compact('id', 'done'));
        $context = context_system::instance();

        self::validate_context($context);
        require_capability('paygw/transfer:markdone', $context);

        $message = new message($id);
        $message->set_done($done);
        $message->update();
        return true;
    }

    /**
     * Description for returning value of mark_done()
     * @return external_value
     */
    public static function mark_done_returns() {
        return new external_value(PARAM_BOOL, 'Success or not');
    }

    /**
     * Parameters descriptions for delete_message()
     * @return external_function_parameters
     */
    public static function delete_message_parameters() {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'The message id'),
        ]);
    }

    /**
     * Delete a message.
     * @param int $id
     * @return bool
     */
    public static function delete_message($id) {
        [
            'id'   => $id,
        ] = self::validate_parameters(self::mark_done_parameters(), compact('id'));
        $context = context_system::instance();

        self::validate_context($context);
        require_capability('paygw/transfer:markdone', $context);

        $message = new message($id);
        return $message->delete();
    }

    /**
     * Description for returning value of delete_message()
     * @return external_value
     */
    public static function delete_message_returns() {
        return new external_value(PARAM_BOOL, 'Success or not');
    }

    /**
     * Description for search_messages() parameters.
     * @return external_function_parameters
     */
    public static function search_messages_parameters() {
        return new external_function_parameters([
            'search' => new external_value(PARAM_TEXT),
            'page'   => new external_value(PARAM_INT),
            'perpage' => new external_value(PARAM_INT),
        ]);
    }

    /**
     * Search messages.
     * @param string $search
     * @param int $page
     * @param int $perpage
     * @return array{amount: float, id: int, message: string, sender: string, timecreated: int[]}
     */
    public static function search_messages($search, $page, $perpage) {
        global $DB;
        [
            'search'  => $search,
            'page'    => $page,
            'perpage' => $perpage,
        ] = self::validate_parameters(self::search_messages_parameters(), compact('search', 'page', 'perpage'));
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('paygw/transfer:viewsms', $context);

        $messages = message::search_messages($search, $page, $perpage);
        return array_map(fn(message $message) => [
            'id'      => $message->id,
            'message' => $message->message,
            'amount'  => $message->get_amount(),
            'sender'  => $message->sender,
            'timecreated' => $message->timecreated,
        ], $messages);
    }

    /**
     * Description for search_messages() return values.
     * @return external_multiple_structure
     */
    public static function search_messages_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'id'          => new external_value(PARAM_INT),
                'message'     => new external_value(PARAM_TEXT),
                'amount'      => new external_value(PARAM_FLOAT),
                'sender'      => new external_value(PARAM_TEXT),
                'timecreated' => new external_value(PARAM_INT),
            ])
        );
    }

    /**
     * Description for delete_saved_sender() parameters.
     * @return external_function_parameters
     */
    public static function delete_saved_sender_parameters() {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT),
        ]);
    }

    /**
     * Delete a saved sender record.
     * @param int $id
     * @return bool
     */
    public static function delete_saved_sender($id) {
        global $DB;

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('paygw/transfer:markdone', $context);

        $params = self::validate_parameters(self::delete_saved_sender_parameters(), compact('id'));
        return $DB->delete_records('paygw_transfer_saved', $params);
    }

    /**
     * Description for returning value of delete_saved_sender()
     * @return external_value
     */
    public static function delete_saved_sender_returns() {
        return new external_value(PARAM_BOOL);
    }

    /**
     * Description for edit_saved_sender() parameters.
     * @return external_function_parameters
     */
    public static function edit_saved_sender_parameters() {
        return new external_function_parameters([
            'id'     => new external_value(PARAM_INT),
            'sender' => new external_value(PARAM_USERNAME),
        ]);
    }

    /**
     * Edit a saved sender record.
     * @param int $id the record id.
     * @param string $sender
     * @return bool
     */
    public static function edit_saved_sender($id, $sender) {
        global $DB;

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('paygw/transfer:markdone', $context);

        $params = self::validate_parameters(self::edit_saved_sender_parameters(), compact('id', 'sender'));
        $params['timemodified'] = time();

        $wallet = utils::validate_wallet_number($params['sender']);
        $instapay = utils::validate_instapay_account($params['sender']);

        if (!$wallet && !$instapay) {
            return false;
        }

        $params['sender'] = (!$wallet && $instapay)
                          ? utils::clean_instapay_account($sender)
                          : utils::clean_wallet_number($sender);

        return $DB->update_record('paygw_transfer_saved', $params);
    }

    /**
     * Description for returning value of edit_saved_sender()
     * @return external_value
     */
    public static function edit_saved_sender_returns() {
        return new external_value(PARAM_BOOL);
    }
}
