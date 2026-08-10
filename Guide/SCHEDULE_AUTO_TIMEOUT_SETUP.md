# Schedule-Based Auto Timeout System Setup Guide

## 🎯 **Overview**

This system automatically times out staff members based on their employment type and assigned schedules with a 30-minute grace period. Half-day staff are timed out based on their morning/afternoon schedules, while full-day staff are timed out at 6:30 PM.

## 🚀 **Features**

- **Employment Type Aware**: Handles both half-day (scheduled) and full-day staff
- **Schedule-Based Timeout**: Automatic timeout based on staff schedule (morning/afternoon) or employment type
- **30-Minute Grace Period**: Allows 30 minutes after scheduled end time before auto-timeout
- **Real-Time Processing**: Runs every 15-30 minutes to check for overdue timeouts
- **Comprehensive Logging**: All timeout events are logged for audit purposes
- **Windows Task Scheduler**: Automated execution on Windows systems

## 📁 **Files Created**

### Core Files:
- `admin/utilities/schedule_auto_timeout.php` - Main timeout script
- `admin/utilities/setup_schedule_auto_timeout.php` - Setup helper script
- `admin/utilities/schedule_auto_timeout.bat` - Windows batch file
- `admin/utilities/schedule_auto_timeout.ps1` - PowerShell alternative

### Log Files:
- `logs/schedule_auto_timeout.log` - Main timeout activity log
- `logs/schedule_auto_timeout_errors.log` - Error logging

## 🔧 **Setup Instructions**

### 1. Initial Setup

Run the setup script to create necessary files:

```bash
cd admin/utilities
php setup_schedule_auto_timeout.php
```

### 2. Configure Windows Task Scheduler

1. Open Task Scheduler (search for "Task Scheduler" in Windows)
2. Click "Create Basic Task"
3. Name: `FITTRACK Schedule Auto Timeout`
4. Trigger: Daily, Start time: 06:00:00
5. Action: Start a program
6. Program/script: `C:\Users\Mark\Documents\Final_Capstone Project_FITTRACK\admin\utilities\schedule_auto_timeout.bat`
7. Start in: `C:\Users\Mark\Documents\Final_Capstone Project_FITTRACK\admin\utilities`

### 3. Configure Advanced Settings

After creating the basic task:
1. Open task properties
2. Go to Triggers tab, edit the trigger
3. Set "Repeat task every" to 15 minutes
4. Set duration to "Indefinitely"
5. Check "Stop all running tasks at end of repetition duration"

### 4. Test the System

Test the auto-timeout system manually:

```bash
cd admin/utilities
php schedule_auto_timeout.php
```

## 📋 **How It Works**

### Schedule Timing:
- **Morning Shift (Half Day)**: 7:00 AM - 12:00 PM → Auto-timeout at **12:30 PM**
- **Afternoon Shift (Half Day)**: 12:00 PM - 5:00 PM → Auto-timeout at **5:30 PM**
- **Full Day**: 7:00 AM - 6:00 PM → Auto-timeout at **6:30 PM**

### Employment Types Handled:
- **Half Day**: Requires morning/afternoon schedule assignment
- **Whole Day (Full Day)**: Automatic 6:00 PM end time
- **Other Types**: Part-time, contract staff are skipped (manual timeout only)

### Database Updates:
- Updates `staff_attendance` table with `time_out` timestamp
- Maintains accurate attendance records

## 📊 **Monitoring**

### Log Files:
- Check `logs/schedule_auto_timeout.log` for successful timeouts
- Check `logs/schedule_auto_timeout_errors.log` for any errors

### Manual Testing:
Run the script manually to see current status:
```bash
php schedule_auto_timeout.php
```

## ⚠️ **Important Notes**

- Staff must have a valid schedule (morning/afternoon) assigned
- Only affects staff who are currently timed in but haven't timed out
- Runs independently of the existing 8 PM general timeout
- Grace period prevents premature timeouts due to minor delays

## 🔧 **Troubleshooting**

### Common Issues:
1. **Script not running**: Check Task Scheduler configuration
2. **No timeouts occurring**: Verify staff have schedules assigned
3. **Permission errors**: Ensure PHP has write access to log files

### Manual Execution:
```bash
cd admin/utilities
php schedule_auto_timeout.php
```

## 📈 **Benefits**

- **Accurate Attendance**: Prevents forgotten timeouts
- **Fair Payroll**: Ensures proper time tracking
- **Automated Management**: Reduces manual intervention
- **Schedule Compliance**: Enforces shift end times

---

*This system works alongside the existing 8 PM general timeout for comprehensive attendance management.*