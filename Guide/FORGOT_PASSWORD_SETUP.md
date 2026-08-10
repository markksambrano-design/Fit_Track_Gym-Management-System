# FIT_TRACK Forgot Password System Setup

This document explains how to set up and use the forgot password functionality for the FIT_TRACK member portal.

## Files Created

1. **`member/forgot-password.php`** - Page where members enter their email to request password reset
2. **`member/reset-password.php`** - Page where members enter their new password using the reset token
3. **`includes/email_helper.php`** - Helper functions for sending password reset emails
4. **`add_password_reset_fields.sql`** - SQL script to add required database fields

## Database Setup

Before using the forgot password system, you need to add the required fields to your `members` table:

```sql
-- Run this SQL script in your database
ALTER TABLE members 
ADD COLUMN reset_token VARCHAR(64) NULL AFTER remember_token,
ADD COLUMN reset_token_expiry DATETIME NULL AFTER reset_token;

-- Add index for better performance
ALTER TABLE members 
ADD INDEX idx_reset_token (reset_token);
```

## How It Works

### 1. Password Reset Request
- Member goes to `forgot-password.php`
- Enters their email address
- System generates a secure random token
- Token is stored in database with 1-hour expiry
- Password reset email is sent to member

### 2. Password Reset
- Member clicks link in email (goes to `reset-password.php?token=...`)
- System validates the token and expiry
- Member enters new password and confirms it
- Password is updated and reset token is cleared
- Member can now login with new password

## Email Configuration

### Development Mode (Default)
- Emails are logged to `logs/password_reset_emails.log` instead of being sent
- This is useful for testing without setting up email servers
- To enable actual email sending, change `$development_mode = false` in `email_helper.php`

### Production Mode
- Set `$development_mode = false` in `email_helper.php`
- Ensure your server has proper email configuration
- Consider using services like SendGrid, Mailgun, or PHPMailer for reliable email delivery

## Security Features

- **Token Expiry**: Reset tokens expire after 1 hour
- **Secure Tokens**: Uses `random_bytes(32)` for cryptographically secure tokens
- **Password Hashing**: New passwords are hashed using `password_hash()`
- **Token Cleanup**: Reset tokens are cleared after successful password reset
- **Rate Limiting**: Can be easily extended with login attempt tracking

## Customization

### Email Template
- Edit the HTML template in `sendPasswordResetEmail()` function
- Modify colors, branding, and content as needed
- Update sender information and reply-to addresses

### Token Expiry
- Change the expiry time in `forgot-password.php` (currently 3600 seconds = 1 hour)
- Update the email message to reflect the new expiry time

### Password Requirements
- Modify password validation in `reset-password.php`
- Currently requires minimum 6 characters
- Can add complexity requirements (uppercase, numbers, symbols)

## Testing

1. **Database Setup**: Run the SQL script to add reset fields
2. **Request Reset**: Go to forgot password page and enter a member's email
3. **Check Logs**: Verify the reset link is logged in `logs/password_reset_emails.log`
4. **Test Reset**: Use the logged link to test the password reset process
5. **Verify Login**: Confirm the member can login with the new password

## Troubleshooting

### Common Issues

1. **"Invalid or expired reset token"**
   - Token may have expired (check expiry time)
   - Token may not exist in database
   - Check database connection and table structure

2. **Email not received**
   - Check if in development mode (emails are logged, not sent)
   - Verify email server configuration
   - Check server error logs

3. **Database errors**
   - Ensure `reset_token` and `reset_token_expiry` fields exist
   - Check database permissions
   - Verify table structure

### Debug Mode

To enable debug mode, add this to your PHP files:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## Integration with Existing System

The forgot password system integrates seamlessly with the existing FIT_TRACK member login system:

- Uses the same database connection (`../includes/db.php`)
- Follows the same styling and design patterns
- Maintains session security and redirects
- Compatible with existing member authentication flow

## Future Enhancements

- **SMS Integration**: Add SMS-based password reset
- **Security Questions**: Implement security questions for additional verification
- **Audit Logging**: Track all password reset attempts
- **Admin Notifications**: Notify admins of password reset requests
- **Rate Limiting**: Prevent abuse of password reset functionality
