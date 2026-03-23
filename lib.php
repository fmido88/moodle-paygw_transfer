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
 * Plugin functions.
 *
 * @package    paygw_transfer
 * @copyright  2026 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Mark that the message as done in other site.
 * @param array $params
 * @return void
 */
function paygw_transfer_other_mark_done($params) {
    global $CFG, $PAGE;
    $domainname = get_config('paygw_transfer', 'othersite');
    $token = get_config('paygw_transfer', 'othertoken');
    if (empty($domainname) || empty($token)) {
        return;
    }

    require_once($CFG->libdir.'/filelib.php');

    $functionname = 'paygw_transfer_other_mark_done';

    // REST RETURNED VALUES FORMAT.
    $restformat = 'json';

    // REST CALL.
    if (!$PAGE->headerprinted) {
        header('Content-Type: text/plain');
    }

    $serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token . '&wsfunction='.$functionname;
    $curl = new curl;
    // If rest format == 'xml', then we do not add the param for backward compatibility with Moodle < 2.2.
    $restformat = ($restformat == 'json') ? '&moodlewsrestformat=' . $restformat : '';
    $resp = $curl->post($serverurl . $restformat, $params);
    $curl->cleanopt();
}

/**
 * Serve stored files.
 * @param ?stdClass $course
 * @param ?stdClass $cm
 * @param core\context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return void
 */
function paygw_transfer_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {

    if ($context->contextlevel != CONTEXT_SYSTEM) {
        send_file_not_found();
    }

    if (!in_array($filearea, ['walletnumbers', 'instapayaddresses'])) {
        send_file_not_found();
    }

    $itemid = array_shift($args);

    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'paygw_transfer', $filearea, $itemid, $filepath, $filename);

    if (!$file || $file->is_directory()) {
        send_file_not_found();
    }

    send_stored_file($file);
}
