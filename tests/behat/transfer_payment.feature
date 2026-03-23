@paygw_transfer
Feature: Transfer payment gateway
  In order to pay for courses using bank transfers
  As a student
  I need to be able to complete payments via Vodafone Cash and InstaPay

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | student1 | Student | One | student1@example.com |
      | teacher1 | Teacher | One | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
    And the following "payment accounts" exist:
      | name | gateways |
      | Test Account | transfer |
    And the following config values are set as admin:
      | enablecredit | 1 | paygw_transfer |
      | secret | testsecret | paygw_transfer |
      | walletnumbers | <p>Transfer to Vodafone Cash number: 01012345678</p> | paygw_transfer |
      | instapayaddresses | <p>Transfer to InstaPay: test@university.instapay</p> | paygw_transfer |

  @javascript
  Scenario: Student views payment form for course enrolment
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    And I press "Enrol me"
    When I select "Bank transfer (Vodafone Cash/InstaPay)" from the "Payment method" singleselect
    Then I should see "Transfer to Vodafone Cash number: 01012345678"
    And I should see "Transfer to InstaPay: test@university.instapay"
    And I should see "Vodafone Cash" in the "#id_gateway" "css_element"
    And I should see "InstaPay" in the "#id_gateway" "css_element"

  @javascript
  Scenario: Student submits Vodafone Cash payment form
    Given I log in as "student1"
    And I am on the transfer payment form for course "C1" with amount "100.00"
    When I set the following fields to these values:
      | Gateway | Vodafone Cash |
      | Sender (Vodafone Cash number) | 01098765432 |
      | Amount | 100.00 |
    And I press "Submit"
    Then I should see "Payment submitted successfully"
    And I should see "Your payment is being processed"

  @javascript
  Scenario: Student submits InstaPay payment form
    Given I log in as "student1"
    And I am on the transfer payment form for course "C1" with amount "150.00"
    When I set the following fields to these values:
      | Gateway | InstaPay |
      | Sender (InstaPay account) | student1@university |
      | Amount | 150.00 |
    And I press "Submit"
    Then I should see "Payment submitted successfully"
    And I should see "Your payment is being processed"

  @javascript
  Scenario: Student submits invalid payment form
    Given I log in as "student1"
    And I am on the transfer payment form for course "C1" with amount "50.00"
    When I set the following fields to these values:
      | Gateway | Vodafone Cash |
      | Sender (Vodafone Cash number) | invalid |
      | Amount | 50.00 |
    And I press "Submit"
    Then I should see "Invalid Vodafone Cash number format"

  @javascript
  Scenario: Admin views orders report
    Given I log in as "admin"
    And I navigate to "Payments > Payment accounts" in site administration
    And I click on "Test Account" "link"
    And I click on "Orders" "link" in the "region-main" "region"
    Then I should see "Transfer Payment Orders"
    And I should see "Order ID"
    And I should see "User"
    And I should see "Status"
    And I should see "Actions"

  @javascript
  Scenario: Admin views messages report
    Given I log in as "admin"
    And I navigate to "Payments > Payment accounts" in site administration
    And I click on "Test Account" "link"
    And I click on "Messages" "link" in the "region-main" "region"
    Then I should see "Transfer Payment Messages"
    And I should see "Sender"
    And I should see "Amount"
    And I should see "Status"
    And I should see "Actions"

  @javascript
  Scenario: Admin marks order as done
    Given I log in as "admin"
    And a transfer order exists for student "student1" with amount "200.00"
    And a transfer message exists from "01012345678" with amount "200.00"
    When I navigate to the orders report
    And I click on "Mark as done" "link" in the "200.00" "table_row"
    And I select the message from "01012345678" with amount "200.00"
    And I press "Complete payment"
    Then I should see "Payment completed successfully"
    And the order should be marked as completed
    And the message should be marked as used

  @javascript
  Scenario: Admin filters messages by date range
    Given I log in as "admin"
    And transfer messages exist with various dates
    When I navigate to the messages report
    And I set the following fields to these values:
      | Date from | 2024-01-01 |
      | Date to | 2024-12-31 |
    And I press "Filter"
    Then I should see only messages within the date range

  @javascript
  Scenario: Student tops up wallet credit
    Given I log in as "student1"
    And wallet credit is enabled
    When I navigate to my profile
    And I click on "Top up credit" "link"
    And I select "Bank transfer (Vodafone Cash/InstaPay)" from the "Payment method" singleselect
    And I set the following fields to these values:
      | Gateway | Vodafone Cash |
      | Sender (Vodafone Cash number) | 01055556666 |
      | Amount | 50.00 |
    And I press "Submit"
    Then I should see "Credit request submitted"
    And I should see "Your credit request is being processed"