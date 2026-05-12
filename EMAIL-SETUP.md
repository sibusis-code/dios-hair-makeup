# Email Notification System Setup

## Overview

The DIOS CRM now includes an automated email notification system that sends:

✅ **Client Emails:**
- Booking confirmation (when form submitted)
- Payment confirmation (when payment succeeds)
- Status updates (when admin changes status)
- Reschedule notifications (when appointment is moved)
- Cancellation notices (when booking is cancelled)

✅ **Admin Emails:**
- New booking alerts (when client books)

## Quick Setup

### Step 1: Update config.php

Add email settings to your environment (or set defaults in config.php):

```php
// In config.php, already included:
define('EMAIL_FROM_NAME', envOrDefault('EMAIL_FROM_NAME', 'DIOS Hair & Makeup'));
define('EMAIL_FROM_ADDRESS', envOrDefault('EMAIL_FROM_ADDRESS', 'bookings@dios.local'));
define('EMAIL_ADMIN_ADDRESS', envOrDefault('EMAIL_ADMIN_ADDRESS', 'admin@dios.local'));
define('SEND_CLIENT_EMAILS', envToBool('SEND_CLIENT_EMAILS', false));
define('SEND_ADMIN_EMAILS', envToBool('SEND_ADMIN_EMAILS', false));
```

### Step 2: Enable Emails

**Option 1: Direct in config.php** (Simplest for xneelo)

```php
define('SEND_CLIENT_EMAILS', true);    // Send to clients
define('SEND_ADMIN_EMAILS', true);     // Send to admin
define('EMAIL_FROM_ADDRESS', 'bookings@yourdomain.com');
define('EMAIL_ADMIN_ADDRESS', 'admin@yourdomain.com');
```

**Option 2: Via Environment Variables** (Better for production)

Create a `.env` file or set in hosting control panel:

```
SEND_CLIENT_EMAILS=1
SEND_ADMIN_EMAILS=1
EMAIL_FROM_NAME=DIOS Hair & Makeup
EMAIL_FROM_ADDRESS=bookings@dios.local
EMAIL_ADMIN_ADDRESS=admin@dios.local
EMAIL_USE_SMTP=0
```

### Step 3: Test Email Sending

1. Fill out a test booking form
2. Check both client and admin email inboxes
3. Look for confirmation emails

## Email Configuration Options

### Basic Settings (Recommended for xneelo)

```php
define('SEND_CLIENT_EMAILS', true);           // Emails to clients
define('SEND_ADMIN_EMAILS', true);            // Emails to staff
define('EMAIL_FROM_NAME', 'DIOS Hair & Makeup');
define('EMAIL_FROM_ADDRESS', 'bookings@yourdomain.com');
define('EMAIL_ADMIN_ADDRESS', 'admin@yourdomain.com');
define('EMAIL_USE_SMTP', false);              // Use PHP mail()
```

### SMTP Settings (Advanced)

For Gmail, Office365, or custom SMTP servers:

```php
define('EMAIL_USE_SMTP', true);
define('EMAIL_SMTP_HOST', 'smtp.gmail.com');
define('EMAIL_SMTP_PORT', 587);
define('EMAIL_SMTP_USER', 'your-email@gmail.com');
define('EMAIL_SMTP_PASS', 'your-app-password');  // Use app password, not regular password
```

## Email Types & Content

### 1. Booking Confirmation (Client)

**Sent:** When client submits booking form  
**Contains:** Appointment date, time, service, deposit amount, reference number  
**Requires:** `SEND_CLIENT_EMAILS = true`

### 2. Payment Confirmation (Client)

**Sent:** When PayFast payment succeeds  
**Contains:** Payment received notification, booking confirmed  
**Requires:** `SEND_CLIENT_EMAILS = true`

### 3. Status Update (Client)

**Sent:** When admin changes booking status (confirmed, paid, completed)  
**Contains:** Updated status and relevant message  
**Triggers:** Admin edits booking in CRM

### 4. Reschedule Notification (Client)

**Sent:** When admin reschedules appointment  
**Contains:** Old and new appointment times  
**Triggers:** Admin clicks "Reschedule"

### 5. Cancellation Notice (Client)

**Sent:** When booking is cancelled  
**Contains:** Cancellation reason and original appointment details  
**Triggers:** Admin cancels booking

### 6. New Booking Alert (Admin)

**Sent:** When client books  
**Contains:** Client info, appointment details, CRM link  
**Requires:** `SEND_ADMIN_EMAILS = true`

## Xneelo Hosting Setup

### For xneelo.com Shared Hosting:

1. **Use PHP mail() (Default):**
   - Set `EMAIL_USE_SMTP = false`
   - xneelo automatically handles mail relay
   - No additional configuration needed

2. **Email Address Requirements:**
   - Use a valid email address on your domain
   - Format: `bookings@yourdomain.com`
   - Domain must be hosted on same xneelo account

3. **Update config.php:**

```php
define('SEND_CLIENT_EMAILS', true);
define('SEND_ADMIN_EMAILS', true);
define('EMAIL_FROM_ADDRESS', 'bookings@yourdomain.com');
define('EMAIL_ADMIN_ADDRESS', 'admin@yourdomain.com');
define('EMAIL_USE_SMTP', false);
```

