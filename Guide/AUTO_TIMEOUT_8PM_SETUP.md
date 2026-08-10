# Auto Timeout 8 PM System Setup Guide

## 🎯 **Overview**

This system automatically logs out all members and staff at 8:00 PM daily. It includes both server-side and client-side components to ensure complete coverage.

## 🚀 **Features**

- **Automatic 8 PM Timeout**: All active users are automatically logged out at 8:00 PM
- **Client-Side Warnings**: Users get 5-minute advance warning before timeout
- **Server-Side Processing**: Database records are automatically updated
- **Comprehensive Logging**: All timeout events are logged for audit purposes
- **Cross-Platform**: Works on both Linux/Unix (cron) and Windows (Task Scheduler)

## 📁 **Files Created**

### Server-Side Files:
- `admin/utilities/auto_timeout_8pm.php` - Main timeout script
- `admin/utilities/setup_auto_timeout_cron.php` - Setup helper
- `admin/utilities/test_auto_timeout.php` - Testing script
- `auto_logout.php` - Client-side logout endpoint

### Client-Side Files:
- `assets/js/auto_timeout.js` - JavaScript timeout manager
- Updated `member/components/header.php` - Includes auto-timeout script
- Updated `staff/components/header.php` - Includes auto-timeout script

### Log Files:
- `logs/auto_timeout.log` - Main timeout log
- `logs/auto_timeout_errors.log` - Error logging
- `logs/auto_logout.log` - Client-side logout events

## 🔧 **Setup Instructions**

### 1. Initial Setup

Run the setup script to create necessary directories and files:

```bash
cd admin/utilities
php setup_auto_timeout_cron.php
```

### 2. Test the System

Test the auto-timeout system:

```bash
# Basic test
php test_auto_timeout.php

# Full test with actual timeout (WARNING: This will timeout active users!)
php test_auto_timeout.php --test-timeout
```

### 3. Set Up Scheduled Execution

#### For Linux/Unix (Cron):

```bash
# Edit crontab
crontab -e

# Add this line to run at 8 PM daily:
0 20 * * * cd /path/to/your/project/admin/utilities && php auto_timeout_8pm.php >> ../../logs/auto_timeout.log 2>&1
```

#### For Windows (Task Scheduler):

1. Open Task Scheduler
2. Create Basic Task:
   - **Name**: FIT_TRACK Auto Timeout
   - **Trigger**: Daily at 8:00 PM
   - **Action**: Start a program
   - **Program**: `php.exe`
   - **Arguments**: `auto_timeout_8pm.php`
   - **Start in**: `C:\path\to\your\project\admin\utilities`

### 4. Verify Setup

Check that everything is working:

```bash
# Check logs
tail -f logs/auto_timeout.log

# Check database
mysql -u username -p database_name -e "SELECT * FROM timeout_logs ORDER BY created_at DESC LIMIT 5;"
```

## 🎮 **How It Works**

### Server-Side Process:

1. **8:00 PM Trigger**: Cron job or scheduled task runs `auto_timeout_8pm.php`
2. **Database Scan**: Script finds all active users (checked in but not checked out)
3. **Auto Timeout**: Updates all active records with current timestamp as `time_out`
4. **Logging**: Records the timeout event in `timeout_logs` table
5. **File Logging**: Writes detailed log to `logs/auto_timeout.log`

### Client-Side Process:

1. **JavaScript Check**: `auto_timeout.js` checks time every minute
2. **Warning Display**: Shows 5-minute warning before 8 PM
3. **Auto Logout**: At 8 PM, automatically logs out user
4. **Server Notification**: Notifies server of auto-logout
5. **Redirect**: Redirects to appropriate login page

## 📊 **Database Changes**

### New Table: `timeout_logs`

```sql
CREATE TABLE timeout_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    timeout_date DATE NOT NULL,
    timeout_time TIME NOT NULL,
    total_timeouts INT NOT NULL,
    member_timeouts INT DEFAULT 0,
    staff_timeouts INT DEFAULT 0,
    walkin_timeouts INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_timeout_date (timeout_date)
);
```

### Updated Records:

- **member_attendance**: `time_out` set to current timestamp
- **staff_attendance**: `time_out` set to current timestamp  
- **walk_in**: `time_out` set to current timestamp

## 🔍 **Monitoring & Troubleshooting**

### Check System Status:

```bash
# Test the system
php admin/utilities/test_auto_timeout.php

# Check recent timeouts
mysql -u username -p database_name -e "SELECT * FROM timeout_logs ORDER BY created_at DESC LIMIT 10;"

# Monitor logs
tail -f logs/auto_timeout.log
```

### Common Issues:

1. **Cron Job Not Running**:
   - Check cron service: `systemctl status cron`
   - Check cron logs: `grep CRON /var/log/syslog`
   - Verify file permissions

2. **Database Connection Issues**:
   - Check `includes/db.php` configuration
   - Verify database credentials
   - Test connection manually

3. **JavaScript Not Working**:
   - Check browser console for errors
   - Verify `assets/js/auto_timeout.js` is loaded
   - Test in different browsers

4. **Permission Issues**:
   - Ensure log directory is writable: `chmod 755 logs/`
   - Check file ownership: `chown -R www-data:www-data logs/`

## 📈 **Performance Impact**

- **Minimal**: Script runs once daily for a few seconds
- **Database**: Light queries, no performance impact
- **Client-Side**: Minimal JavaScript overhead
- **Logging**: Small log files, auto-rotated

## 🛡️ **Security Considerations**

- **Server-Side**: Script runs with same permissions as web server
- **Client-Side**: JavaScript can be disabled, but server-side still works
- **Logging**: Sensitive data is not logged
- **Database**: Uses prepared statements to prevent SQL injection

## 🔄 **Maintenance**

### Daily:
- Monitor logs for errors
- Check timeout_logs table for successful runs

### Weekly:
- Review log file sizes
- Clean up old logs if needed

### Monthly:
- Analyze timeout patterns
- Update system if needed

## 📞 **Support**

If you encounter issues:

1. Check the logs: `logs/auto_timeout.log` and `logs/auto_timeout_errors.log`
2. Run the test script: `php admin/utilities/test_auto_timeout.php`
3. Verify cron job is running: `crontab -l`
4. Check database connectivity

## ✅ **Verification Checklist**

- [ ] Setup script completed successfully
- [ ] Test script shows all green checkmarks
- [ ] Cron job or scheduled task configured
- [ ] JavaScript files included in headers
- [ ] Log directory is writable
- [ ] Database connection working
- [ ] Manual test completed successfully
- [ ] Monitoring setup in place

## 🎉 **Success!**

Your auto-timeout system is now configured! All members and staff will be automatically logged out at 8:00 PM daily, with proper warnings and logging.


