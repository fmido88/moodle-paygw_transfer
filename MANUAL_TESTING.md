# Transfer Payment Gateway Manual Testing Checklist

## Pre-Installation Setup
- [ ] Ensure Moodle version is 2025.04.14 or higher
- [ ] Verify PHP version compatibility (7.4+ recommended)
- [ ] Check that enrol_wallet plugin is installed (optional but recommended)
- [ ] Confirm database supports UTF-8 for Arabic text processing
- [ ] Set up SMS forwarding service to send POST requests to webhook endpoint

## Installation and Configuration
- [ ] Download and install the transfer payment gateway plugin
- [ ] Run database installation/upgrade scripts
- [ ] Verify plugin appears in Site Administration > Plugins > Payment gateways
- [ ] Configure payment account with transfer gateway enabled
- [ ] Set webhook secret key in plugin settings
- [ ] Configure wallet transfer instructions (HTML editor)
- [ ] Configure InstaPay addresses (HTML editor)
- [ ] Enable/disable credit top-up functionality
- [ ] Set cooldown period between credit attempts
- [ ] Configure cross-site messaging (optional)

## Basic Functionality Testing

### Payment Form Display
- [ ] Navigate to course enrolment page
- [ ] Select "Bank transfer (Vodafone Cash/InstaPay)" payment method
- [ ] Verify payment form displays correctly
- [ ] Check that gateway dropdown shows Vodafone Cash and InstaPay options
- [ ] Verify wallet instructions are displayed
- [ ] Verify InstaPay instructions are displayed
- [ ] Test form responsiveness on mobile devices

### Vodafone Cash Payment Submission
- [ ] Fill out payment form with valid Vodafone number (010xxxxxxxxx)
- [ ] Enter valid amount (0.01 - 999999.99)
- [ ] Submit form
- [ ] Verify success message displays
- [ ] Check that order is created in database with status 'created'
- [ ] Verify user is redirected appropriately

### InstaPay Payment Submission
- [ ] Fill out payment form with valid InstaPay account (user@instapay)
- [ ] Enter valid amount
- [ ] Submit form
- [ ] Verify success message displays
- [ ] Check that order is created with correct gateway type

### Form Validation Testing
- [ ] Test invalid Vodafone number formats (too short, wrong operator, non-numeric)
- [ ] Test invalid InstaPay formats (missing @instapay, special characters)
- [ ] Test amounts outside valid range (negative, zero, too large)
- [ ] Test empty required fields
- [ ] Verify appropriate error messages display
- [ ] Test form submission with JavaScript disabled

## Webhook Processing Testing

### Vodafone Cash SMS Processing
- [ ] Send POST request to webhook with valid Vodafone SMS format
- [ ] Verify message is created in database
- [ ] Check amount parsing (Arabic: "استلام مبلغ X جنيه مصري")
- [ ] Check amount parsing (English: "You have received X EGP")
- [ ] Verify sender extraction (010xxxxxxxxx format)
- [ ] Test balance information removal from message

### InstaPay SMS Processing
- [ ] Send POST request with valid InstaPay SMS
- [ ] Verify message creation
- [ ] Check sender extraction (user@instapay → user)
- [ ] Test various InstaPay account formats

### Security Testing
- [ ] Test webhook with correct secret key (header, query, message)
- [ ] Test webhook with incorrect secret key
- [ ] Verify unauthorized requests are rejected
- [ ] Test secret key validation in different locations
- [ ] Check for SQL injection vulnerabilities
- [ ] Verify XSS protection in webhook input

### Edge Cases
- [ ] Test SMS with typos in amount words
- [ ] Test mixed Arabic/English messages
- [ ] Test messages with extra whitespace
- [ ] Test very long SMS messages
- [ ] Test SMS with special characters
- [ ] Test concurrent webhook requests

## Admin Interface Testing

### Orders Management
- [ ] Access orders report as admin
- [ ] Verify orders list displays correctly
- [ ] Test filtering by user, status, date range
- [ ] Test sorting by different columns
- [ ] Check pagination functionality
- [ ] Verify order details display correctly

### Messages Management
- [ ] Access messages report as admin
- [ ] Verify messages list displays
- [ ] Test search functionality by sender
- [ ] Test filtering by date range and status
- [ ] Check message details display
- [ ] Test pagination and sorting

### Order Completion Process
- [ ] Select pending order
- [ ] Click "Mark as done"
- [ ] Verify message selection form appears
- [ ] Select appropriate message
- [ ] Complete the order
- [ ] Verify order status changes to 'success'
- [ ] Verify message is marked as done
- [ ] Check user enrolment/course access
- [ ] Verify notification is sent to user

### Bulk Operations
- [ ] Select multiple messages
- [ ] Mark multiple as done
- [ ] Verify all selected messages update
- [ ] Test bulk delete functionality
- [ ] Check confirmation dialogs

## Credit/Wallet Integration Testing

