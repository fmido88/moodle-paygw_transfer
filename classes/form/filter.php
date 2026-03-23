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

/**
 * The form to filter messages.
 *
 * @package    paygw_transfer
 * @copyright  2023 Mo Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_transfer\form;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

/**
 * The form to filter data.
 * @package paygw_transfer
 */
class filter extends \moodleform {
    /**
     * Form definition.
     * @return void
     */
    public function definition() {
        global $DB;
        $mform = $this->_form;
        static::elements($mform);
        $this->set_display_vertical();
    }

    /**
     * Add form elements.
     * @param  \MoodleQuickForm $mform
     * @return void
     */
    public static function elements(\MoodleQuickForm $mform) {
        $mform->addElement('text', 'sender', get_string('sendernumber', 'paygw_transfer'));
        $mform->setType('sender', PARAM_TEXT);

        $mform->addElement('text', 'amount', get_string('round_amount', 'paygw_transfer'));
        $mform->setType('amount', PARAM_NUMBER);

        $year    = (int)userdate(time(), '%Y');
        $options = [
            'startyear' => $year - 3,
            'stopyear'  => $year,
            'timezone'  => 99,
            'optional'  => false,
        ];
        $mform->addElement('date_selector', 'datefrom', get_string('from', 'paygw_transfer'), $options);
        $mform->addElement('date_selector', 'dateto', get_string('to', 'paygw_transfer'), $options);

        $mform->addElement('checkbox', 'undone', get_string('undoneonly', 'paygw_transfer'));

        $mform->addElement('submit', 'submitvc', get_string('submit_filter', 'paygw_transfer'));
    }
}
