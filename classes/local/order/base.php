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

use core\exception\coding_exception;
use core\exception\moodle_exception;
use core\lang_string;
use core\url;
use core\user;
use core_payment\helper;
use core_payment\local\entities\payable;
use core_text;
use paygw_transfer\local\utils\config;
use stdClass;
use Throwable;

/**
 * Class order.
 *
 * @package    paygw_transfer
 * @copyright  2024 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base {
    /**
     * The order id.
     * @var int
     */
    protected int $id;

    /**
     * @var int
     */
    protected int $itemid;

    /**
     * @var string
     */
    protected string $component;

    /**
     * @var string
     */
    protected string $paymentarea;

    /**
     * The cost before adding the surcharge.
     * @var float
     */
    protected float $rawcost;

    /**
     * The cost after adding the surcharge.
     * @var float
     */
    protected float $cost;

    /**
     * @var string
     */
    protected string $currency;

    /**
     * The order status.
     * The values [
     * new: for newly created orders without,
     * success: for paid and delivered orders
     * ]
     * @var string
     */
    protected string $status;

    /**
     * @var int
     */
    public readonly int $timecreated;

    /**
     * @var int
     */
    public int $timemodified;

    /**
     * The payment id in the payments table.
     * @var int|null
     */
    protected ?int $paymentid;

    /**
     * @var payable
     */
    protected payable $payable;

    /**
     * @var url
     */
    protected url $successurl;

    /**
     * @var int
     */
    protected int $userid;

    /**
     * The user object.
     * @var stdClass
     */
    protected stdClass $user;

    /**
     * Create a class to manage an order locally.
     * @param int $orderid
     */
    public function __construct(int $orderid) {
        global $DB;
        $this->id = $orderid;

        self::verify_table_structure();
        $this->load_record_data();

        try {
            $payable = helper::get_payable($this->component, $this->paymentarea, $this->itemid);
        } catch (\dml_exception $e) {
            $payable = null;
        }

        if (!empty($payable)) {
            $this->rawcost  = $payable->get_amount();
            $this->currency = $payable->get_currency();
        } else if (!empty($this->paymentid)) {
            $conditions = [
                'id'          => $this->paymentid,
                'component'   => $this->component,
                'paymentarea' => $this->paymentarea,
                'itemid'      => $this->itemid,
                'gateway'     => static::gateway(),
            ];
            $payment = $DB->get_record('payments', $conditions);

            if (!empty($payment)) {
                $this->rawcost  = $payment->amount;
                $this->currency = $payment->currency;
                $payable = new payable($payment->amount, $payment->currency, $payment->accountid);
            } else if (!empty($e)) {
                throw $e;
            }
        } else {
            if (!empty($e)) {
                throw $e;
            }

            throw new moodle_exception("Cannot find the order with id $orderid");
        }

        $this->payable = $payable;

        $surcharge  = helper::get_gateway_surcharge(static::gateway());
        $this->cost = helper::get_rounded_cost($this->rawcost, $this->currency, $surcharge);
    }

    /**
     * The payment gateway name.
     * @return string
     */
    abstract public static function gateway(): string;
    /**
     * Get the database table name that store the order data.
     * Override if the table has a different name other than paygw_{gatewayname}_orders.
     * @return string
     */
    public static function get_orders_table_name(): string {
        return 'paygw_' . static::gateway() . '_orders';
    }
    /**
     * Get extra fields needed.
     * the return value must be in the form:
     * [
     *  'key' => [
     *              'type' => string ('int', 'char', 'text')
     *              'null' => bool
     *           ]
     * ]
     * This is essential for updating fields in orders table.
     * @return field[]
     */
    abstract protected static function get_extra_fields(): array;
    /**
     * Return an array with the table main fields.
     * @return field[]
     * }
     */
    final protected static function get_table_main_fields(): array {
        return [
            new field('id', 'int', 'get_id', false, null, 'orderid'),
            new field('userid', 'int', 'get_userid', false, null, new lang_string('user')),
            new field('component', 'char', 'get_component', false, null, 'component', true, PARAM_COMPONENT),
            new field('paymentarea', 'char', 'get_paymentarea', false, null, 'paymentarea', true, PARAM_AREA),
            new field('itemid', 'int', 'get_itemid', titleidentifier: 'itemid'),
            new field('paymentid', 'int', 'get_paymentid', true, setter: 'set_paymentid', titleidentifier: 'paymentid'),
            new field('status', 'char', 'get_status', false, 'created', 'status', true, PARAM_ALPHAEXT, 'update_status'),
            new field('timecreated', 'int', '', false, null, 'timecreated'),
            new field('timemodified', 'int', '', false, null, 'timemodified'),
        ];
    }

    /**
     * Get all database orders table fields.
     * @return field[]
     */
    final public static function get_all_fields(): array {
        return array_merge(self::get_table_main_fields(), static::get_extra_fields());
    }
    /**
     * Verify the structure of the database table.
     * @throws coding_exception
     */
    final public static function verify_table_structure(): void {
        global $DB;
        $tablename = static::get_orders_table_name();
        $fields = $DB->get_columns($tablename);

        $all = static::get_all_fields();

        $all = array_map(fn(field $value): string => $value->name, $all);
        $errors = [];

        foreach ($fields as $name => $column) {
            if (!\in_array($name, $all)) {
                $errors[] = "The field $name not defined in get_extra_field or get_table_main_fields method";
                continue;
            }
        }

        if (!empty($errors)) {
            $msg = "The database table $tablename not match the structure of the class";
            throw new coding_exception($msg, implode("<br>\n", $errors));
        }
    }

    /**
     * Load the record data.
     * @param bool $changeableonly update variable fields only.
     * @return void
     */
    protected function load_record_data(bool $changeableonly = false): void {
        global $DB;
        $tablename = $this->get_orders_table_name();

        $fields = '*';
        if ($changeableonly) {
            $variables = ['status', 'paymentid', 'timemodified'];

            foreach (static::get_extra_fields() as $field) {
                if ($field->null) {
                    $variables[] = $field->name;
                }
            }

            $fields = implode(', ', $variables);
        }

        $record = $DB->get_record($tablename, ['id' => $this->id], $fields, MUST_EXIST);

        foreach ($record as $key => $value) {
            if (property_exists($this, $key) && $value !== null) {
                $this->$key = $value;
            }
        }
    }

    /**
     * Set the payment id local one.
     * @param int  $id
     * @param bool $updaterecord
     */
    public function set_paymentid(int $id, bool $updaterecord = true): void {
        $this->paymentid = $id;

        if ($updaterecord) {
            $this->update_record();
        }
    }

    /**
     * Getter for payment id.
     * @return ?int
     */
    public function get_paymentid(): ?int {
        return $this->paymentid ?? null;
    }
    /**
     * Update the order status.
     * @param string $status
     * @param bool   $updaterecord
     */
    public function update_status(string $status, bool $updaterecord = true) {
        $this->status = core_text::strtolower($status);

        if ($updaterecord) {
            $this->update_record();
        }
    }

    /**
     * Get the local (merchant) order id
     * which is the same as the table.
     * @return int
     */
    public function get_id(): int {
        return $this->id;
    }

    /**
     * Return the id of the user.
     * @return int
     */
    public function get_userid(): int {
        return $this->userid;
    }

    /**
     * Get the user object.
     * @param int $strictness
     * @return \stdClass
     */
    public function get_user(int $strictness = IGNORE_MISSING): stdClass {
        if (!empty($this->user)) {
            return $this->user;
        }

        $this->user = user::get_user($this->userid, '*', $strictness);

        return $this->user;
    }

    /**
     * Get the currency of this transaction.
     * @return string
     */
    public function get_currency(): string {
        return $this->currency;
    }

    /**
     * Get the cost after adding the surcharge.
     * @return float
     */
    public function get_cost(): float {
        return $this->cost;
    }

    /**
     * Get the raw cost without surcharge.
     * @return float
     */
    public function get_raw_cost(): float {
        return $this->rawcost;
    }

    /**
     * Get the component.
     * @return string
     */
    public function get_component(): string {
        return $this->component;
    }

    /**
     * Get the payment account id for this item.
     * @return int
     */
    public function get_account_id(): int {
        return $this->payable->get_account_id();
    }

    /**
     * Get paymentarea.
     * @return string
     */
    public function get_paymentarea(): string {
        return $this->paymentarea;
    }

    /**
     * Return the itemid.
     * @return int
     */
    public function get_itemid(): int {
        return $this->itemid;
    }

    /**
     * Return the order status.
     */
    public function get_status(): string {
        return $this->status;
    }

    /**
     * Get the payment configurations.
     * @return config
     */
    public function get_gateway_config(): config {
        return config::made_from_order($this);
    }

    /**
     * Get redirect url.
     * @return url
     */
    public function get_redirect_url(): url {
        global $DB, $CFG;

        if (!empty($this->successurl)) {
            return $this->successurl;
        }

        // Find redirection.
        $url = new url('/');

        // Method only exists in 3.11+.
        if (method_exists('\core_payment\helper', 'get_success_url')) {
            $url = helper::get_success_url($this->component, $this->paymentarea, $this->itemid);
        } else if (($this->component == 'enrol_fee' && $this->paymentarea == 'fee')
                || ($this->component == 'enrol_wallet' && $this->paymentarea == 'enrol')) {
            $enrol    = explode('_', $this->component, 2)[1];
            $courseid = $DB->get_field('enrol', 'courseid', ['enrol' => $enrol, 'id' => $this->itemid]);

            if (!empty($courseid)) {
                require_once($CFG->dirroot . '/course/lib.php');
                $url = course_get_url($courseid);
            }
        }

        $this->successurl = $url;

        return $this->successurl;
    }

    /**
     * Check if this order is successfully paid and delivered.
     * @return bool
     */
    public function is_success(): bool {
        $success = array_unique(\array_merge(['success'], $this->get_successful_statuses()));
        $success = array_map('\core_text::strtolower', $success);

        return !empty($this->paymentid) && in_array($this->status, $success);
    }
    /**
     * Save the payment and process the order
     * This will automatically update the record.
     * @param bool $checkrecord
     * @param bool $checksuccess Check if the order is already marked as success.
     */
    public function payment_complete(bool $checkrecord = false, bool $checksuccess = true): void {
        if ($checkrecord) {
            // Update any change in the status.
            $this->load_record_data(true);
        }

        if ($checksuccess && $this->is_success()) {
            return;
        }

        $needupdate = false;
        if (!$paymentid = $this->get_paymentid()) {
            $paymentid = helper::save_payment(
                $this->get_account_id(),
                $this->component,
                $this->paymentarea,
                $this->itemid,
                $this->userid,
                $this->rawcost,
                $this->currency,
                static::gateway()
            );

            $this->set_paymentid($paymentid, false);
            $needupdate = true;
        }

        if (!$this->is_success()) {
            $this->update_status('success', false);
            $needupdate = true;
        }

        if ($needupdate) {
            $this->update_record();
        }

        helper::deliver_order(
            $this->component,
            $this->paymentarea,
            $this->itemid,
            $paymentid,
            $this->userid
        );
    }

    /**
     * Notify the user about successful transaction.
     * @return void
     */
    public function notify_success(): void {
        // Override if we want to notify the user about the transaction.
    }
    /**
     * Update the data base record.
     */
    protected function update_record(): void {
        global $DB;

        $this->timemodified = time();

        $record = [
            'id'           => $this->id,
            'paymentid'    => $this->paymentid ?? null,
            'status'       => $this->status,
            'timemodified' => $this->timemodified,
        ];

        $extrafields = $this->get_extra_fields();

        foreach ($extrafields as $field) {
            $record[$field->name] = $field->get($this) ?? $field->get_default_value();
        }

        $tablename = $this->get_orders_table_name();
        $DB->update_record($tablename, (object)$record);
    }

    /**
     * The period to check for existence order before creating a new one.
     * @return int
     */
    public static function period_to_check_for_existed_order(): int {
        return DAYSECS;
    }

    /**
     * The statuses that could be changed.
     * @return string[]
     */
    public static function get_changeable_statuses(): array {
        return ['new', 'created'];
    }
    /**
     * Get a list of successful statues
     * @return string[]
     */
    public static function get_successful_statuses(): array {
        return ['success'];
    }
    /**
     * Return a list of all statuses in human readable form.
     * @return lang_string[]
     */
    public static function get_statuses_list(): array {
        return [
            'new'        => new lang_string('new', 'paygw_transfer'),
            'created'    => new lang_string('created', 'paygw_transfer'),
            'completed'  => new lang_string('completed', 'paygw_transfer'),
            'success'    => new lang_string('success', 'paygw_transfer'),
            'declined'   => new lang_string('declined', 'paygw_transfer'),
            'failed'     => new lang_string('failed', 'paygw_transfer'),
            'pending'    => new lang_string('pending', 'paygw_transfer'),
        ];
    }

    /**
     * Return readable data about this order.
     * @return string
     */
    public function __toString() {
        global $OUTPUT;
        $data = [];
        $fields = $this->get_all_fields();
        foreach ($fields as $field) {
            $label = $field->get_title();
            $value = $field->get($this);

            if ($value === null) {
                continue;
            }

            if ($field->name === 'userid') {
                $user = $this->get_user();
                $value = !$user ? get_string('deleted') : fullname($user);
            } else {
                $value = $field->format_value($value);
            }

            $data[] = compact('label', 'value');
        }
        $templatename = "paygw_" . $this->gateway() . "/order";
        return $OUTPUT->render_from_template($templatename, ['data' => $data]);
    }
    /**
     * Check last order with the same parameters.
     * @param string $component
     * @param string $paymentarea
     * @param int $itemid
     * @return stdClass[]
     */
    public static function check_for_recent_orders(string $component, string $paymentarea, string $itemid, int $limit = 1): array {
        global $USER, $DB;
        $tablename = static::get_orders_table_name();
        $timetocheck = static::period_to_check_for_existed_order();
        if ($timetocheck <= 0) {
            return [];
        }

        $changablestatuses = static::get_changeable_statuses();
        if (empty($changablestatuses)) {
            return [];
        }

        // Try to get an order with the same data in the last day.
        $data = [
            'itemid'       => $itemid,
            'component'    => $component,
            'paymentarea'  => $paymentarea,
            'userid'       => $USER->id,
            'timetocheck'  => time() - $timetocheck,
        ];
        $select = 'itemid = :itemid AND component = :component AND paymentarea = :paymentarea';
        $select .= ' AND userid = :userid AND timecreated >= :timetocheck';

        if (!empty($changablestatuses)) {
            [$sin, $sparams] = $DB->get_in_or_equal($changablestatuses, SQL_PARAMS_NAMED);
            $select .= " AND status $sin";
            $data += $sparams;
        }

        return $DB->get_records_select($tablename, $select, $data, 'timecreated DESC, id DESC', 'id', 0, $limit);
    }
    /**
     * Create a new order.
     * @param string $component
     * @param string $paymentarea
     * @param int $itemid
     * @return static
     */
    public static function create_order(string $component, string $paymentarea, int $itemid): static {
        global $USER, $DB;
        $tablename = static::get_orders_table_name();

        $records = static::check_for_recent_orders($component, $paymentarea, $itemid);
        if (!empty($records)) {
            return new static(reset($records)->id);
        }

        // Create a new one.
        $data = [
            'itemid'      => $itemid,
            'component'   => $component,
            'paymentarea' => $paymentarea,
            'userid'      => $USER->id,
            'status'      => 'created',
        ];

        $data['timecreated'] = $data['timemodified'] = time();

        $orderid = $DB->insert_record($tablename, (object)$data);

        return new static($orderid);
    }

    /**
     * Get all orders.
     * @param int $from
     * @param int $to
     * @return array[static]
     */
    public static function get_orders($from = 0, $to = 0) {
        global $DB;
        $select = '1=1';
        $params = [];

        if ($from > 0) {
            $select .= ' AND timecreated >= :fromtime';
            $params['fromtime'] = $from;
        }

        if ($to > 0) {
            $select .= ' AND timecreated <= :totime';
            $params['totime'] = $to;
        }

        $tablename = static::get_orders_table_name();
        $records   = $DB->get_records_select($tablename, $select, $params, '', 'id');
        $orders    = [];

        foreach ($records as $record) {
            $orders[$record->id] = new static($record->id);
        }

        return $orders;
    }

    /**
     * Get orders by user id.
     * @param int $userid
     * @return array[static]
     */
    public static function get_orders_by_user(int $userid, bool $changableonly = false, float $minamount = 0): array {
        global $DB;
        $tablename = static::get_orders_table_name();
        $select = "userid = :userid";
        $params = ['userid' => $userid];

        if ($changableonly) {
            $changable = static::get_changeable_statuses();
            [$in, $inparams] = $DB->get_in_or_equal($changable, SQL_PARAMS_NAMED);
            $select = "status $in";
            $params += $inparams;
        }

        $records = $DB->get_records_select($tablename, $select, $params, 'id DESC', 'id');
        $orders = [];
        foreach ($records as $record) {
            try {
                $order = new static($record->id);
            } catch (Throwable $e) {
                // Rare case that the payable item could be deleted.
                continue;
            }

            if ($order->get_cost() >= $minamount) {
                $orders[$order->get_id()] = $order;
            }
        }
        return $orders;
    }
}
