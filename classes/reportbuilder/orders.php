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

namespace paygw_transfer\reportbuilder;

use core\lang_string;
use core\output\pix_icon;
use core\url;
use core_reportbuilder\local\report\action;
use core_reportbuilder\system_report;
use paygw_transfer\local\order\order;
use paygw_transfer\local\utils\utils;

/**
 * Class orders
 *
 * @package    paygw_transfer
 * @copyright  2026 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class orders extends system_report {
    #[\Override()]
    protected function can_view(): bool {
        return has_capability('paygw/transfer:viewreport', $this->get_context());
    }
    #[\Override()]
    protected function initialise(): void {
        $order = new \paygw_transfer\reportbuilder\local\entities\order();
        $ordertable = order::get_orders_table_name();
        $ordalias = $order->get_table_alias($ordertable);
        $this->set_main_table($ordertable, $ordalias);
        $this->add_base_fields("{$ordalias}.id, {$ordalias}.status");

        $this->add_entity($order);
        $ordername = $order->get_entity_name();

        $entityuser = new \core_reportbuilder\local\entities\user();
        $entityuseralias = $entityuser->get_table_alias('user');
        $username = $entityuser->get_entity_name();

        $this->add_entity($entityuser
            ->add_join("JOIN {user} {$entityuseralias} ON {$entityuseralias}.id = {$ordalias}.userid")
        );

        $this->add_column_from_entity("{$ordername}:id");
        $this->add_column_from_entity("{$username}:fullnamewithlink");
        $this->add_columns_from_entity($ordername, [], ['timecreated', 'timemodified', 'id']);
        $this->add_columns_from_entity($ordername, ['timecreated', 'timemodified']);

        $this->add_filters_from_entity($entityuser->get_entity_name(), ['userselect']);
        $this->add_filters_from_entity($order->get_entity_name());

        $component = 'paygw_' . order::gateway();
        $action = new action(
            new url('/payment/gateway/transfer/actions/done.php', ['id' => ':id']),
            new pix_icon('i/completion-manual-enabled', get_string('done', $component)),
            ['data-id' => ':id', 'data-action' => 'done'],
            false,
            new lang_string('markdone', $component)
            );
        $action->add_callback(function($row) {
            return !\in_array($row->status, order::get_successful_statuses());
        });
        $this->add_action($action);

        $action = new action(
            new url('/payment/gateway/transfer/actions/delete.php', ['id' => ':id']),
            new pix_icon('i/delete', get_string('done', $component)),
            ['data-id' => ':id', 'data-action' => 'delete'],
            false,
            new lang_string('deleteorder', $component)
            );
        $action->add_callback(function($row) {
            return \in_array($row->status, order::get_changeable_statuses());
        });
        $this->add_action($action);
    }
}
