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

namespace paygw_transfer\local\utils;

use core\url;
use MoodleQuickForm;
use paygw_transfer\local\messages\message;

/**
 * Class form.
 *
 * @package    paygw_transfer
 * @copyright  2026 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class form {
    /**
     * Add autocomplete to search for a certain message.
     * @param  MoodleQuickForm $mform
     * @param  string          $elementname
     * @param  mixed           $visiblename
     * @return void
     */
    public static function get_search_element(
        MoodleQuickForm $mform,
        string $elementname = 'messageid',
        ?string $visiblename = null
    ) {
        global $CFG;

        if (empty($visiblename)) {
            $visiblename = get_string('selectmessage', 'paygw_transfer');
        }
        $options = [
            'ajax'       => 'paygw_transfer/form-messages-select',
            'multiple'   => false,
            'perpage'    => $CFG->maxusersperpage,
        ];

        if (!empty($elementid)) {
            $options['id'] = $elementid;
        }

        $mform->addElement('autocomplete', $elementname, $visiblename, [], $options);
    }

    /**
     * Add a user selection autocomplete element to a form used by enrol_manual.
     * @param  \MoodleQuickForm $mform
     * @param  string           $elementname element name
     * @param  string           $visiblename form element visible name
     * @param  string|null      $elementid   html element id
     * @param  bool             $multi       multiple selection
     * @return void
     */
    public static function add_user_auto_complete_selection(
        \MoodleQuickForm &$mform,
        $elementname,
        $visiblename = '',
        $elementid = null,
        $multi = false
    ) {
        global $CFG;

        if (empty($visiblename)) {
            $visiblename = get_string('selectusers', 'enrol_manual');
        }

        $context = \context_system::instance();
        $options = [
            'ajax'       => 'enrol_manual/form-potential-user-selector',
            'multiple'   => $multi,
            'courseid'   => SITEID,
            'enrolid'    => 0,
            'perpage'    => $CFG->maxusersperpage,
        ];

        if (!empty($elementid)) {
            $options['id'] = $elementid;
        }

        $options['userfields'] = implode(',', \core_user\fields::get_identity_fields($context, true));

        $mform->addElement('autocomplete', $elementname, $visiblename, [], $options);
    }

    public static function process_charges_form(\enrol_wallet\form\charger_form $form, url $baseurl) {
        global $USER, $SITE;

        $confirm = optional_param('confirm', false, PARAM_BOOL);
        $submit = optional_param('submit', false, PARAM_BOOL);

        if ($submit && $confirm && confirm_sesskey()) {
            $data = [
                'op'       => required_param('op', PARAM_ALPHA),
                'value'    => optional_param('value', '', PARAM_FLOAT),
                'category' => optional_param('category', 0, PARAM_INT),
                'userlist' => required_param('userlist', PARAM_INT),
                'neg'      => optional_param('neg', false, PARAM_BOOL),
                'reason'   => optional_param('reason', '', PARAM_TEXT),
                'submit'   => $submit,
            ];
            $id = optional_param('messageid', 0, PARAM_INT);

            $errors = $form->validation($data, []);

            if (!empty($errors)) {
                $params = ['errors' => $errors];
                $baseurl->remove_all_params();
                $return = $baseurl->out() .'?'. http_build_query($params);
                return new url($return);
            } else {
                $form->process_form_submission($data);
                if (!empty($id)) {
                    $message = new message($id);
                    $message->completed($data['userlist']);
                }

                return $baseurl;
            }
        }

        if ($submitdata = $form->get_data()) {

            // Check if there action for marking some of the messages as done.
            $dones = explode(',', $submitdata->done);
            $subject = "Marked done by " . fullname($USER);
            if (!empty($dones) && !empty($submitdata->submit)) {
                foreach ($dones as $id) {
                    $message = new message($id);
                    $message->completed(0, 0, $subject);

                    $options = ['context' => \context_course::instance($SITE->id)];
                    $sitename = format_string($SITE->fullname, true, $options);
                    $params = [
                        'sender'   => $message->sender,
                        'amount'   => $message->get_amount(),
                        'timefrom' => $message->timecreated - 12 * HOURSECS,
                        'timeto'   => $message->timecreated + 12 * HOURSECS,
                        'subject'  => "$message->subject in $sitename",
                    ];
                    paygw_transfer_other_mark_done($params);
                }
            }

            if (\count($dones) <= 1) {

                if ($submitdata->op === 'balance') {
                    $form->process_form_submission($submitdata);
                    return $baseurl;
                }

                $data = (array)$submitdata;
                if (!empty($dones) && \count($dones) === 1) {
                    $id = reset($dones);
                    $data['messageid'] = $id;
                }

                $errors = [];
                if ($data['op'] === 'credit' && !empty($data['value'])  && !empty($data['userlist']) && empty($id)) {
                    $errors['value'] = get_string('error_donewithnovalue', 'paygw_transfer');
                }

                if (!empty($errors)) {
                    $params = ['errors' => $errors];
                    $baseurl->remove_all_params();
                    $return = $baseurl->out() .'?'. http_build_query($params);

                    foreach ($data as $key => $v) {
                        if ($key === 'submit' || $key === 'sesskey') {
                            unset($data[$key]);
                        }
                    }

                    return new url($return, $data);
                }

                return \enrol_wallet\output\pages::get_charger_confirm($data, $baseurl, $baseurl);
            }
        }
        return null;
    }
}
