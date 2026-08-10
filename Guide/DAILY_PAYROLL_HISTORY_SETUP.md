# Daily Payroll History Setup Guide

## Overview
The Daily Payroll History system automatically creates payroll records for every staff member every day based on their attendance. This ensures that payroll history is always up-to-date and accurate.

## Features
- **Automatic Daily Records**: Creates a payroll history record for each staff member every day
- **Real-time Updates**: Automatically updates payroll records when staff clocks out
- **8-Hour Daily Cap**: Automatically applies 8-hour cap per day
- **Duplicate Prevention**: Prevents duplicate records for the same date
- **Manual Trigger**: Can be manually triggered via web interface or command line

## How It Works

### 1. Automatic Creation on Clock Out
When a staff member clocks out, the system automatically:
- Calculates hours worked for that day
- Applies 8-hour daily cap
- Creates or updates the daily payroll history record

### 2. Daily Batch Processing
A cron job can be set up to run daily (recommended at 1:00 AM) to:
- Process all staff members for the previous day
- Create records for staff who didn't clock out
- Update any records that may have changed

## Setup Instructions

### Option 1: Automatic via Cron Job (Recommended)

1. **Run the setup script** to get the cron command:
   ```bash
   php admin/utilities/setup_daily_payroll_cron.php
   ```

2. **Add to crontab**:
   ```bash
   crontab -e
   ```

3. **Add this line** (runs daily at 1:00 AM):
   ```
   0 1 * * * /usr/bin/php /path/to/your/project/admin/utilities/daily_payroll_history.php >> /var/log/daily_payroll.log 2>&1
   ```

### Option 2: Windows Task Scheduler

1. Open Task Scheduler
2. Create a new task
3. Set trigger: Daily at 1:00 AM
4. Set action:
   - Program: `php.exe` (full path)
   - Arguments: `admin/utilities/daily_payroll_history.php`
   - Start in: Project root directory

### Option 3: Manual Execution

**Via Command Line:**
```bash
# Process yesterday's attendance (default)
php admin/utilities/daily_payroll_history.php

# Process a specific date
php admin/utilities/daily_payroll_history.php?date=2025-12-15
```

**Via Web Interface:**
```
POST/GET to: admin/actions/staff_actions.php?action=generate_daily_payroll&date=2025-12-15
```

## File Structure

- `admin/utilities/daily_payroll_history.php` - Main script for batch processing
- `admin/utilities/daily_payroll_helper.php` - Helper function for individual staff processing
- `admin/utilities/setup_daily_payroll_cron.php` - Setup helper script
- `admin/actions/staff_actions.php` - Web-accessible action endpoint

## Payroll Record Structure

Each daily record contains:
- **staff_id**: Internal staff ID
- **period_start**: Date (same as period_end for daily records)
- **period_end**: Date (same as period_start for daily records)
- **hours_worked**: Total hours worked (capped at 8 hours)
- **hourly_rate**: ₱62.50 (fixed)
- **status**: 'pending' (default)
- **payment_date**: NULL (until marked as paid)

## Integration Points

### Time Out Action
The system is automatically integrated with the staff time_out action. When a staff member clocks out:
1. Time out is recorded
2. Daily payroll record is automatically created/updated
3. Hours are calculated and capped at 8 hours

### Payroll History Page
All daily records appear in the Payroll History page, where you can:
- View daily records
- Filter by date, staff, or status
- Mark records as paid
- Export payroll data

## Testing

### Test Individual Staff
```bash
php admin/utilities/daily_payroll_helper.php
# Or call via web: admin/actions/staff_actions.php?action=generate_daily_payroll&date=2025-12-15
```

### Test All Staff
```bash
php admin/utilities/daily_payroll_history.php?date=2025-12-15
```

## Troubleshooting

### Records Not Being Created
1. Check if staff has attendance records for that date
2. Verify database connection
3. Check error logs: `/var/log/daily_payroll.log` (if using cron)

### Duplicate Records
The system prevents duplicates by checking for existing records with the same:
- staff_id
- period_start
- period_end

If duplicates exist, they will be updated instead of created.

### Hours Not Calculating Correctly
- Verify attendance records have valid time_in and time_out
- Check that time_out is after time_in
- Ensure date format is correct (Y-m-d)

## Maintenance

### View Logs
```bash
tail -f /var/log/daily_payroll.log
```

### Manual Cleanup
If needed, you can manually delete or update records via the Payroll History page in the admin panel.

## Notes

- The system processes **yesterday's** attendance by default (to ensure all clock-outs are recorded)
- Records are created even for 0 hours (to maintain complete history)
- The 8-hour daily cap is automatically applied
- All records start with 'pending' status until marked as paid

## Support

For issues or questions, check:
- Error logs in `/var/log/daily_payroll.log`
- Database error logs
- PHP error logs

