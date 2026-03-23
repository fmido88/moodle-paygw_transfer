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
 * TODO describe module messages-table
 *
 * @module     paygw_transfer/messages-table
 * @copyright  2026 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import $ from 'jquery';
import reportEvents from 'core_reportbuilder/local/events';
import SELECTORS from 'core_reportbuilder/local/selectors';

let checkboxes;
let input;
let submit;

const defineElements = () => {
    checkboxes = $('input[type="checkbox"][name="report-select-row[]"]');
    input = $('input[name="done"]');
    submit = $('input[name="submit"][type="submit"]');
};
const onChange = function() {
    /**
     * @type {Array<Number>|Set}
     */
    let dones = input.val().split(',');
    let box = $(this);
    if (box.prop('checked')) {
        dones.push(box.attr('value'));
    } else {
        dones = dones.filter(function(value) {
            return (value != box.attr('value'));
        });
    }
    dones = new Set(dones.filter(value => value != ''));
    input.val(dones.join(','));
    // eslint-disable-next-line no-console
    console.log(dones, input.val(), box.val());
    input.trigger('click');
    input.trigger('blur');
    input.trigger('change');
    submit.attr('disabled', dones.size === 0);
};
const registerEvent = () => {
    input.val('');
    submit.attr('disabled', true);
    checkboxes.off('input change', onChange);
    checkboxes.on('input change', onChange);
};
export const init = () => {
    defineElements();
    registerEvent();
    $(SELECTORS.regions.report).on(reportEvents.tableReload, function() {
            setTimeout(function() {
            defineElements();
            registerEvent();
        }, 2000);
    });
};