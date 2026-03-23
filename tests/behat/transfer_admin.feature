@paygw_transfer
Feature: Transfer payment gateway administration
  In order to manage transfer payments
  As an admin
  I need to configure the gateway and manage payments

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | student1 | Student | One | student1@example.com |
      | admin1 | Admin | One | admin1@example.com |
    And the following "payment accounts" exist:
      | name | gateways |
      | Test Account | transfer |
    And the following config values are set as admin:
      | secret | adminsecret | paygw_transfer |
      | enablecredit | 1 | paygw_transfer |
      | limitbetween | 3600 | paygw_transfer |

  @javascript
  Scenario: Admin configures transfer gateway settings
    Given I log in as "admin"
    When I navigate to "Plugins > Payment gateways > Transfer" in site administration
    Then I should see "Transfer payment gateway settings"
    And I set the following fields to these values:
      | Secret key | newsecret123 |
      | Enable credit top-up | 1 |
      | Cooldown between credit attempts | 7200 |
      | Wallet transfer instructions | <p>Transfer to: 01012345678</p> |
      | InstaPay addresses | <p>Transfer to: admin@university.instapay</p> |
    And I press "Save changes"
    Then I should see "Changes saved"

  @javascript
  Scenario: Admin views payment statistics
    Given I log in as "admin"
    And transfer orders exist with various statuses
    When I navigate to the transfer reports
    Then I should see payment statistics including:
      | Total orders | 10 |
      | Pending orders | 3 |
      | Completed orders | 7 |
      | Total amount | 1500.00 EGP |

  @javascript
  Scenario: Admin searches for specific messages
    Given I log in as "admin"
    And transfer messages exist from various senders
    When I navigate to the messages report
    And I set the field "Search" to "01012345678"
    And I press "Search"
    Then I should see only messages from "01012345678"
    And I should see the message details:
      | Sender | 01012345678 |
      | Amount | 100.00 |
      | Status | Pending |

  @javascript
  Scenario: Admin marks multiple messages as done
    Given I log in as "admin"
    And multiple transfer messages exist
    When I navigate to the messages report
    And I select multiple messages
    And I press "Mark selected as done"
    Then the selected messages should be marked as completed
    And I should see "Messages marked as done"

  @javascript
  Scenario: Admin deletes old messages
    Given I log in as "admin"
    And old transfer messages exist
    When I navigate to the messages report
    And I select messages older than 30 days
    And I press "Delete selected"
    Then the old messages should be deleted
    And I should see "Messages deleted successfully"

  @javascript
  Scenario: Admin views cross-site messages
    Given I log in as "admin"
    And cross-site messaging is configured
    And messages exist on the remote site
    When I navigate to the messages report
    And I check "Include remote site messages"
    And I press "Refresh"
    Then I should see messages from both local and remote sites

  @javascript
  Scenario: Admin exports payment data
    Given I log in as "admin"
    And transfer orders and messages exist
    When I navigate to the reports page
    And I press "Export to CSV"
    Then a CSV file should be downloaded
    And the CSV should contain:
      | Order ID | User | Amount | Status | Date |

  @javascript
  Scenario: Admin configures rate limiting
    Given I log in as "admin"
    When I navigate to the transfer settings
    And I set the field "Cooldown between credit attempts" to "1800"
    And I press "Save changes"
    Then credit requests should be limited to one per 30 minutes

  @javascript
  Scenario: Admin views webhook logs
    Given I log in as "admin"
    And webhook requests have been received
    When I navigate to the webhook logs
    Then I should see a list of webhook requests with:
      | Timestamp | Status | Message |
      | 2024-01-01 10:00 | Success | Message parsed |
      | 2024-01-01 10:05 | Error | Invalid secret |

  @javascript
  Scenario: Admin manually creates payment order
    Given I log in as "admin"
    When I navigate to the orders page
    And I press "Create order"
    And I set the following fields to these values:
      | User | Student One |
      | Amount | 250.00 |
      | Currency | EGP |
      | Component | enrol_fee |
      | Payment area | fee |
      | Item ID | 1 |
    And I press "Create"
    Then a new order should be created with status "created"