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
use core_reportbuilder\local\filters\text;
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use paygw_transfer\local\webhook\webhook;

/**
 * Class sender
 *
 * @package    paygw_transfer
 * @copyright  2026 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sender extends \core_reportbuilder\local\entities\base {
    #[\Override()]
    protected function get_default_entity_title(): \lang_string {
        return new lang_string('sender', 'paygw_transfer');
    }
    #[\Override()]
    protected function get_default_tables(): array {
        return [webhook::SAVED_TABLE];
    }
    #[\Override()]
    public function initialise(): self {
        $fields = ['sender', 'timecreated', 'timemodified'];
        $alias = $this->get_table_alias(webhook::SAVED_TABLE);
        foreach ($fields as $field) {
            $string = new lang_string($field, 'paygw_transfer');
            $column = (new column(
                $field,
                $string,
                $this->get_entity_name()
            ))->add_joins($this->get_joins())
            ->set_is_sortable(true)
            ->set_type(($field === 'sender') ? column::TYPE_TEXT : column::TYPE_TIMESTAMP)
            ->add_field("{$alias}.{$field}");
            if (\in_array($field, ['timecreated', 'timemodified'])) {
                $column->set_callback([format::class, 'userdate']);
                $filterclass = date::class;
            } else {
                $filterclass = text::class;
            }
            $this->add_column($column);

            $filter = (new filter(
                $filterclass,
                $field,
                $string,
                $this->get_entity_name(),
                "{$alias}.{$field}"
            ))->add_joins($this->get_joins());
            $this->add_filter($filter);
        }
        return $this;
    }
}
