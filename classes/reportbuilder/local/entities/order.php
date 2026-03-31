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

namespace paygw_transfer\reportbuilder\local\entities;

use core\lang_string;
use core_reportbuilder\local\filters\date;
use core_reportbuilder\local\filters\number;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\filters\text;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use paygw_transfer\local\order\order as gw_order;

/**
 * The order entity.
 *
 * @package    paygw_transfer
 * @copyright  2026 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class order extends \core_reportbuilder\local\entities\base {
    /**
     * Cached orders.
     * @var gw_order[]
     */
    protected static array $orders = [];

    #[\Override()]
    protected function get_default_entity_title(): lang_string {
        return new lang_string('orderentity', 'paygw_' . gw_order::gateway());
    }

    #[\Override()]
    protected function get_default_tables(): array {
        return [gw_order::get_orders_table_name()];
    }

    #[\Override()]
    public function initialise(): self {
        global $DB;

        $fields     = gw_order::get_all_fields();
        $tablename  = gw_order::get_orders_table_name();
        $tablealias = $this->get_table_alias($tablename);

        foreach ($fields as $field) {
            if (!$field->report) {
                continue;
            }

            $column = (new column(
                $field->name,
                $field->get_title(),
                $this->get_entity_name()
            ))->add_field("{$tablealias}.{$field->name}")
            ->add_joins($this->get_joins())
            ->set_type($field->get_column_type())
            ->set_is_sortable(true)
            ->add_callback(function ($value) use ($field) {
                return $field->format_value($value);
            });

            $this->add_column($column);

            $filterclass = match(true) {
                $column->get_type() == column::TYPE_TIMESTAMP                   => date::class,
                $field->type == 'int'                                           => number::class,
                \in_array($field->name, ['status', 'component', 'paymentarea']) => select::class,
                default                                                         => text::class,
            };
            $filter = (new filter(
                $filterclass,
                $field->name,
                $field->get_title(),
                $this->get_entity_name(),
                "{$tablealias}.{$field->name}"
            ))
                        ->add_joins($this->get_joins());

            if ($filterclass === select::class) {
                $options = [];

                switch ($field->name) {
                    case 'status':
                        $options = gw_order::get_statuses_list();
                        break;

                    case 'component':
                        $menu = $DB->get_records_menu($tablename, [], '', 'DISTINCT component, component');

                        foreach ($menu as $component => $ignore) {
                            $options[$component] = $field->format_value($component);
                        }
                        break;

                    case 'paymentarea':
                        $options = $DB->get_records_menu($tablename, [], '', 'DISTINCT paymentarea, paymentarea');
                        $options = array_keys($options);
                        $options = array_combine($options, $options);
                        break;

                    default:
                }

                if (empty($options)) {
                    continue;
                }
                $filter->set_options($options);
            }
            $this->add_filter($filter);
        }

        $column = (new column(
            'cost',
            new lang_string('cost'),
            $this->get_entity_name()
        ))->add_field("{$tablealias}.id", "idforcost")
        ->add_joins($this->get_joins())
        ->set_type(column::TYPE_FLOAT)
        ->set_is_sortable(false)
        ->set_callback(function ($id) {
            $order = self::get_order($id);

            if (!$order) {
                return '';
            }

            return $order->get_cost();
        });
        $this->add_column($column);

        $column = (new column(
            'currency',
            new lang_string('currency'),
            $this->get_entity_name()
        ))->add_field("{$tablealias}.id", "idforcurrency")
        ->add_joins($this->get_joins())
        ->set_type(column::TYPE_TEXT)
        ->set_is_sortable(false)
        ->set_callback(function ($id) {
            $order = self::get_order($id);

            if (!$order) {
                return '';
            }

            return new lang_string($order->get_currency(), 'currencies');
        });

        $this->add_column($column);

        return $this;
    }

    /**
     * Get the order object.
     * @param  int      $id
     * @return ?gw_order
     */
    protected static function get_order(int $id): ?gw_order {
        if (isset(static::$orders[$id])) {
            return static::$orders[$id];
        }

        try {
            static::$orders[$id] = new gw_order($id);
        } catch (\Throwable $e) {
            $msg = "Error constructing order with id '$id' the payable item may be deleted:\n<br>";
            debugging($msg . $e->getMessage(), DEBUG_DEVELOPER, $e->getTrace());
            static::$orders[$id] = null;
        }

        return static::$orders[$id];
    }
}
