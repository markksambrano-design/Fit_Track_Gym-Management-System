# Payroll History System Setup Guide

## Overview
The Payroll History system allows you to track and manage historical payroll records for all staff members. This system provides comprehensive payroll tracking with filtering, reporting, and management capabilities.

## Features

### 1. Payroll History Management
- **View Historical Records**: Browse all payroll records with detailed information
- **Filter by Month**: Filter records by specific months
- **Filter by Status**: Filter by pending, paid, or cancelled records
- **Filter by Staff**: View records for specific staff members
- **Export Data**: Export payroll history to CSV format

### 2. Record Management
- **Add New Records**: Manually add payroll history entries
- **Edit Records**: Update existing payroll records
- **Mark as Paid**: Mark pending records as paid
- **View Details**: View comprehensive record information

### 3. Statistics Dashboard
- **Total Records**: Count of all payroll history records
- **Total Paid Amount**: Sum of all paid amounts
- **Pending Records**: Count of pending payments
- **Paid Records**: Count of completed payments

## Setup Instructions

### 1. Database Setup
The payroll history table should already exist. If not, run:
```bash
php admin/utilities/create_payroll_history_table.php
```

### 2. Populate Existing Data
To populate the payroll history with existing attendance data:
```bash
php admin/utilities/populate_payroll_history.php
```

This will:
- Create payroll history records for the last 6 months
- Calculate hours worked with 8-hour daily cap
- Set appropriate status and notes

### 3. Access the System
1. Navigate to the admin panel
2. Click on "Payroll History" in the sidebar
3. The system will display all payroll history records

## Usage Guide

### Viewing Records
1. **Default View**: Shows current month's records
2. **Filter Records**: Use the filter form to narrow down results
3. **Search by Staff**: Select specific staff member to view their history
4. **Status Filter**: Filter by pending, paid, or cancelled records

### Managing Records

#### Adding New Records
1. Click "Add Record" button
2. Select staff member from dropdown
3. Set period start and end dates
4. Enter hours worked and hourly rate
5. Set status (pending/paid/cancelled)
6. Add payment date if applicable
7. Add notes if needed
8. Click "Add Record"

#### Editing Records
1. Click the edit button (pencil icon) for any record
2. Modify the required fields
3. Click "Save Changes"

#### Marking as Paid
1. Click the check button for pending records
2. Confirm the action
3. Record will be marked as paid with current date

#### Viewing Details
1. Click the eye icon to view full record details
2. View comprehensive information including:
   - Staff information
   - Payroll calculations
   - Status and payment information
   - Notes

### Exporting Data
1. Set desired filters (month, status, staff)
2. Click "Export" button
3. CSV file will be downloaded with filtered data

## Data Structure

### Payroll History Table
- `id`: Unique record identifier
- `staff_id`: Reference to staff member
- `period_start`: Start date of payroll period
- `period_end`: End date of payroll period
- `hours_worked`: Total hours worked (with 8-hour daily cap)
- `hourly_rate`: Hourly rate (default: ₱62.50)
- `status`: Record status (pending/paid/cancelled)
- `payment_date`: Date when payment was made
- `notes`: Additional notes or comments
- `created_at`: Record creation timestamp
- `updated_at`: Last update timestamp

### Status Types
- **Pending**: Record created but not yet paid
- **Paid**: Payment completed
- **Cancelled**: Record cancelled (not paid)

## Integration with Existing System

### Automatic Population
The system can automatically populate payroll history from existing attendance data:
- Calculates hours worked with 8-hour daily cap
- Creates monthly records for the last 6 months
- Sets appropriate status and notes
- Prevents duplicate records

### Staff Payroll Integration
- Payroll history complements the main staff payroll system
- Provides historical tracking and reporting
- Maintains data consistency across systems

## Troubleshooting

### Common Issues

1. **No Records Displayed**
   - Check if payroll history table exists
   - Run the populate script to create initial data
   - Verify staff have attendance records

2. **Filter Not Working**
   - Ensure proper date format (YYYY-MM)
   - Check if staff ID exists in database
   - Verify status values are correct

3. **Export Issues**
   - Check file permissions for downloads
   - Ensure sufficient memory for large datasets
   - Verify CSV headers are correct

### Performance Considerations
- Large datasets may take time to load
- Use filters to narrow down results
- Export data for offline analysis
- Regular database maintenance recommended

## Security Notes
- All data is properly sanitized and validated
- SQL injection protection implemented
- Access restricted to admin users only
- Sensitive financial data properly protected

## Support
For technical support or questions about the payroll history system, refer to the main system documentation or contact the development team.
