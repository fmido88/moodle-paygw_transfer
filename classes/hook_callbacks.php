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

namespace paygw_transfer;

use core\url;
use enrol_wallet\hook\before_charger_form_definition;
use paygw_transfer\form\credit;

/**
 * Class hook_callbacks.
 *
 * @package    paygw_transfer
 * @copyright  2026 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Add filter form to the wallet charging form to let the administrator check the
     * existence of transfer messages before topping up the user's wallet.
     * @param  before_charger_form_definition $hook
     * @return void
     */
    public static function extend_charger_form(before_charger_form_definition $hook) {
        global $DB, $PAGE;
        $mform = $hook->get_form();
        $data  = $hook->get_custom_data();

        if (empty($data['paygw_transfer'])) {
            return;
        }

        $data = (object)$data;

        $mform->addElement('text', 'done');
        $mform->setType('done', PARAM_TEXT);
        $mform->updateElementAttr('done', ['style' => 'display: none;']);

        $mform->addRule('done', null, 'required', null, 'client');
    }

    /**
     * Add the transfer gateway as a method for topping up the wallet.
     * @param  \enrol_wallet\hook\extend_topup_options $hook
     * @return void
     */
    public static function extend_topup_options(\enrol_wallet\hook\extend_topup_options $hook) {
        $enabled = (bool)get_config('paygw_transfer', 'enablecredit');

        if (!$enabled) {
            return;
        }

        $action = new url('/payment/gateway/transfer/credit.php');
        $form   = new credit($action, ['type' => credit::WALLET]);

        $hook->add_option($form->render(), get_string('topupbytransfer', 'paygw_transfer'), 'vc');
        $hook->order_option('vc', 2);
    }
}
