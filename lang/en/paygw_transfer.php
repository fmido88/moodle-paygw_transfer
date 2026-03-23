<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin strings are defined here.
 *
 * @package     paygw_transfer
 * @category    string
 * @copyright   2026 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['address_required'] = 'Address is required, InstaPay payment address without @instapay';
$string['agree'] = 'I Agree and aware';
$string['alreadyused'] = 'Already used before';
$string['amount'] = 'Amount';
$string['amount_invalid'] = 'This amount is invalid it should be a positive whole number.';
$string['anothersite'] = 'Another site';
$string['anothersite_desc'] = 'write the url of another site that sync this data with.';
$string['anothersite_toke_desc'] = 'The web service token of the other site to ensure connection';
$string['anothersite_token'] = 'web service token of other site';

$string['cannotusenow'] = 'Cannot use this feature now, you can retry in {$a->hours} hours : {$a->min} minutes : {$a->sec} seconds';
$string['charger'] = 'Charger';
$string['completed'] = 'Completed';
$string['component'] = 'Component';
$string['confirm_save_msg'] = 'It is appear that you have been using this identifier {$a} for a while, we can save it in our database so you will be automatically charged whenever you send again from it with no need to confirm the sending details again. PLEASE NOTE THAT this identifier should be belonging to you and only you to avoid any mistakes.';
$string['confirmdeletemessage'] = 'Are you sure you want to delete this message: {$a}';
$string['created'] = 'Created';
$string['creditsuccess'] = 'Transaction complete and you wallet charged by {$a->amount}. Your balance before was: {$a->before} And net amount {$a->credit} added. Your balance now is: {$a->after}';
$string['creditwarning'] = 'Please make sure that the data is absolute correct to be able to credit correctly.<br>If the data is wrong you cannot retry immediately.';

$string['declined'] = 'Declined';
$string['deletemessage'] = 'Delete message';
$string['deleteorder'] = 'Delete order';
$string['deletesender'] = 'Delete sender';
$string['deletesenderconfirm'] = 'Are you sure that you want to delete this saved sender?';
$string['done'] = 'done';
$string['dontsave'] = 'No don\'t save it';

$string['editsender'] = 'Edit sender';
$string['enablecredit'] = 'Enable self-credit';
$string['enablecredit_desc'] = 'If enabled users can credit themselves according to the data in the sms table.';
$string['error_donewithnovalue'] = 'Please select a record to mark as done before charging a user\'s wallet';
$string['exact_amount'] = 'Exact Amount';
$string['exact_amount_help'] = 'The exact amount sent. (for example if you sent 51, don\'t write 50)';
$string['exact_day'] = 'Exact Day';
$string['exact_day_help'] = 'Select the day at which the payment sent.';

$string['failed'] = 'Failed';
$string['from'] = 'From';

$string['gateway'] = 'Gateway';
$string['gatewaydescription'] = 'Transfer the fee to a mobile wallet or InstaPay, complete your payment by entering the exact data of the transfer process or contact administration for help.';
$string['gatewayname'] = 'Transfer';

$string['instapay'] = 'InstaPay';
$string['instapay_address'] = 'InstaPay Address (without @instapay)';
$string['instapay_address_help'] = 'Your instapay account address only if you send to our instapay, if you sent to wallet please use wallet option.';
$string['instapay_description_body'] = 'After sending credit to our InstaPay account<br>{$a}<br>You can enter here the precise data (the exact amount sent, the InstaPay address without @instapay) then the payment will be processed automatically';
$string['instapay_description_label'] = 'Pay by transfer to InstaPay';
$string['instapayaddresses_config'] = 'Instapay Addresses';
$string['instapayaddresses_config_desc'] = 'Add a clear list of instapay number or address that the user should send the fee to. (could include qr code images)';
$string['insufficentamount'] = 'Insufficient amount {$a->amount}, the required amount for this item is {$a->cost}.';
$string['invalidsender'] = 'Invalid sender, it should be small letters, numbers and may be containing ( . , - , _ )';
$string['itemid'] = 'Item id';