### Credit Top-up (if enrol_wallet enabled)
- [ ] Access user profile credit section
- [ ] Select transfer payment method
- [ ] Submit credit request
- [ ] Verify credit request is recorded
- [ ] Complete payment via admin
- [ ] Verify credit is added to user wallet

### Rate Limiting
- [ ] Submit multiple credit requests quickly
- [ ] Verify cooldown period is enforced
- [ ] Check appropriate error messages
- [ ] Test rate limiting reset after cooldown

## Cross-Site Integration Testing

### Remote Site Configuration
- [ ] Configure remote Moodle site URL
- [ ] Set up web service token
- [ ] Test connection to remote site
- [ ] Verify remote messages appear in local report

### Cross-Site Message Marking
- [ ] Mark message as done on local site
- [ ] Verify remote site reflects the change
- [ ] Test error handling for connection failures

## Report and Analytics Testing

### Data Export
- [ ] Generate CSV export of orders
- [ ] Verify CSV format and data accuracy
- [ ] Test export with filters applied
- [ ] Check large dataset export performance

### Statistics Display
- [ ] Verify payment statistics calculation
- [ ] Check total amounts, counts by status
- [ ] Test statistics with date range filters
- [ ] Verify real-time updates

## Performance and Load Testing

### High Volume Testing
- [ ] Process 100+ webhook requests
- [ ] Create 1000+ orders
- [ ] Test report loading with large datasets
- [ ] Check database query performance

### Concurrent User Testing
- [ ] Multiple users submitting payments simultaneously
- [ ] Multiple admins processing payments
- [ ] Test database locking and consistency

## Error Handling and Recovery

### Network Issues
- [ ] Test webhook processing during network outages
- [ ] Verify graceful handling of SMS service downtime
- [ ] Check error logging and alerting

### Database Issues
- [ ] Test behavior during database connection loss
- [ ] Verify transaction rollback on failures
- [ ] Check data consistency after errors

### Invalid Data Handling
- [ ] Test processing of corrupted SMS messages
- [ ] Verify handling of invalid payment amounts
- [ ] Check behavior with malformed webhook requests

## Security Testing

### Access Control
- [ ] Verify non-admin users cannot access admin reports
- [ ] Test capability checks for different user roles
- [ ] Check proper context restrictions

### Data Privacy
- [ ] Verify sensitive payment data is properly stored
- [ ] Check audit logging of admin actions
- [ ] Test data anonymization in logs

### Input Validation
- [ ] Test SQL injection attempts
- [ ] Verify XSS prevention
- [ ] Check CSRF protection on forms

## Mobile and Responsive Testing

### Mobile Browser Testing
- [ ] Test payment form on mobile devices
- [ ] Verify SMS display on small screens
- [ ] Check touch interface functionality
- [ ] Test with different mobile browsers

### SMS Display
- [ ] Verify Arabic text displays correctly
- [ ] Test right-to-left text rendering
- [ ] Check font support for Arabic characters

## Integration Testing

### Course Enrolment Integration
- [ ] Complete payment and verify course access
- [ ] Test different enrolment methods (fee, manual, etc.)
- [ ] Verify enrolment triggers work correctly

### Wallet Plugin Integration
- [ ] Test credit top-up functionality
- [ ] Verify wallet balance updates
- [ ] Check wallet transaction history

### Notification System Integration
- [ ] Verify payment completion notifications
- [ ] Test email notification delivery
- [ ] Check notification content and formatting

## Browser Compatibility Testing

### Desktop Browsers
- [ ] Test with Chrome, Firefox, Safari, Edge
- [ ] Verify JavaScript functionality
- [ ] Check CSS styling consistency

### Mobile Browsers
- [ ] Test iOS Safari
- [ ] Test Android Chrome
- [ ] Verify responsive design

## Localization and Internationalization

### Arabic Language Support
- [ ] Test Arabic interface elements
- [ ] Verify Arabic SMS processing
- [ ] Check right-to-left layout support

### Currency Display
- [ ] Verify EGP currency formatting
- [ ] Test decimal places handling
- [ ] Check currency symbol display

## Backup and Recovery Testing

### Data Backup
- [ ] Verify payment data is included in Moodle backups
- [ ] Test selective backup/restore
- [ ] Check data integrity after restore

### Disaster Recovery
- [ ] Test system recovery after failures
- [ ] Verify payment data consistency
- [ ] Check webhook replay functionality

## Final Verification

### End-to-End Testing
- [ ] Complete full payment cycle from user submission to completion
- [ ] Verify all system components work together
- [ ] Test real-world usage scenarios

### Documentation Review
- [ ] Verify README and help text accuracy
- [ ] Check admin documentation completeness
- [ ] Test user-facing instructions

### Code Quality Checks
- [ ] Run PHP code analysis tools
- [ ] Check for security vulnerabilities
- [ ] Verify coding standards compliance