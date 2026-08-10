# QR Code Attendance Fix Guide

## Problem
QR code attendance scanning is not recording data in the attendance table.

## Root Causes Identified
1. **Missing QR code data** in member/staff records
2. **Database table structure issues**
3. **Silent error handling** making debugging difficult

## Solution Steps

### Step 1: Fix Database Structure
Run the database fix script to ensure all required tables and columns exist:

```
http://your-domain/qr/fix_attendance_table.php
```

This script will:
- Check if attendance table exists and create it if missing
- Verify all required columns are present
- Create attendance_archive table if needed

### Step 2: Fix QR Code Data
Run the QR code data fix script to populate missing QR code data:

```
http://your-domain/qr/fix_qr_codes.php
```

This script will:
- Add `qr_code_data` column to members and staff tables if missing
- Update all members with QR code data: `FIT_TRACK_MEMBER_ID:MEM-YYYY-XXXX`
- Update all staff with QR code data: `FIT_TRACK_STAFF_ID:STAFF-YYYY-XXXX`

### Step 3: Test QR Scanner
Use the test scanner to verify the system is working:

```
http://your-domain/qr/test_scanner.html
```

This page allows you to:
- Test QR code scanning with camera
- Manually enter QR code data for testing
- See detailed server responses
- Debug any issues

### Step 4: Check Logs
If issues persist, check the debug logs:

- **QR Scan Debug Log**: `qr/logs/qr_scan_debug.log`
- **QR Scan Error Log**: `qr/logs/qr_scan_errors.log`

These logs will show exactly what's happening during QR code processing.

## Manual Database Fixes

If the scripts don't work, you can run these SQL commands manually:

### 1. Create/Update Attendance Table
```sql
CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    member_code VARCHAR(50) NOT NULL,
    date DATE NOT NULL,
    time_in DATETIME DEFAULT NULL,
    time_out DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_member_date (member_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2. Add QR Code Data Column to Members
```sql
ALTER TABLE members ADD COLUMN qr_code_data VARCHAR(100) NULL;
UPDATE members SET qr_code_data = CONCAT('FIT_TRACK_MEMBER_ID:', member_id) WHERE qr_code_data IS NULL OR qr_code_data = '';
```

### 3. Add QR Code Data Column to Staff
```sql
ALTER TABLE staff ADD COLUMN qr_code_data VARCHAR(100) NULL;
UPDATE staff SET qr_code_data = CONCAT('FIT_TRACK_STAFF_ID:', staff_id) WHERE qr_code_data IS NULL OR qr_code_data = '';
```

## Testing the Fix

### 1. Generate QR Codes
After fixing the data, generate QR codes for testing:

```
http://your-domain/qr/generate.php?type=member&id=1
http://your-domain/qr/generate.php?type=staff&id=1
```

### 2. Test Scanning
1. Go to the admin attendance page
2. Click "Scan QR Code" button
3. Scan a generated QR code
4. Check if attendance appears in the table

### 3. Manual Testing
Use the test scanner page to manually test QR code data:

1. Go to `qr/test_scanner.html`
2. Enter QR code data manually (e.g., `FIT_TRACK_MEMBER_ID:MEM-2024-0001`)
3. Click "Test QR Code"
4. Check the response

## Common Issues and Solutions

### Issue: "Member not found" error
**Solution**: Check if the member exists and has QR code data:
```sql
SELECT id, member_id, first_name, last_name, qr_code_data FROM members WHERE member_id = 'MEM-2024-0001';
```

### Issue: "Staff not found" error
**Solution**: Check if the staff exists and has QR code data:
```sql
SELECT id, staff_id, first_name, last_name, qr_code_data FROM staff WHERE staff_id = 'STAFF-2024-0001';
```

### Issue: Database connection errors
**Solution**: Check database configuration in `includes/db.php`

### Issue: Permission errors
**Solution**: Ensure the web server has write permissions to the `qr/logs/` directory

## Verification

After applying the fixes, verify the system works by:

1. **Checking database**: Ensure members/staff have QR code data
2. **Testing scanner**: Use the test scanner page
3. **Checking attendance**: Verify records appear in the attendance table
4. **Checking logs**: Ensure no errors in debug logs

## Files Modified

- `qr/scan.php` - Added comprehensive error logging
- `qr/fix_qr_codes.php` - New script to fix QR code data
- `qr/test_scanner.html` - New test page for debugging
- `qr/logs/` - New directory for debug logs

## Next Steps

1. Run the fix scripts
2. Test with the scanner
3. Check logs for any remaining issues
4. Generate QR codes for actual members/staff
5. Train users on the new system

If issues persist after following this guide, check the debug logs for specific error messages and contact support with the log details.
