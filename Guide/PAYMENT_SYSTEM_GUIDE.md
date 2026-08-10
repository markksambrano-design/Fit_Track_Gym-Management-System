# FIT_TRACK Payment System Guide

## Overview
The payment system in FIT_TRACK automatically tracks all membership fees for registrations and renewals. Every time a member registers or renews their membership, a payment record is automatically created in the system.

## How It Works

### 1. Automatic Payment Creation

#### During Registration
- When a new member registers through `admin/register.php`
- System automatically creates a payment record in `member_payroll` table
- Payment amount is calculated based on membership type:
  - **Regular Membership**: ₱1,000
  - **Student Membership**: ₱700
- Initial status is set to "pending"

#### During Renewal
- When a membership is renewed through `admin/members_actions.php`
- System automatically creates a new payment record
- Payment amount follows the same pricing structure
- Notes field includes "Membership renewal" information

### 2. Payment Statuses

The system uses three payment statuses:

- **Pending**: Payment is expected but not yet received
- **Paid**: Payment has been received and recorded
- **Overdue**: Payment is past due (automatically updated for expired memberships)

### 3. Payment Management Features

#### In `admin/payments.php`:

1. **Payment Statistics Dashboard**
   - Total payments amount
   - Paid amount
   - Pending amount
   - Overdue amount

2. **Payment Records Table**
   - Shows all payment records with member details
   - Search and filter functionality
   - Status indicators with color coding

3. **Add Payment**
   - Manual payment entry for any member
   - Auto-calculates amount based on membership type
   - Supports multiple payment methods

4. **Edit Payment Status**
   - Update payment status (pending/paid/overdue)
   - Track payment processing

5. **Delete Payment**
   - Remove payment records if needed
   - Confirmation dialog for safety

6. **Update Statuses**
   - Manual trigger to update overdue payments
   - Runs the `payment_status_updater.php` script

### 4. Database Structure

#### `member_payroll` Table
```sql
CREATE TABLE member_payroll (
    id INT(11) NOT NULL AUTO_INCREMENT,
    member_id INT(11) NOT NULL,
    membership_type ENUM('regular','student') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    status ENUM('pending','paid','overdue') DEFAULT 'pending',
    payment_method ENUM('cash','gcash','bank_transfer') DEFAULT NULL,
    transaction_id VARCHAR(100) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
);
```

### 5. Payment Methods Supported

- Cash
- GCash
- Bank Transfer
- Credit Card
- Debit Card

### 6. Automatic Status Updates

#### `payment_status_updater.php`
- Automatically marks payments as "overdue" for expired memberships
- Can be run manually or scheduled as a cron job
- Updates are logged for audit purposes

### 7. Integration Points

#### Registration System (`admin/register.php`)
- Creates initial payment record during member registration
- Shows payment information in success message

#### Renewal System (`admin/members_actions.php`)
- Creates payment record during membership renewal
- Includes renewal details in payment notes

#### Member Management (`admin/members.php`)
- Shows payment status in member details
- Links to payment records

### 8. Usage Instructions

#### For Administrators:

1. **View All Payments**
   - Go to Admin Dashboard → Payments
   - See all payment records with statistics

2. **Add Manual Payment**
   - Click "Add Payment" button
   - Select member from dropdown
   - Enter payment details
   - Save payment record

3. **Update Payment Status**
   - Click edit button on any payment
   - Change status to "paid" when payment is received
   - Add notes if needed

4. **Update Overdue Payments**
   - Click "Update Statuses" button
   - System will automatically mark expired payments as overdue

5. **Search and Filter**
   - Use search box to find specific payments
   - Use status filter to view payments by status

#### For Members:
- Payment information is visible in their profile
- Payment history can be accessed through member dashboard

### 9. Best Practices

1. **Regular Status Updates**
   - Run status updater daily or weekly
   - Keep payment statuses current

2. **Payment Recording**
   - Record payments immediately when received
   - Use appropriate payment method
   - Add notes for special circumstances

3. **Audit Trail**
   - All payment changes are logged
   - Maintain records for accounting purposes

4. **Communication**
   - Notify members of pending payments
   - Send reminders for overdue payments

### 10. Troubleshooting

#### Common Issues:

1. **Payment Not Showing**
   - Check if member exists in database
   - Verify payment record was created during registration

2. **Status Not Updating**
   - Run manual status updater
   - Check membership expiration dates

3. **Amount Calculation Issues**
   - Verify membership type is correct
   - Check pricing configuration

#### Error Logs:
- Check PHP error logs for database issues
- Payment status updates are logged with details

### 11. Future Enhancements

Potential improvements for the payment system:

1. **Automated Reminders**
   - Email notifications for pending payments
   - SMS reminders for overdue payments

2. **Payment Gateway Integration**
   - Online payment processing
   - Credit card processing

3. **Reporting Features**
   - Monthly payment reports
   - Revenue analytics
   - Payment trend analysis

4. **Invoice Generation**
   - Automatic invoice creation
   - PDF invoice downloads

5. **Payment Plans**
   - Installment payment options
   - Recurring payment setup

---

This payment system ensures that all membership fees are properly tracked and managed, providing a complete financial overview of the gym's membership revenue.
