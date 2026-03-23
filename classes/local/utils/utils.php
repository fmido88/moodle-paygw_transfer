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

use core\exception\coding_exception;
use core_text;
use paygw_transfer\admin\admin_setting_confightmleditorwithimages;
use paygw_transfer\reportbuilder\messages;
use stdClass;

/**
 * Utils.
 *
 * @package    paygw_transfer
 * @copyright  2026 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class utils {
    /**
     * Transfer to wallet.
     * @var int
     */
    public const GATEWAY_WALLET = 1;

    /**
     * Transfer to InstaPay.
     * @var int
     */
    public const GATEWAY_INSTAPAY = 2;

    /**
     * Get the gateway options.
     * @return string[]
     */
    public static function get_gateways_options() {
        return [
            self::GATEWAY_INSTAPAY => get_string('instapay', 'paygw_transfer'),
            self::GATEWAY_WALLET   => get_string('wallet', 'paygw_transfer'),
        ];
    }

    /**
     * Clean wallet number.
     * @param string $number
     */
    public static function clean_wallet_number(string $number) {
        $pattern        = '/0?1(0|1|2|5)\d{8}/';
        $replacepattern = '/[^0-9\+]/';
        // Clean the number first.
        $number = preg_replace($replacepattern, '', $number);
        $result = preg_match($pattern, $number, $matches);

        if (!$result) {
            return '';
        }

        return $matches[0] ?? '';
    }

    /**
     * Clean instapay account input.
     * @param  string $account
     * @return string
     */
    public static function clean_instapay_account(string $account): string {
        $account = core_text::strtolower($account);

        if (strpos($account, '@instapay') !== false) {
            $account = core_text::substr($account, 0, core_text::strlen($account) - 9);
        }

        if (empty($account)) {
            return '';
        }

        $pattern = '/[^0-9a-z.\-_]/';

        return preg_replace($pattern, '', $account);
    }

    /**
     * Validate input instapay account input.
     * @param  string $account
     * @return bool
     */
    public static function validate_instapay_account(string $account): bool {
        return $account === self::clean_instapay_account($account);
    }

    /**
     * Validate wallet number input.
     * @param  string $number
     * @return bool
     */
    public static function validate_wallet_number(string $number) {
        $cleaned = self::clean_wallet_number($number);

        if (empty($cleaned)) {
            return false;
        }

        $nospaces = preg_replace('/\s*/', '', $number);

        if (strpos($nospaces, '+2') === 0) {
            $nospaces = core_text::substr($nospaces, 2);
        } else if (strpos($nospaces, '002') === 0) {
            $nospaces = core_text::substr($nospaces, 3);
        }

        $valid = $cleaned === $nospaces;

        return $valid;
    }

    /**
     * Get formatted description to how transfer by wallet.
     * @return string
     */
    public static function get_wallet_description() {
        $numbers = admin_setting_confightmleditorwithimages::get_formatted_text('walletnumbers');

        if (empty($numbers)) {
            $numbers = '';
        }

        return get_string('wallet_description_body', 'paygw_transfer', $numbers);
    }

    /**
     * Get formatted description to how to transfer by instapay.
     * @return string
     */
    public static function get_instapay_description() {
        $addresses = admin_setting_confightmleditorwithimages::get_formatted_text('instapayaddresses');

        if (empty($addresses)) {
            $addresses = '';
        }

        return get_string('instapay_description_body', 'paygw_transfer', $addresses);
    }

    /**
     * Extract the parameters date from and date to from submitted data.
     * @param ?stdClass $data
     * @param string    $type
     *
     * @throws coding_exception
     * @return int
     */
    public static function extract_date_from_data(?stdClass $data, string $type): int {
        if (!\in_array($type, ['from', 'to'])) {
            throw new coding_exception("Invalid \$type '$type' for date extraction. Expected 'from' or 'to'.");
        }
        $param   = "date{$type}";
        $from    = $type === 'from';
        $dperiod = DAYSECS;

        return match (true) {
            !empty($data->$param)    && !\is_array($data->$param)     => $from ? $data->$param : $data->$param + $dperiod,
            isset($_REQUEST[$param]) && !\is_array($_REQUEST[$param]) => optional_param(
                                                                                    $param,
                                                                                    $from ? time() - $dperiod : time(),
                                                                                    PARAM_INT
                                                                                ),
            (
                $date = $data->$param
                ?? optional_param_array($param, false, PARAM_INT)
            ) !== false                                               => mktime(
                ($from ? 0 : 23),
                ($from ? 0 : 59),
                ($from ? 0 : 59),
                $date['month'],
                $date['day'],
                $date['year']
            ),
            default => $from ? time() - $dperiod : time(),
        };
    }

    /**
     * Get the http headers.
     * @return array
     */
    public static function get_headers(): array {
        if (function_exists('getallheaders')) {
            return getallheaders();
        }

        if (function_exists('apache_request_headers')) {
            return apache_request_headers();
        }

        $headers = [];

        $copyserver = [
            'CONTENT_TYPE'   => 'Content-Type',
            'CONTENT_LENGTH' => 'Content-Length',
            'CONTENT_MD5'    => 'Content-Md5',
        ];

        foreach ($_SERVER as $key => $value) {
            if (substr($key, 0, 5) === 'HTTP_') {
                $key = substr($key, 5);

                if (!isset($copyserver[$key]) || !isset($_SERVER[$key])) {
                    $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $key))));

                    $headers[$key] = $value;
                }
            } else if (isset($copyserver[$key])) {
                $headers[$copyserver[$key]] = $value;
            }
        }

        if (!isset($headers['Authorization'])) {
            if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                $headers['Authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            } else if (isset($_SERVER['PHP_AUTH_USER'])) {
                $basicpass = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : '';

                $headers['Authorization'] = 'Basic ' . base64_encode($_SERVER['PHP_AUTH_USER'] . ':' . $basicpass);
            } else if (isset($_SERVER['PHP_AUTH_DIGEST'])) {
                $headers['Authorization'] = $_SERVER['PHP_AUTH_DIGEST'];
            }
        }

        return $headers;
    }

    /**
     * Get the message table output.
     * @param  ?stdClass $data
     * @return string
     */
    public static function get_messages_table(?stdClass $data = null): string {
        global $DB, $PAGE;
        $context = \core\context\system::instance();

        if (!has_capability('paygw/transfer:viewsms', $context)) {
            return '';
        }

        $report = \core_reportbuilder\system_report_factory::create(messages::class, $context);
        $PAGE->requires->js_call_amd('paygw_transfer/messages-table', 'init');

        if (is_siteadmin()) {
            $PAGE->requires->js_call_amd('paygw_transfer/mark-done', 'init');
            $PAGE->requires->js_call_amd('paygw_transfer/delete-messages', 'init');
        }

        return $report->output();
    }

    /**
     * Pagination data.
     * @param int $count
     * @param int $page
     * @param int $perpage
     *
     * @return object{limitfrom: int, limitnum: int, pages: int, page: int}
     */
    public static function pagination(int $count, int $page, int $perpage) {
        $limitfrom = 0;
        $limitnum  = 0;
        $pages     = 1;

        if ($perpage > 0) {
            $pages = ceil($count / $perpage);

            // Fix page order to not exceed the number of pages and not have a value less than 1.
            $page = max(min($page, $pages), 1);

            $limitfrom = max($page - 1, 0) * $perpage;
            $limitnum  = $perpage;
        } else {
            $page = 1;
        }

        return (object)[
            'limitfrom' => $limitfrom,
            'limitnum'  => $limitnum,
            'pages'     => $pages,
            'page'      => $page,
        ];
    }
}
