# FIT_TRACK Staff System Guide

## Overview
The FIT_TRACK Staff System provides a complete authentication and management solution for gym staff members. It's fully integrated with the admin panel and includes modern security features.

## System Components

### 1. Staff Authentication System
- **Login Page**: `staff/login.php`
- **Forgot Password**: `staff/forgot-password.php`
- **Reset Password**: `staff/reset-password.php`
- **Logout**: `staff/logout.php`

### 2. Staff Management (Admin Panel)
- **Staff Registration**: `admin/register.php` (Staff tab)
- **Staff Management**: `admin/staff.php`
- **Staff Actions**: `admin/staff_actions.php`

### 3. Staff Portal Features
- **Dashboard**: `staff/dashboard.php`
- **Profile Management**: `staff/profile.php`
- **Attendance Tracking**: `staff/attendance.php`
- **Schedule Management**: `staff/schedule.php`
- **Salary Information**: `staff/salary.php`

## How It Works

### Staff Account Creation
1. **Admin creates staff account** through `admin/register.php`
2. **System generates**:
   - Unique Staff ID (format: STAFF-YYYY-XXXX)
   - Hashed password
   - QR code data
   - Profile photo storage
3. **Staff receives** login credentials via admin

### Staff Login Process
1. **Staff visits** `staff/login.php`
2. **Enters** email and password
3. **System validates** credentials against staff table
4. **Creates session** with staff information
5. **Redirects to** staff dashboard

### Password Reset Flow
1. **Staff clicks** "Forgot password?" on login page
2. **Enters email** on forgot password page
3. **System generates** secure reset token
4. **Token stored** in database with 1-hour expiry
5. **Staff receives** reset link via email (when configured)
6. **Staff sets** new password using reset token
7. **Token cleared** after successful reset

## Security Features

### Authentication Security
- **Password Hashing**: Uses PHP's `password_hash()` function
- **Login Attempt Limiting**: 5 attempts before temporary lockout
- **Session Management**: Secure session handling
- **Remember Me**: Secure cookie-based authentication
- **Password Requirements**: Minimum 6 characters

### Data Protection
- **Prepared Statements**: SQL injection prevention
- **Input Validation**: Server-side validation
- **XSS Protection**: HTML escaping
- **CSRF Protection**: Form token validation (recommended)

## Database Structure

### Required Staff Table Fields
```sql
CREATE TABLE staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    position VARCHAR(100) NOT NULL,
    hire_date DATE NOT NULL,
    address TEXT,
    gender ENUM('Male', 'Female', 'Other'),
    photo VARCHAR(255),
    qr_code_data TEXT,
    reset_token VARCHAR(64),
    reset_token_expiry DATETIME,
    remember_token VARCHAR(64),
    token_expiry DATETIME,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Payroll Table (Optional)
```sql
CREATE TABLE payroll (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    salary DECIMAL(10,2),
    employment_type ENUM('full-time', 'part-time', 'contract') DEFAULT 'full-time',
    bank_name VARCHAR(100),
    account_number VARCHAR(50),
    tax_id VARCHAR(50),
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
);
```

## Setup Instructions

### 1. Database Setup
Run the SQL script to add required fields:
```bash
mysql -u username -p database_name < add_staff_reset_fields.sql
```

### 2. File Permissions
Ensure proper file permissions:
```bash
chmod 755 staff/
chmod 644 staff/*.php
chmod 755 logs/
chmod 666 logs/*.log
```

### 3. Email Configuration (Optional)
To enable email-based password reset:
1. Configure SMTP settings in your PHP environment
2. Update the forgot password functionality to send actual emails
3. Test email delivery

## Usage Examples

### Staff Login
```php
// Staff visits: staff/login.php
// Enters: email@example.com / password123
// System validates and creates session
```

### Admin Creates Staff
```php
// Admin visits: admin/register.php
// Fills staff registration form
// System generates: STAFF-2024-0001
// Staff can now login with created credentials
```

### Password Reset
```php
// Staff visits: staff/forgot-password.php
// Enters email address
// System generates reset token
// Staff receives reset link
// Staff sets new password
```

## Customization

### Styling
- **Color Scheme**: Green theme for staff (vs. Blue for members)
- **CSS Location**: `assets/css/admin/staff.css`
- **Responsive Design**: Mobile-friendly interface

### Branding
- **Logo**: Customizable in header sections
- **Company Name**: Update FIT_TRACK references
- **Colors**: Modify CSS variables in :root

## Troubleshooting

### Common Issues
1. **Login Fails**: Check if staff exists in database
2. **Password Reset Not Working**: Verify reset_token fields exist
3. **Session Issues**: Check session configuration
4. **Database Errors**: Verify table structure

### Debug Mode
Enable error reporting for development:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## Security Recommendations

### Additional Security Measures
1. **HTTPS**: Use SSL/TLS encryption
2. **Rate Limiting**: Implement API rate limiting
3. **Audit Logging**: Log all authentication attempts
4. **Two-Factor Authentication**: Consider adding 2FA
5. **Password Policy**: Enforce stronger password requirements

### Regular Maintenance
1. **Update Dependencies**: Keep PHP and libraries updated
2. **Security Audits**: Regular security reviews
3. **Backup Strategy**: Regular database backups
4. **Monitoring**: Monitor for suspicious activity

## Support

For technical support or questions about the staff system:
- Check the logs in `logs/` directory
- Review database structure
- Verify file permissions
- Test with sample data

---

**Version**: 2.0  
**Last Updated**: <?= date('Y-m-d') ?>  
**System**: FIT_TRACK Gym Management
