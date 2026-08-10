# QR Scanner Troubleshooting Guide

## Problem: QR Scanner Not Recording Attendance

If your QR scanner is not recording attendance properly, follow these steps to diagnose and fix the issue.

## Step 1: Database Connection Test

First, test your database connection and table structure:

1. Open your browser and go to: `http://your-domain/qr/test_database.php`
2. Check if all tables exist and have the correct structure
3. Verify that you have members/staff data with QR codes

## Step 2: Manual QR Code Testing

Test the QR scanner manually:

1. Go to: `http://your-domain/qr/test_scanner.html`
2. Enter a sample QR code data (e.g., `FIT_TRACK_MEMBER_ID:MEM-2024-0001`)
3. Test both normal and debug modes
4. Check the response for any errors

## Step 3: Check Debug Logs

If using debug mode, check the logs:

1. Look for the file: `logs/scan_debug.log`
2. Check for any error messages or issues
3. Verify that the QR data is being processed correctly

## Common Issues and Solutions

### Issue 1: Database Connection Failed
**Symptoms:** Error messages about database connection
**Solution:** 
- Check `includes/db.php` configuration
- Verify MySQL server is running
- Ensure database credentials are correct

### Issue 2: Tables Don't Exist
**Symptoms:** "Table doesn't exist" errors
**Solution:**
- Run the database setup script
- Check if the `attendance` table was created properly
- Verify all required tables exist

### Issue 3: QR Code Data Not Found
**Symptoms:** "Member not found" or "Staff not found" errors
**Solution:**
- Check if members/staff have `qr_code_data` populated
- Verify QR code format matches expected format
- Ensure member_id/staff_id exists in database

### Issue 4: Attendance Not Being Inserted
**Symptoms:** No error but no attendance record created
**Solution:**
- Check database permissions
- Verify the attendance table structure
- Check for any SQL errors in logs

### Issue 5: Scanner Not Detecting QR Codes
**Symptoms:** Camera works but no QR codes detected
**Solution:**
- Ensure QR code is clear and well-lit
- Check if QR code format is correct
- Verify jsQR library is loaded properly

## QR Code Format Requirements

The system expects QR codes in these formats:

1. **Member QR Codes:**
   - `FIT_TRACK_MEMBER_ID:MEM-YYYY-XXXX`
   - `MEM-YYYY-XXXX` (raw format)

2. **Staff QR Codes:**
   - `FIT_TRACK_STAFF_ID:STAFF-YYYY-XXXX`
   - `STAFF-YYYY-XXXX` (raw format)

## Database Structure Requirements

Ensure your database has these tables with correct structure:

### Members Table
```sql
CREATE TABLE members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id VARCHAR(20) NOT NULL UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    qr_code_data VARCHAR(255) DEFAULT NULL,
    -- other fields...
);
```

### Staff Table
```sql
CREATE TABLE staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id VARCHAR(20) NOT NULL UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    qr_code_data VARCHAR(255) DEFAULT NULL,
    -- other fields...
);
```

### Attendance Table
```sql
CREATE TABLE attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    member_code VARCHAR(50) NOT NULL,
    date DATE NOT NULL,
    time_in DATETIME DEFAULT NULL,
    time_out DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_member_date (member_id, date)
);
```

## Testing Steps

1. **Test Database Connection:**
   ```
   http://your-domain/qr/test_database.php
   ```

2. **Test QR Scanner Manually:**
   ```
   http://your-domain/qr/test_scanner.html
   ```

3. **Test with Sample Data:**
   - Use a known member ID from your database
   - Try both with and without prefixes
   - Test both time-in and time-out scenarios

4. **Check Admin Attendance Page:**
   ```
   http://your-domain/admin/attendance.php
   ```

## Debug Mode

Use the debug version of the scanner for detailed logging:

1. In the test form, select "Debug Scan (with logging)"
2. Check the `logs/scan_debug.log` file for detailed information
3. Look for any error messages or unexpected behavior

## Common Error Messages

- **"Member not found"**: Check if the member exists and has correct QR code data
- **"Staff not found"**: Check if the staff exists and has correct QR code data
- **"Failed to log time in"**: Database insertion error, check permissions and table structure
- **"Failed to log time out"**: Database update error, check permissions and table structure

## File Permissions

Ensure these directories are writable:
- `logs/` (for debug logs)
- `uploads/` (for member photos)

## Browser Compatibility

The QR scanner requires:
- Modern browser with camera access
- HTTPS (for camera access)
- JavaScript enabled
- jsQR library loaded

## Still Having Issues?

If you're still experiencing problems:

1. Check the browser console for JavaScript errors
2. Check the server error logs
3. Verify all files are in the correct locations
4. Test with a simple QR code first
5. Ensure the database has sample data

## Support

If you need additional help:
1. Check the debug logs for specific error messages
2. Test with the provided test scripts
3. Verify your database structure matches the requirements
4. Ensure all required files are present and accessible
