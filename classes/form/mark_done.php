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

use moodleform;
use paygw_transfer\local\order\order;

defined('MOODLE_INTERNAL') || die();
require_once("{$CFG->libdir}/formslib.php");
/**
 * Class mark_done
 *
 * @package    paygw_transfer
 * @copyright  2026 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mark_done extends moodleform {
    /**
     * The order object to be marked as complete.
     * @var order
     */
    public order $order;
    /**
     * Form definition.
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;
        $id = $this->optional_param('id', 0, PARAM_INT);

        $mform->addElement('hidden', 'id');
        $mform->setConstant('id', $id);
        $mform->setType('id', PARAM_INT);

        $notice = "";
        $mform->addElement('html', $notice);

        $this->order = new order($id);
        $mform->addElement('html', $this->order);

        \paygw_transfer\local\utils\form::get_search_element($mform);

        $this->add_action_buttons();
    }
}
