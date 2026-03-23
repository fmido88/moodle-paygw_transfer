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
use paygw_transfer\local\webhook\webhook;
use paygw_transfer\reportbuilder\local\entities\sender;

/**
 * Class senders
 *
 * @package    paygw_transfer
 * @copyright  2026 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class senders extends \core_reportbuilder\system_report {
    #[\Override()]
    protected function can_view(): bool {
        return has_capability('paygw/transfer:viewsms', $this->get_context());
    }

    #[\Override()]
    protected function initialise(): void {
        $senderentity = new sender();
        $senderalias = $senderentity->get_table_alias(webhook::SAVED_TABLE);
        $this->set_main_table(webhook::SAVED_TABLE, $senderalias);
        $this->add_base_fields("{$senderalias}.id");

        $this->add_entity($senderentity);
        $senderentityname = $senderentity->get_entity_name();

        $entityuser1 = new \core_reportbuilder\local\entities\user();
        $entityuseralias1 = $entityuser1->get_table_alias('user');
        $username1 = $entityuser1->get_entity_name();

        $this->add_entity($entityuser1
            ->add_join("LEFT JOIN {user} {$entityuseralias1} ON {$entityuseralias1}.id = {$senderalias}.userid")
        );

        $this->add_column_from_entity("{$username1}:fullnamewithlink");
        $this->add_filter($entityuser1->get_filter('userselect'));

        $this->add_columns_from_entity($senderentityname);
        $this->add_filters_from_entity($senderentityname);

        $action = new action(
            new url('#'),
            new pix_icon('i/delete', get_string('deletesender', 'paygw_transfer')),
            ['data-id' => ':id', 'data-action' => 'delete-sender'],
            false,
            new lang_string('deletesender', 'paygw_transfer')
            );
        $this->add_action($action);

        $action = new action(
            new url('#'),
            new pix_icon('i/edit', get_string('editsender', 'paygw_transfer')),
            ['data-id' => ':id', 'data-action' => 'edit-sender'],
            false,
            new lang_string('editsender', 'paygw_transfer')
            );
        $this->add_action($action);
    }
}