$string['lastupdate'] = 'Last update';
$string['limitbetween'] = 'Duration between two successive tries';
$string['limitbetween_desc'] = 'Users cannot try to credit themselves multiple time withing this period.';

$string['markdone'] = 'Mark as completed payment';
$string['markmessagedone'] = 'Mark message as done';
$string['markmessageundone'] = 'Mark message as undone';
$string['message'] = 'Message';
$string['messageid'] = 'Message id';
$string['messages'] = 'Messages';
$string['messages_report'] = 'Transfer messages';
$string['multiplemsgs'] = 'Multiple messages stored with the same data, cannot credit you without manual verification, please contact support.';

$string['new'] = 'New';
$string['nofuture'] = 'Sorry, we didn\'t invent something to make us able to receive message from the future yet, make sure that day  didn\'t pass the current day';
$string['nomatchdata'] = 'No messages in with this data.';
$string['nosmss'] = 'There is no sms\'s in this period of time, try change another period';
$string['notused'] = 'Not used';

$string['orderentity'] = 'Order';
$string['orderid'] = 'Order id';
$string['orders'] = 'Orders';
$string['orders_report'] = 'Transfer payment gateway orders';

$string['paybytransfer_title'] = 'Pay by transfer to mobile wallet or instapay';
$string['payment_successful'] = 'Payment successful';
$string['paymentarea'] = 'Payment Area';
$string['paymentid'] = 'Payment Id';
$string['pending'] = 'Pending';
$string['pluginname'] = 'Transfer to Mobile Wallet or InstaPay';

$string['receiveat'] = 'Received at';
$string['receiver'] = 'Receiver';
$string['redirecting'] = 'Redirecting to <a href="{$a}">{$a}</a>';
$string['round_amount'] = 'Round Amount';

$string['saved_senders'] = 'Payment gateway transfer saved senders';
$string['savesender'] = 'Save sender id';
$string['savingpayment'] = 'Confirm Saving payment Method';
$string['secretkey'] = 'Secret key';
$string['secretkey_desc'] = 'A unique secret key that should be matched with that in the message to ensure secure webhook if needed';
$string['selectmessage'] = 'Select message';
$string['sender'] = 'Sender';
$string['sender_invalid'] = 'Sender data is invalid, mobile number in case of wallet transfer should start with 01 and contain 11 numbers in total';
$string['sender_required'] = 'Sender is required, mobile number in case of wallet transfer';
$string['sendernumber'] = 'Sender number';
$string['sendernumber_help'] = 'Write down the mobile number from which the amount has been sent.';
$string['status'] = 'Status';
$string['subject'] = 'Status';
$string['submit_filter'] = 'Filter';
$string['success'] = 'Success';

$string['timecreated'] = 'Time Created';
$string['timemodified'] = 'Time Modified';
$string['to'] = 'To';
$string['toomanymessagestoshow'] = 'Too many messages to show';
$string['topup_desc'] = 'Self-credit by the user.';
$string['topupbytransfer'] = 'Topup you Site wallet by transfer';
$string['transfer:delete'] = 'Delete transfer messages';
$string['transfer:markdone'] = 'Mark transfer messages as done';
$string['transfer:viewreport'] = 'View orders report';
$string['transfer:viewsms'] = 'view messages table';

$string['undoneonly'] = 'Show undone only';
$string['used'] = 'Used';
$string['usedbefore'] = 'This transaction has been used before, contact support for further information.';

$string['vc_transactions'] = 'Vodafone Cash Transactions';

$string['wallet'] = 'Mobile Wallet';
$string['wallet_description_body'] = 'After sending credit to number <br>{$a}<br>from any wallet, you can enter here the precise data (the exact amount sent, the mobile number from which you sent this amount) then the payment will be processed automatically';
$string['wallet_description_label'] = 'Pay by transfer to mobile wallet';
$string['walletnumbers_config'] = 'Wallet numbers';
$string['walletnumbers_config_desc'] = 'Add a clear list of wallet number that the user could transfer to';
