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
 * TODO describe module sensers-report
 *
 * @module     paygw_transfer/sensers-report
 * @copyright  2026 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
import Ajax from 'core/ajax';
import Templates from 'core/templates';
import ModalDeleteCancel from 'core/modal_delete_cancel';
import ModalSaveCancel from 'core/modal_save_cancel';
import * as ReportSelectors from 'core_reportbuilder/local/selectors';
import ReportEvents from 'core_reportbuilder/local/events';
import {getString} from 'core/str';
import {dispatchEvent} from 'core/event_dispatcher';
import {exception} from 'core/notification';

let deleteButtons;
let editButtons;
/**
 * Register reloaded elements and their observers.
 */
function registerElements() {
    deleteButtons = $('a[data-action="delete-sender"]');
    editButtons = $('a[data-action="edit-sender"]');

    deleteButtons.off('click', deleteSender);
    editButtons.off('click', editSender);
    deleteButtons.on('click', deleteSender);
    editButtons.on('click', editSender);
}

/**
 * Delete sender link clicked.
 * @param {JQuery.Event} e
 */
async function deleteSender(e) {
    e.stopPropagation();
    e.preventDefault();

    let button = $(this);
    let modal = await ModalDeleteCancel.create({
        body: getString('deletesenderconfirm', 'paygw_transfer'),
        show: true,
        removeOnClose: true,
    });

    modal.getRoot().find('button[data-action="delete"]').on('click', function() {
        let request = Ajax.call([{
            methodname: 'paygw_transfer_delete_sender',
            args: {
                id: button.data('id')
            }
        }]);
        request[0].always(function() {
            modal.destroy();
            reload();
        });
    });

    modal.getRoot().find('button[data-action="cancel"]').on('click', function() {
        modal.destroy();
    });
}

/**
 * Edit sender link clicked.
 * @param {JQuery.Event} e
 */
async function editSender(e) {
    e.stopPropagation();
    e.preventDefault();

    let button = $(this);
    let modal = await ModalSaveCancel.create({
        body: Templates.render('paygw_transfer/edit-sender'),
        show: true,
        removeOnClose: true,
    });

    let root = modal.getRoot();

    root.find('button[data-action="save"]').on('click', async function(e) {
        e.preventDefault();
        e.stopPropagation();

        let input = root.find('input[name="sender"]');
        removeError(root);

        /**
         * @type {String}
         */
        let sender = input.val();
        if (sender.match(/[^0-9a-z.\-_]/)) {
            addError(root);
            return;
        }

        // eslint-disable-next-line no-console
        console.log(input, sender);
        let request = Ajax.call([{
            methodname: 'paygw_transfer_edit_sender',
            args: {
                id: button.data('id'),
                sender: sender
            }
        }]);
        request[0].then(async function(result) {
            // eslint-disable-next-line no-console
            console.log(result);
            if (!result) {
                addError();
            } else {
                modal.destroy();
                reload();
            }
            return result;
        }).catch(e => {
            exception(e);
        });
    });

    root.find('button[data-action="cancel"]').on('click', function() {
        modal.destroy();
    });
}
/**
 * Add error string for invalid sender.
 * @param {JQuery<HTMLElement>} root The modal root
 */
function addError(root) {
    let input = root.find('input[name="sender"]');

    input.addClass('is-invalid');
    input.closest('.fitem').addClass('has-danger');

    let errorHolder = root.find('.invalid-feedback#id_error_sender');
    errorHolder.css("display", "block");

    getString('invalidsender', 'paygw_transfer')
        .then((string) => errorHolder.html(string))
        .catch(e => exception(e));
}
/**
 * Add error string for invalid sender.
 * @param {JQuery<HTMLElement>} root The modal root
 */
function removeError(root) {
    let input = root.find('input[name="sender"]');

    input.removeClass('is-invalid');
    input.closest('.fitem').removeClass('has-danger');

    let errorHolder = root.find('.invalid-feedback#id_error_sender');
    errorHolder.css("display", "none");
    errorHolder.html('');
}
/**
 * Reload the table.
 */
function reload() {
    dispatchEvent(ReportEvents.tableReload, {}, $(ReportSelectors.regions.report)[0]);
    setTimeout(registerElements, 3000);
}

export const init = function() {
    registerElements();
};
