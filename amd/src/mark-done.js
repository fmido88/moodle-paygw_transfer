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
 * @module     paygw_transfer/mark-done
 * @copyright  2026 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import $ from 'jquery';
import {dispatchEvent} from 'core/event_dispatcher';
import * as reportEvents from 'core_reportbuilder/local/events';
import tableEvents from 'core_table/local/dynamic/events';
import * as reportSelectors from 'core_reportbuilder/local/selectors';
import * as tableSelectors from 'core_table/local/dynamic/selectors';
import Templates from 'core/templates';
import Ajax from 'core/ajax';

let buttons;

const defineElements = () => {
    buttons = $('a[data-action="mark-as-done"]');
};

const onChange = async function(e) {
    e.preventDefault();
    let box = $(this);
    let done = !box.data('done');
    let id = box.data('id');

    let templatePromise = Templates.render('core/loading');

    let requests = Ajax.call([{
        methodname: 'paygw_transfer_mark_done',
        args: {
            id: id,
            done: done,
        }
    }]);

    box.html(await templatePromise);

    await requests[0];

    dispatchEvent(reportEvents.tableReload, {preservePagination: true}, $(reportSelectors.regions.report)[0]);
};

const registerEvent = () => {
    buttons.off('click', onChange);
    buttons.on('click', onChange);
};
const refresh = function() {
    defineElements();
    registerEvent();
};

/**
 * Start document mutation observation.
 */
function startObserver() {
    const mutationObserver = new MutationObserver(refresh);
    const config = {attributes: false, childList: true, subtree: true};
    mutationObserver.observe($(reportSelectors.regions.report)[0], config);
}


export const init = () => {
    defineElements();
    registerEvent();
    $(reportSelectors.regions.report).on(reportEvents.tableReload, refresh);
    $(tableSelectors.main.region).on(tableEvents.tableContentRefreshed, refresh);
    startObserver();
};