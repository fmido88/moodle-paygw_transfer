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

namespace paygw_transfer\form;

use paygw_transfer\local\utils\form;
use paygw_transfer\local\utils\utils;

defined('MOODLE_INTERNAL') || die();
require_once("{$CFG->libdir}/formslib.php");

use moodleform;

/**
 * Class sender
 *
 * @package    paygw_transfer
 * @copyright  2026 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sender extends moodleform {
    /**
     * The form definition.
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'save_sender', get_string('savesender', 'paygw_transfer'));
        form::add_user_auto_complete_selection($mform, 'userid');
        $mform->addRule('userid', get_string('required'), 'required');

        $mform->addElement('text', 'sender', get_string('sender', 'paygw_transfer'));
        $mform->addRule('sender', get_string('required'), 'required');
        $mform->setType('sender', PARAM_USERNAME);

        $mform->addElement('hidden', 'id', $this->optional_param('id', 0, PARAM_INT));
        $mform->setType('id', PARAM_INT);

        $this->add_action_buttons();
    }

    #[\Override]
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (empty($data['sender'])) {
            $errors['sender'] = get_string('sender_required', 'paygw_transfer');
        } else if (!utils::validate_wallet_number($data['sender']) && !utils::validate_instapay_account($data['sender'])) {
            $errors['sender'] = get_string('sender_invalid', 'paygw_transfer');
        }

        return $errors;
    }
}
