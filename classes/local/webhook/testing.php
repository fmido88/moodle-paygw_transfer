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

namespace paygw_transfer\local\webhook;

use core\url;
use core_text;
use curl;
use phpunit_util;

/**
 * Class testing.
 *
 * @package    paygw_transfer
 * @copyright  2026 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class testing {
    /**
     * Get the data generator.
     * @return \testing_data_generator
     */
    protected static function get_generator(): \testing_data_generator {
        global $CFG;
        require_once("{$CFG->libdir}/phpunit/classes/util.php");
        static $generator;

        if (!isset($generator)) {
            $firstnames = ['أحمد', 'محمد', 'محمود', 'مصطفى', 'عبد الله', 'نهى', 'سماح', 'سمر', 'جنى', 'مريم'];
            $lastnames  = ['محمود', 'سعيد', 'غالي', 'أبو العنين', 'محسن', 'شلبي', 'سعد', 'عبد الرحمن', 'وليد', 'فتحي'];

            $gen       = phpunit_util::get_data_generator();
            $generator = clone $gen;

            $generator->firstnames = array_merge(array_slice($generator->firstnames, 0, 20), $firstnames);
            $generator->lastnames  = array_merge(array_slice($generator->lastnames, 0, 20), $lastnames);
        }

        return $generator;
    }

    /**
     * Generate a random fullname.
     * @return string
     */
    public static function get_random_name() {
        $country   = rand(0, 2);
        $firstname = rand(0, 4);
        $lastname  = rand(0, 4);
        $female    = rand(0, 1);
        $generator = self::get_generator();
        $sender    = $generator->firstnames[($country * 10) + $firstname + ($female * 5)];
        $sender .= ' ' . $generator->lastnames[($country * 10) + $lastname + ($female * 5)];

        return $sender;
    }

    /**
     * Generate a random instapay gateway account.
     * @return string
     */
    public static function get_random_instapay_sender() {
        $charset = 'abcdefghijklmnopqrstuvwxyz0123456789.-_';
        $length  = rand(7, 15);
        $count   = core_text::strlen($charset);
        $random  = '';

        while ($length--) {
            $random .= $charset[mt_rand(0, $count - 1)];
        }

        return $random;
    }

    /**
     * Generate a random Egyptian phone number.
     * @return string
     */
    public static function get_random_phone_number() {
        $operators = ['0', '1', '2', '5'];
        $number    = '01';
        $number .= $operators[rand(0, 3)];

        for ($i = 0; $i < 8; $i++) {
            $number .= rand(0, 9);
        }

        return $number;
    }

    /**
     * Get the real secret number.
     * @return string
     */
    protected static function get_secret() {
        $secret = get_config('paygw_transfer', 'secret');

        if (!$secret || empty(trim($secret))) {
            return '';
        }

        return trim($secret);
    }

    /**
     * Generate random webhook messages.
     * @param  int       $number
     * @param  bool      $save
     * @return webhook[]
     */
    public static function generate_random_instapay_messages(int $number = 5, bool $save = false) {
        $secret   = self::get_secret();
        $messages = [];

        for ($i = 0; $i < $number; $i++) {
            $amount  = random_int(20, 900);
            $sender  = self::get_random_instapay_sender();
            $message = self::mock_instapay_message($amount, $sender, $secret);
            $message = new webhook($message);

            if ($save) {
                $message->save();
            }
            $messages[] = $message;
        }

        return $messages;
    }

    /**
     * Generate random Vodafone cash webhook messages.
     * @param  int       $number
     * @param  bool      $save   Save the messages to database or not.
     * @return webhook[]
     */
    public static function generate_random_vodafone_cash_messages(int $number = 5, bool $save = false) {
        $secret   = self::get_secret();
        $messages = [];

        for ($i = 0; $i < $number; $i++) {
            $amount  = random_int(20, 900) . '.' . random_int(0, 99);
            $balance = random_int(0, 15000) . '.' . random_int(0, 99);
            $name    = self::get_random_name();
            $sender  = self::get_random_phone_number();
            $date    = random_int(time() - YEARSECS, time());
            $date    = userdate($date, '%H:%M %d-%m-%y');
            $message = self::mock_vodafone_cash_message(
                $amount,
                $sender,
                $name,
                $balance,
                date: $date,
                reference: '01' . random_int(1111111111, 9999999999),
                secret: $secret
            );
            $message = new webhook($message);

            if ($save) {
                $message->save();
            }
            $messages[] = $message;
        }

        return $messages;
    }

    /**
     * Mock an instapay message for testing.
     * @param  float  $amount
     * @param  string $sender
     * @param  string $secret
     * @return string
     */
    public static function mock_instapay_message(
        $amount = 157,
        $sender = 'john.doe_34',
        $secret = '',
    ): string {
        return "{$secret}You have received {$amount} EGP from {$sender}@instapay";
    }

    /**
     * Mock a vodafone cash transfer message for testing.
     * @param  float  $amount
     * @param  string $sender
     * @param  string $name
     * @param  float  $balance
     * @param  string $date
     * @param  string $reference
     * @param  string $temp      the template index 'random, ar, en'
     * @param  string $secret
     * @return string
     */
    public static function mock_vodafone_cash_message(
        $amount = 105.50,
        $sender = '01012345678',
        $name = 'John Doe',
        $balance = 7820.46,
        $date = '21:17 26-03-05',
        $reference = '018146640963',
        $temp = 'random',
        $secret = '',
    ): string {

        $templates = [];
        $templates[] = 'تم استلام مبلغ {amount} جنيه من رقم {sender} المسجل بإسم {name} رصيدك'
        .' الحالي {balance} تاريخ العملية {date} رقم العملية {reference}.
تابع كل مصروفاتك من تاريخ المعاملات على أبلكيشن أنا فودافون http://vf.eg/vfcash';

        $templates[] = 'Mar 10, 2026 10:39:12 AM: Received EGP{amount} from {sender} '
            .'to Mobile Account Number 7215. Ref: {reference} Available Balance: {balance}';

        foreach ($templates as &$template) {
            $template = $secret . str_replace(
                ['{amount}', '{sender}', '{name}', '{balance}', '{date}', '{reference}'],
                [$amount, $sender, $name, $balance, $date, $reference],
                $template
            );
        }

        if ($temp === 'random') {
            return $templates[array_rand($templates)];
        }

        if (\in_array($temp, $templates)) {
            return $template;
        } else if (\in_array($temp, ['ar', 'arabic'])) {
            return $templates[0];
        } else {
            return $templates[1];
        }
    }

    /**
     * Mock sending a message without secret key (Must be added manually) before sending.
     * @param  string                               $message
     * @param  array                                $payload
     * @param  array                                $headers
     * @return array{info: array, response: string}
     */
    public static function mock_receiving_message(string $message, array $payload = [], array $headers = []) {
        global $CFG;
        if (PHPUNIT_TEST) {
            $tobecleanedpost = ['message'];
            $_POST['message'] = $message;
            foreach($payload as $key => $param) {
                if ($key === 'message') {
                    continue;
                }
                $_POST[$key] = $param;
                $tobecleanedpost[] = $key;
            }
            $tobecleanedheaders = [];
            foreach($headers as $header) {
                // Mock sending headers.
                [$key, $value] = explode(':', $header);
                if (empty($key) || empty($value)) {
                    continue;
                }
                $key = core_text::strtotitle(trim($key));
                $_SERVER["HTTP_{$key}"] = trim($value);
                $tobecleanedheaders[] = "HTTP_{$key}";
            }
            ob_start();
            require("{$CFG->dirroot}/payment/gateway/transfer/webhook.php");

            // Cleanup the globals.
            foreach ($tobecleanedpost as $key) {
                unset($_POST[$key]);
            }
            foreach ($tobecleanedheaders as $key) {
                unset($_SERVER[$key]);
            }

            $response = ob_get_clean();
            if (false !== ($errorpos = strpos($response, 'ERROR: '))) {
                $error = substr($response, $errorpos + 7);
                $response = substr($response, 0, $errorpos);
            }
            return [
                'info'     => ['http_code' => http_response_code()],
                'response' => trim($response),
                'error'    => trim($error ?? ''),
            ];
        }
        require_once("{$CFG->libdir}/filelib.php");
        $curl = new curl(['ignoresecurity' => true]);
        $url  = new url('/payment/gateway/transfer/webhook.php');

        $curl->setHeader($headers);

        $payload['message'] = $message;
        $response           = $curl->post($url->out(false), $payload);

        return [
            'response' => $response,
            'info'     => $curl->get_info(),
            'error'    => $curl->error ?? '',
        ];
    }
}
