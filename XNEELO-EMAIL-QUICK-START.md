# XNEELO EMAIL SETUP - Quick Guide

For xneelo.com shared hosting, email setup is simple and requires NO SMTP configuration.

## 5-Minute Setup

### Step 1: Update config.php

Replace these lines in `config.php`:

```php
// Change these:
define('EMAIL_FROM_ADDRESS', 'bookings@dios.local');
define('EMAIL_ADMIN_ADDRESS', 'admin@dios.local');
define('SEND_CLIENT_EMAILS', false);
define('SEND_ADMIN_EMAILS', false);

// To these (use YOUR actual domain):
define('EMAIL_FROM_ADDRESS', 'bookings@yourdomain.com');
define('EMAIL_ADMIN_ADDRESS', 'admin@yourdomain.com');
define('SEND_CLIENT_EMAILS', true);
define('SEND_ADMIN_EMAILS', true);
```

### Step 2: That's It!

Xneelo automatically handles mail relay using PHP mail() function.

✅ No SMTP configuration needed  
✅ No passwords to set  
✅ Uses your domain's mail system  

### Step 3: Test

1. Go to booking page: `yoursite.com/booking.php`
2. Fill out form with a test email
3. Check inbox for confirmation
4. Look in spam/junk if not in inbox

## Troubleshooting

### Email Address Format

**MUST use valid email on your hosted domain:**

✅ Correct:
- bookings@yourdomain.com
- admin@yourdomain.com

❌ Wrong:
- bookings@dios.local
- bookings@gmail.com
- random@other-domain.com

### Email Not Arriving

1. **Check spam/junk folder**
   - Gmail, Yahoo, Outlook often filter first time

2. **Wait a few minutes**
   - Email may take 1-5 minutes to arrive

3. **Check domain is on xneelo**
   - If domain hosted elsewhere, won't work
   - Verify in xneelo control panel

4. **Check email enable in config.php**
   - Verify `SEND_CLIENT_EMAILS = true`
   - Verify `SEND_ADMIN_EMAILS = true`

### xneelo Mail Relay Issues

If emails still don't send:

1. **Contact xneelo Support** with:
   - Your domain name
   - Email error messages (check error logs)
   - Confirm mail relay is enabled

2. **Check xneelo Control Panel:**
   - Go to Email > Mail Relay
   - Should show your domain
   - Confirm no block/restrictions

3. **Check PHP mail() function works:**
   - Add temporary test file: `test-email.php`
   - Content below

### test-email.php (Temporary Test)

```php
<?php
$to = "your-email@test.com";
$subject = "DIOS Email Test";
$message = "If you receive this, email works!";
$headers = "From: bookings@yourdomain.com";

if (mail($to, $subject, $message, $headers)) {
    echo "Email sent successfully!";
} else {
    echo "Email failed to send.";
}
?>
```

## Email Types You'll Receive

### Client Emails

1. **Booking Confirmation** - After form submission
   - Appointment details
   - Deposit amount
   - Reference number

2. **Payment Confirmed** - After successful payment
   - Booking is paid
   - Ready for appointment

3. **Status Updates** - When admin changes status
   - Appointment confirmed
   - Appointment completed
   - etc.

4. **Reschedule Notice** - When admin moves appointment
   - Old vs new time
   - Request to confirm

5. **Cancellation Notice** - When booking cancelled
   - Cancellation reason
   - How to rebook

### Admin Emails

1. **New Booking Alert** - Each time client books
   - Client name & contact
   - Appointment details
   - Link to CRM

## SMTP Setup (Optional - NOT recommended for xneelo)

If PHP mail() doesn't work, xneelo also supports SMTP:

```php
define('EMAIL_USE_SMTP', true);
define('EMAIL_SMTP_HOST', 'mail.yourdomain.com');
define('EMAIL_SMTP_PORT', 25);
define('EMAIL_SMTP_USER', 'bookings@yourdomain.com');
define('EMAIL_SMTP_PASS', 'your-email-password');
```

But **PHP mail()** is simpler and faster for xneelo.

## Disable Emails (If Needed)

To temporarily disable email sending:

```php
define('SEND_CLIENT_EMAILS', false);
define('SEND_ADMIN_EMAILS', false);
```

## Files Modified

- config.php - Email settings
- mail-functions.php - Email functions
- booking.php - Booking confirmation
- success.php - Payment confirmation
- admin-functions.php - Admin action emails

## Support

**Before contacting support, check:**
1. ✓ Email address is on your domain
2. ✓ `SEND_CLIENT_EMAILS = true`
3. ✓ `SEND_ADMIN_EMAILS = true`
4. ✓ Checked spam/junk folder
5. ✓ Waited 5 minutes for email
6. ✓ Domain is hosted on xneelo

## Quick Reference

| Setting | Value | Notes |
|---------|-------|-------|
| EMAIL_USE_SMTP | false | PHP mail() for xneelo |
| EMAIL_FROM_ADDRESS | bookings@yourdomain.com | Must be on your domain |
| EMAIL_ADMIN_ADDRESS | admin@yourdomain.com | Must be on your domain |
| SEND_CLIENT_EMAILS | true | Send to clients |
| SEND_ADMIN_EMAILS | true | Send to staff |

---

**Status:** ✅ Ready for xneelo  
**Complexity:** Simple (1 setting to change)  
**Time:** 5 minutes  
**Cost:** Included with xneelo hosting
