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
 * TODO describe module mark-done
 *
 * @module     paygw_transfer/delete-messages
 * @copyright  2026 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import $ from 'jquery';
import {dispatchEvent} from 'core/event_dispatcher';
import * as reportEvents from 'core_reportbuilder/local/events';
import * as reportSelectors from 'core_reportbuilder/local/selectors';
import Templates from 'core/templates';
import Ajax from 'core/ajax';
import Modal from 'core/modal_delete_cancel';
import {getString} from 'core/str';

let buttons;

const defineElements = () => {
    buttons = $('a[data-action="delete-message"]');
};

const onChange = async function(e) {
    e.preventDefault();
    let box = $(this);
    let id = box.data('id');

    let templatePromise = Templates.render('core/loading');

    let message = box.closest('tr').find('td.c3').text();
    let modal = await Modal.create({
        body: getString('confirmdeletemessage', 'paygw_transfer', message),
        title: getString('confirm'),
        removeOnClose: true,
    });
    modal.show();
    let modalRoot = $(modal.getRoot());
    modalRoot.find('button[data-action="delete"]').on('click', async function() {
        let requests = Ajax.call([{
            methodname: 'paygw_transfer_delete_message',
            args: {
                id: id,
            }
        }]);

        box.html(await templatePromise);

        await requests[0];

        dispatchEvent(reportEvents.tableReload, {}, $(reportSelectors.regions.report)[0]);
    });

    modalRoot.find('button[data-action="cancel"]').on('click', function() {
        modal.destroy();
    });

};

const registerEvent = () => {
    buttons.off('click', onChange);
    buttons.on('click', onChange);
};

export const init = () => {
    defineElements();
    registerEvent();
    $(reportSelectors.regions.report).on(reportEvents.tableReload, function() {
        setTimeout(function() {
            defineElements();
            registerEvent();
        }, 2000);
    });
};