### Troubleshooting xneelo Emails:

**Emails not sending?**
- Check `/mail-functions.php` line 50 (sendEmail function)
- Verify `EMAIL_FROM_ADDRESS` uses valid domain email
- Check spam folder for test emails
- Contact xneelo support if mail relay is blocked

**Spam issues?**
- Set up SPF/DKIM records in xneelo control panel
- Use clear "From" name and address
- Avoid email list recipients

## Gmail Setup (if using SMTP)

1. **Enable 2FA** on Gmail account
2. **Create App Password:**
   - Go to myaccount.google.com/apppasswords
   - Select "Mail" and "Windows Computer"
   - Copy 16-character password

3. **Update config.php:**

```php
define('EMAIL_USE_SMTP', true);
define('EMAIL_SMTP_HOST', 'smtp.gmail.com');
define('EMAIL_SMTP_PORT', 587);
define('EMAIL_SMTP_USER', 'your-email@gmail.com');
define('EMAIL_SMTP_PASS', 'xxxx xxxx xxxx xxxx');  // 16-char app password
```

## Office 365 Setup (if using SMTP)

```php
define('EMAIL_USE_SMTP', true);
define('EMAIL_SMTP_HOST', 'smtp.office365.com');
define('EMAIL_SMTP_PORT', 587);
define('EMAIL_SMTP_USER', 'your-email@company.com');
define('EMAIL_SMTP_PASS', 'your-password');
```

## Files Involved

- **mail-functions.php** - Email sending functions
- **config.php** - Email configuration settings
- **booking.php** - Sends confirmation on form submit
- **success.php** - Sends payment confirmation
- **admin-functions.php** - Sends status/reschedule/cancel emails

## Disable Emails

To temporarily disable email sending:

```php
define('SEND_CLIENT_EMAILS', false);
define('SEND_ADMIN_EMAILS', false);
```

## Email Templates

All email templates are embedded in `mail-functions.php`:

- `sendBookingConfirmation()` - Line ~30
- `sendStatusUpdateEmail()` - Line ~120
- `sendCancellationEmail()` - Line ~200
- `sendAdminNewBookingNotification()` - Line ~280
- `sendRescheduleEmail()` - Line ~360

## Customizing Email Content

To change email template text:

1. Open `mail-functions.php`
2. Find function: `sendBookingConfirmation()`, etc.
3. Edit HTML in the `$htmlBody` variable
4. Edit plain text in the `$plainBody` variable
5. Save and test

Example:

```php
function sendBookingConfirmation(array $booking): bool
{
    // ... code ...
    $htmlBody = <<<HTML
    <!DOCTYPE html>
    <html>
    <head>
        <!-- Your custom HTML here -->
    </head>
    <body>
        <!-- Edit your email template here -->
    </body>
    </html>
    HTML;
```

## Common Issues & Fixes

### "Undefined constant SEND_CLIENT_EMAILS"

**Solution:** Check config.php has email constants defined (already done)

### Emails not sending to test account

**Solution:**
- Check spam/junk folder
- Verify `SEND_CLIENT_EMAILS = true`
- Check email address is correct
- Test with simple booking

### Mail relay error on xneelo

**Solution:**
- Use email on your domain only
- Check xneelo control panel if mail is blocked
- Verify no SMTP settings when using PHP mail()

### SMTP connection failed

**Solution:**
- Verify SMTP credentials are correct
- Check port is 587 (not 25, 465, or 25)
- For Gmail: use app password, not regular password
- Test with simpler email provider first

## Performance Notes

- Email sending is done during form submission (may add 1-2 seconds)
- Emails are synchronous (page waits for send)
- Failed emails log to PHP error log, don't block booking
- For high volume, consider async email queue (future enhancement)

## Security Best Practices

✅ **DO:**
- Use domain email addresses (bookings@yourdomain.com)
- Store passwords in environment variables
- Use SMTP for sensitive operations
- Keep email addresses out of logs

❌ **DON'T:**
- Hard-code SMTP passwords in files
- Use personal Gmail for business emails
- Send to external email lists
- Store customer emails in plain text logs

## Testing

### Manual Test:

1. Disable payment: Skip PayFast and go straight to success.php for testing
2. Fill booking form with test email
3. Check your email for confirmation
4. Edit booking in admin and check for status update email

### Check Logs:

PHP mail errors appear in:
- Server error logs (check hosting control panel)
- WordPress debug log (if applicable)
- Application error_log if set up

## Next Steps

1. Set email addresses in config.php
2. Enable emails: `SEND_CLIENT_EMAILS = true`
3. Submit test booking
4. Verify email received
5. Test admin status changes
6. Go live!

## Support

If emails aren't working:
1. Check config.php settings
2. Verify email address is valid for domain
3. Test with simple text email first
4. Check hosting mail relay status
5. Review error logs
6. Contact hosting support with error messages

---

**Files:** mail-functions.php, config.php, booking.php, success.php, admin-functions.php  
**Status:** ✅ Ready to use  
**Last Updated:** May 2026
