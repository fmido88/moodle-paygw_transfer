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
use core_reportbuilder\local\filters\boolean_select;
use core_reportbuilder\local\filters\date;
use core_reportbuilder\local\filters\number;
use core_reportbuilder\local\filters\text;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\helpers\user_filter_manager;
use core_reportbuilder\local\report\action;
use core_reportbuilder\system_report;
use paygw_transfer\form\filter;
use paygw_transfer\local\messages\message;
use paygw_transfer\local\order\order;
use paygw_transfer\local\utils\utils;
use paygw_transfer\reportbuilder\local\entities\message as message_entity;
use stdClass;
use user_filter_date;

/**
 * Class messages.
 *
 * @package    paygw_transfer
 * @copyright  2026 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class messages extends system_report {
    #[\Override()]
    protected function can_view(): bool {
        return has_capability('paygw/transfer:viewsms', $this->get_context());
    }

    #[\Override()]
    protected function initialise(): void {
        global $PAGE;

        $messageentity = new message_entity();
        $messagealias  = $messageentity->get_table_alias(message::TABLE);
        $this->set_main_table(message::TABLE, $messagealias);
        $this->add_base_fields("{$messagealias}.id, {$messagealias}.done");

        $this->add_entity($messageentity);
        $msgname = $messageentity->get_entity_name();

        $entityuser1      = new \core_reportbuilder\local\entities\user();
        $entityuseralias1 = $entityuser1->get_table_alias('user');
        $username1        = $entityuser1->get_entity_name();

        $this->add_entity(
            $entityuser1
            ->add_join("LEFT JOIN {user} {$entityuseralias1} ON {$entityuseralias1}.id = {$messagealias}.receiverid")
        );

        $entityuser2      = new \core_reportbuilder\local\entities\user();
        $entityuseralias2 = $entityuser2->get_table_alias('user');
        $username2        = $entityuser2->set_entity_name('entityuser2')->get_entity_name();

        $this->add_entity(
            $entityuser2
            ->add_join("LEFT JOIN {user} {$entityuseralias2} ON {$entityuseralias2}.id = {$messagealias}.chargerid")
        );

        $this->add_columns_from_entity($msgname, [], ['timecreated', 'timemodified', 'charger', 'receiver']);

        $this->add_column_from_entity("{$username1}:fullnamewithlink");
        $this->get_column("{$username1}:fullnamewithlink")
            ->set_title(new lang_string('charger', 'paygw_transfer'));

        $this->add_column_from_entity("{$username2}:fullnamewithlink");
        $this->get_column("{$username2}:fullnamewithlink")
            ->set_title(new lang_string('receiver', 'paygw_transfer'));

        $this->add_columns_from_entity($msgname, ['timecreated', 'timemodified']);

        $this->add_filters_from_entity($msgname);
        $this->add_filter($entityuser1->get_filter('userselect')->set_header(new lang_string('charger', 'paygw_transfer')));
        $this->add_filter($entityuser2->get_filter('userselect')->set_header(new lang_string('receiver', 'paygw_transfer')));

        $this->set_checkbox_toggleall(function (stdClass $row) {
            if (!empty($row->done)) {
                return null;
            }

            return [$row->id, get_string('select')];
        });

        $component = 'paygw_' . order::gateway();
        $action    = new action(
            new url('#'),
            new pix_icon('i/completion-manual-y', get_string('markmessagedone', $component)),
            ['data-id' => ':id', 'data-action' => 'mark-as-done', 'data-done' => ':done'],
            false,
            new lang_string('markmessagedone', $component)
        );
        $action->add_callback(function ($row) {
            return has_capability('paygw/transfer:markdone', $this->get_context()) && empty($row->done);
        });

        $this->add_action($action);
        $action = new action(
            new url('#'),
            new pix_icon('i/completion-manual-n', get_string('markmessageundone', $component)),
            ['data-id' => ':id', 'data-action' => 'mark-as-done', 'data-done' => ':done'],
            false,
            new lang_string('markmessageundone', $component)
        );
        $action->add_callback(function ($row) {
            return has_capability('paygw/transfer:markdone', $this->get_context()) && !empty($row->done);
        });
        $this->add_action($action);

        $action = new action(
            new url('#'),
            new pix_icon('i/delete', get_string('deletemessage', $component)),
            ['data-id' => ':id', 'data-action' => 'delete-message'],
            false,
            new lang_string('deletemessage', $component)
        );
        $action->add_callback(fn ($row) => is_siteadmin());
        $this->add_action($action);

        $PAGE->set_context(null);
        $filterform = new filter();
        $data = $filterform->get_data();
        if (empty($data) && !optional_param('submitvc', false, PARAM_BOOL)) {
            return;
        }

        $reportid = $this->get_report_persistent()->get('id');

        $from = utils::extract_date_from_data($data, 'from');
        $to   = utils::extract_date_from_data($data, 'to');

        $filtervalues = [
            "{$msgname}:timecreated_operator" => date::DATE_RANGE,
            "{$msgname}:timecreated_unit" => date::DATE_UNIT_DAY,
            "{$msgname}:timecreated_value" => '1',
            "{$msgname}:timecreated_from" => $from,
            "{$msgname}:timecreated_to" => $to,
        ];

        $sender = $data->sender ?? optional_param('sender', '', PARAM_TEXT);
        $amount = $data->amount ?? optional_param('amount', null, PARAM_FLOAT);

        if (!empty($amount) && is_numeric($amount)) {
            $filtervalues += [
                "{$msgname}:amount_operator" => number::RANGE,
                "{$msgname}:amount_value1" => $amount - 10,
                "{$msgname}:amount_value2" => $amount + 10,
            ];
        }
        if (!empty($sender)) {
            $filtervalues += [
                "{$msgname}:sender_operator" => text::CONTAINS,
                "{$msgname}:sender_value" => $sender,
            ];
        }
        $undone = $data->undone ?? optional_param('undone', false, PARAM_BOOL);

        if ($undone) {
            $filtervalues += [
                "{$msgname}:done_operator" => boolean_select::NOT_CHECKED,
            ];
        }
        user_filter_manager::set($reportid, $filtervalues);
    }
}
