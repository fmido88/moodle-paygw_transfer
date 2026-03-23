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

namespace paygw_transfer\exceptions;

use core\exception\moodle_exception;

/**
 * Class secret_key_exception
 *
 * @package    paygw_transfer
 * @copyright  2026 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class secret_key_exception extends moodle_exception {
    /**
     * secret_key_exception constructor.
     *
     * @param string $message
     */
    public function __construct(string $message) {
        $error = "The message '$message' does not contain the secret key.";
        if (defined(PHPUNIT_TEST) && PHPUNIT_TEST) {
            $error .= " " . get_config('paygw_transfer', 'secret');
        }
        parent::__construct($error);
    }
}
