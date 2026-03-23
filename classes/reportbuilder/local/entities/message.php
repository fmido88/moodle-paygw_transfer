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
use core_reportbuilder\local\filters\boolean_select;
use core_reportbuilder\local\filters\date;
use core_reportbuilder\local\filters\number;
use core_reportbuilder\local\filters\text;
use core_reportbuilder\local\filters\user;
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use core_user;
use paygw_transfer\local\messages\message as msg;

/**
 * Class message.
 *
 * @package    paygw_transfer
 * @copyright  2026 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class message extends \core_reportbuilder\local\entities\base {
    #[\Override()]
    protected function get_default_entity_title(): \lang_string {
        return new lang_string('message', 'paygw_transfer');
    }

    #[\Override()]
    protected function get_default_tables(): array {
        return [msg::TABLE];
    }

    #[\Override()]
    public function initialise(): self {
        $fields = ['id', 'subject', 'message', 'amount', 'sender',
        'charger', 'receiver', 'done', 'timecreated', 'timemodified'];

        foreach ($fields as $field) {
            $this->add_column($this->get_column_for_field($field));
            $this->add_filter($this->get_filter_for_field($field));
        }

        return $this;
    }

    /**
     * Generate filter from field name.
     * @param  string $field
     * @return filter
     */
    protected function get_filter_for_field(string $field): filter {
        $filterclass = match($field) {
            'amount', 'id' => number::class,
            'done'   => boolean_select::class,
            'charger', 'receiver' => user::class,
            'message', 'subject', 'sender' => text::class,
            'timecreated', 'timemodified' => date::class,
        };

        $filter = (new filter(
            $filterclass,
            $field,
            new lang_string($this->strid($field), 'paygw_transfer'),
            $this->get_entity_name(),
            $this->db_field_name($field)
        ))
            ->add_joins($this->get_joins());

        return $filter;
    }

    /**
     * Return the SQL of database field.
     * @param  string $field
     * @return string
     */
    protected function db_field_name(string $field): string {
        $alias   = $this->get_table_alias(msg::TABLE);
        $dbfield = \in_array($field, ['charger', 'receiver']) ? "{$field}id" : $field;

        return "{$alias}.{$dbfield}";
    }

    /**
     * Get the string identifier for the given field.
     * @param string $field
     * @return string
     */
    protected static function strid(string $field): string {
        return match($field) {
            'subject' => 'status',
            'id'      => 'messageid',
            default   => $field,
        };
    }

    /**
     * Generate a column from field name.
     * @param  string $field
     * @return column
     */
    protected function get_column_for_field(string $field): column {
        $column = new column(
            $field,
            new lang_string($this->strid($field), 'paygw_transfer'),
            $this->get_entity_name()
        );

        $type = match($field) {
            'id'     => column::TYPE_INTEGER,
            'amount' => column::TYPE_FLOAT,
            'done'   => column::TYPE_BOOLEAN,
            'timecreated', 'timemodified' => column::TYPE_TIMESTAMP,
            'message' => column::TYPE_LONGTEXT,
            default   => column::TYPE_TEXT
        };

        $callback = match($field) {
            'timecreated', 'timemodified' => [format::class, 'userdate'],
            'charger', 'receiver' => function ($userid) {
                if (empty($userid)) {
                    return '';
                }
                $user = core_user::get_user($userid);

                if (!$user) {
                    return new lang_string('deleted');
                }

                return fullname($user);
            },
            'done'  => [format::class, 'boolean_as_text'],
            default => null
        };
        $column->add_field($this->db_field_name($field))
            ->add_joins($this->get_joins())
            ->set_type($type)
            ->set_is_sortable(true);

        if ($callback) {
            $column->add_callback($callback);
        }

        return $column;
    }
}
