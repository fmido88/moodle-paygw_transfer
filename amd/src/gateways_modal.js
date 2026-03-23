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
 * TODO describe module gateway_modal
 *
 * @module     paygw_transfer/gateway_modal
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getString} from 'core/str';
import Ajax from 'core/ajax';

/**
 * Process the payment.
 * @param {string} component
 * @param {string} paymentArea
 * @param {integer} itemId
 * @param {string} description
 * @returns {string}
 */
export const process = async(component, paymentArea, itemId, description) => {


    let result = await processPayment(component, paymentArea, itemId, description);

    if (!result.success) {
        throw new Error(result.reason);
    }

    let returnStr = getString('redirecting', 'paygw_transfer', result.url);

    await (window.location.href = result.url);
    return new Promise(() => returnStr);
};

/**
 * Create a new order and return the redirection url.
 * @param {string} component
 * @param {string} paymentArea
 * @param {integer} itemId
 * @param {string} description
 * @returns {Object}
 */
async function processPayment(component, paymentArea, itemId, description) {
    let requests = Ajax.call([{
        methodname: 'paygw_transfer_process',
        args: {
            component: component,
            paymentarea: paymentArea,
            itemid: itemId,
            description: description,
        }
    }]);
    let response = await requests[0];
    return response;
}
