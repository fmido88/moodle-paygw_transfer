/* eslint-disable promise/no-nesting */
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
 * Potential messages selector module.
 *
 * @module     paygw_transfer/form-messages-select
 * @copyright  2025 Mohammad Farouk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
import Ajax from 'core/ajax';
import Templates from 'core/templates';
import * as Str from 'core/str';

class Messages {
    static processResults = function(selector, results) {
        var messages = [];
        if (Array.isArray(results)) {
            $.each(results, function(index, message) {
                messages.push({
                    value: message.id,
                    label: message._label,
                });
            });
            return messages;

        } else {
            return results;
        }
    };

    static transport = function(selector, query, success, failure) {
        var promise;

        var perpage = parseInt($(selector).attr('perpage'));
        if (isNaN(perpage)) {
            perpage = 100;
        }

        promise = Ajax.call([{
            methodname: 'paygw_transfer_search_messages',
            args: {
                search: query,
                page: 0,
                perpage: perpage + 1
            }
        }]);

        promise[0].then(function(results) {
            var promises = [],
                i = 0;

            if (results.length <= perpage) {
                // Render the label.
                $.each(results, function(index, message) {
                    let ctx = message;
                    promises.push(Templates.render('paygw_transfer/form-messages-select', ctx));
                });

                // Apply the label to the results.
                return $.when.apply($.when, promises).then(function() {
                    var args = arguments;
                    $.each(results, function(index, message) {
                        message._label = args[i];
                        i++;
                    });
                    success(results);
                    return;
                });

            } else {
                return Str.get_string('toomanymessagestoshow', 'paygw_transfer', '>' + perpage).then(function(toomanyuserstoshow) {
                    success(toomanyuserstoshow);
                    return;
                });
            }

        }).fail(failure);
    };
}

export default Messages;
