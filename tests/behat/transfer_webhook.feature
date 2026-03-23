@paygw_transfer
Feature: Transfer payment gateway webhook processing
  In order to process SMS notifications
  As the payment system
  I need to handle webhook requests correctly

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | student1 | Student | One | student1@example.com |
    And the following "payment accounts" exist:
      | name | gateways |
      | Test Account | transfer |
    And the following config values are set as admin:
      | secret | webhooksecret | paygw_transfer |

  Scenario: Webhook receives valid Vodafone Cash SMS
    Given a transfer order exists for "student1" with amount "100.00"
    When the webhook receives a POST request with:
      | message | webhooksecret You have received 100 EGP from Vodafone Cash 01012345678 |
    Then the webhook should return success
    And a message should be created with:
      | sender | 01012345678 |
      | amount | 100.00 |
      | message | You have received 100 EGP from Vodafone Cash 01012345678 |

  Scenario: Webhook receives valid InstaPay SMS
    Given a transfer order exists for "student1" with amount "150.00"
    When the webhook receives a POST request with:
      | message | webhooksecret استلام مبلغ 150 جنيه مصري من user@instapay |
    Then the webhook should return success
    And a message should be created with:
      | sender | user |
      | amount | 150.00 |
      | message | استلام مبلغ 150 جنيه مصري من user@instapay |

  Scenario: Webhook receives SMS with balance information
    Given a transfer order exists for "student1" with amount "75.00"
    When the webhook receives a POST request with:
      | message | webhooksecret You have received 75 EGP. Your balance is 500 EGP |
    Then the webhook should return success
    And a message should be created with:
      | sender | extracted_sender |
      | amount | 75.00 |
      | message | You have received 75 EGP |
    And the balance information should be removed from the message

  Scenario: Webhook receives invalid secret
    When the webhook receives a POST request with:
      | message | wrongsecret You have received 50 EGP |
    Then the webhook should return an error
    And no message should be created

  Scenario: Webhook receives malformed SMS
    When the webhook receives a POST request with:
      | message | webhooksecret This is not a payment message |
    Then the webhook should return success
    But no message should be created

  Scenario: Webhook processes multiple messages
    Given multiple transfer orders exist
    When the webhook receives multiple POST requests with valid SMS
    Then each valid SMS should create a corresponding message
    And invalid SMS should be ignored

  Scenario: Webhook handles Arabic amount formats
    Given a transfer order exists for "student1" with amount "200.50"
    When the webhook receives a POST request with:
      | message | webhooksecret استلام مبلغ 200.50 جنيه مصري من 01098765432 |
    Then the webhook should return success
    And a message should be created with amount "200.50"

  Scenario: Webhook handles English amount formats
    Given a transfer order exists for "student1" with amount "125.75"
    When the webhook receives a POST request with:
      | message | webhooksecret You have received 125.75 L.E from 01112345678 |
    Then the webhook should return success
    And a message should be created with amount "125.75"

  Scenario: Webhook validates sender formats
    Given a transfer order exists for "student1" with amount "50.00"
    When the webhook receives a POST request with various sender formats:
      | message | webhooksecret You have received 50 EGP from +201012345678 |
    Then the sender should be normalized to "01012345678"

  Scenario: Webhook handles secret in different locations
    Given a transfer order exists for "student1" with amount "100.00"
    When the webhook receives requests with secret in:
      | header | X-Secret: webhooksecret |
      | query | ?secret=webhooksecret |
      | message | webhooksecret You have received 100 EGP |
    Then all requests should be accepted
    And messages should be created for valid requests

  Scenario: Webhook processes concurrent requests
    Given multiple transfer orders exist
    When multiple webhook requests are received simultaneously
    Then all valid requests should be processed
    And no duplicate messages should be created
    And the system should remain consistent

  Scenario: Webhook handles extreme amounts
    Given transfer orders exist with amounts "0.01" and "999999.99"
    When the webhook receives SMS with these amounts
    Then messages should be created with correct amounts
    And amounts should be parsed accurately

  Scenario: Webhook logs processing results
    When the webhook receives various requests
    Then processing results should be logged including:
      | timestamp | request_data | result | error_message |
    And logs should be accessible to administrators