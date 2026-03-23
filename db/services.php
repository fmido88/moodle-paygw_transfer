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
 * wallet enrol plugin external functions and service definitions.
 *
 * @package   paygw_transfer
 * @copyright 2023 Mo Farouk <phun.for.physics@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$functions = [
    'paygw_transfer_other_mark_done' => [
        'classname'   => paygw_transfer\external\report::class,
        'methodname'  => 'other_mark_done',
        'description' => 'Mark the message as done in the other website.',
        'type'        => 'write',
    ],
    'paygw_transfer_mark_done' => [
        'classname'   => paygw_transfer\external\report::class,
        'methodname'  => 'mark_done',
        'description' => 'Mark the message as done.',
        'type'        => 'write',
        'ajax'        => true,
    ],
    'paygw_transfer_delete_message' => [
        'classname'   => paygw_transfer\external\report::class,
        'methodname'  => 'delete_message',
        'description' => 'delete a message.',
        'type'        => 'write',
        'ajax'        => true,
    ],
    'paygw_transfer_search_messages' => [
        'classname'   => paygw_transfer\external\report::class,
        'methodname'  => 'search_messages',
        'description' => 'Search for messages and return them.',
        'type'        => 'read',
        'ajax'        => true,
    ],
    'paygw_transfer_delete_sender' => [
        'classname'   => paygw_transfer\external\report::class,
        'methodname'  => 'delete_saved_sender',
        'description' => 'Delete a saved sender data.',
        'type'        => 'read',
        'ajax'        => true,
    ],
    'paygw_transfer_edit_sender' => [
        'classname'   => paygw_transfer\external\report::class,
        'methodname'  => 'edit_saved_sender',
        'description' => 'Edit a saved sender data.',
        'type'        => 'read',
        'ajax'        => true,
    ],
    'paygw_transfer_process' => [
        'classname'   => paygw_transfer\external\process::class,
        'methodname'  => 'process_payment',
        'description' => 'Process the order and redirect to payment page.',
        'type'        => 'read',
        'ajax'        => true,
    ],
];

$services = [
    // The name of the service.
    // This does not need to include the component name.
    'paygw_transfer_other_sites' => [

        // A list of external functions available in this service.
        'functions' => [
            'paygw_transfer_other_mark_done',
        ],

        // If enabled, the Moodle administrator must link a user to this service from the Web UI.
        'restrictedusers' => 0,

        // Whether the service is enabled by default or not.
        'enabled' => 1,

        // This field os optional, but requried if the `restrictedusers` value is
        // set, so as to allow configuration via the Web UI.
        'shortname' => 'transfer',

        // Whether to allow file downloads.
        'downloadfiles' => 0,

        // Whether to allow file uploads.
        'uploadfiles'  => 0,
    ],
];
