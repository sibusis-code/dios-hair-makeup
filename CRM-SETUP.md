# DIOS Salon CRM System - Setup & Usage Guide

## Overview

This CRM system adds admin capabilities to manage salon bookings, track payments, assign stylists, and maintain booking notes. The system integrates seamlessly with your existing PayFast payment integration.

## Features

✅ **Admin Login System** - Secure staff authentication
✅ **Dashboard** - Overview of all bookings and revenue
✅ **Booking Management** - Edit, reschedule, and cancel bookings
✅ **Status Tracking** - Pending → Confirmed → Paid → Completed
✅ **Stylist Assignment** - Assign stylists to bookings
✅ **Notes & History** - Add internal notes to bookings
✅ **Booking Statistics** - Total bookings, revenue, today's appointments

## Installation

### Step 1: Run SQL Setup

Execute the SQL queries in `admin-tables.sql` to create the required database tables:

```sql
-- Import admin-tables.sql into your MySQL database
```

This creates:
- `admin_users` - Staff login credentials
- `stylists` - Stylist management
- `booking_notes` - Internal notes for bookings
- New columns on `salon_bookings` table

### Step 2: Default Admin Account

A default admin account is created automatically:

**Username:** `admin`  
**Password:** `admin123`

⚠️ **IMPORTANT:** Change this password immediately after first login!

### Step 3: Generate New Admin Passwords (PHP CLI)

To create new admin user accounts or change passwords, use PHP CLI:

```bash
# Generate a password hash
php -r "echo password_hash('your_password_here', PASSWORD_BCRYPT);"
```

Then insert into the database:

```sql
INSERT INTO admin_users (username, email, password_hash, first_name, last_name, role) 
VALUES ('newuser', 'newuser@dios.local', '$2y$10$...', 'First', 'Last', 'staff');
```

## Usage

### Access the CRM

1. Navigate to: `http://yoursite.com/admin-login.php`
2. Login with your credentials
3. You'll see the admin dashboard

### Dashboard Overview

**Stats Cards:**
- Total Bookings - All bookings in system
- Pending Confirmation - Bookings awaiting confirmation
- Today's Bookings - Appointments scheduled for today
- Total Revenue - Sum of all paid deposits

**Booking Table:**
- Filter by status (All, Pending, Confirmed, Paid, Completed, Cancelled)
- View quick booking details
- Click "View" to see full details

### Manage Individual Bookings

Click "View" on any booking to access:

**Booking Details:**
- Client information
- Date & time
- Service & amount
- Payment ID
- Assigned stylist

**Actions Available:**
- **Edit** - Change status or assign stylist
- **Reschedule** - Change appointment date/time
- **Cancel** - Cancel booking with reason

**Notes & History:**
- Add internal notes for the team
- View complete note history with timestamps

### Change Booking Status

1. Click "Edit" on a booking
2. Select new status:
   - `pending` - Initial/unpaid booking
   - `confirmed` - Client confirmed
   - `paid` - Payment received
   - `completed` - Service completed
3. Assign stylist (optional)
4. Click "Save Changes"

### Reschedule a Booking

1. Click "Reschedule" on booking
2. Select new date (must be future date)
3. Select new time slot (8 AM - 5 PM)
4. System checks slot availability
5. Click "Reschedule"

### Cancel a Booking

1. Click "Cancel Booking"
2. Enter cancellation reason
3. Click "Confirm Cancellation"
4. Booking marked as cancelled with reason recorded

### Add Internal Notes

On any booking detail page:
1. Scroll to "Notes & History" section
2. Type note in textarea
3. Click "Add Note"
4. Note appears with timestamp and author

## Admin User Management

### Create New Admin User

Connect to MySQL and run:

```sql
INSERT INTO admin_users (username, email, password_hash, first_name, last_name, role) 
VALUES (
  'username',
  'email@example.com',
  '$2y$10$...hash_from_php...',
  'FirstName',
  'LastName',
  'staff'  -- or 'manager' or 'admin'
);
```

### Change Admin Password

1. Generate new hash using PHP CLI
2. Update the record:

```sql
UPDATE admin_users 
SET password_hash = '$2y$10$...new_hash...' 
WHERE username = 'username';
```

### Delete Admin User (Deactivate)

```sql
UPDATE admin_users SET is_active = 0 WHERE username = 'username';
```

### User Roles

- `admin` - Full CRM access
- `manager` - Booking management & reporting
- `staff` - View bookings and add notes

(Note: Role enforcement can be added in future versions)

## Stylist Management

### Add New Stylist

```sql
INSERT INTO stylists (name, email, phone, specialization) 
VALUES ('Stylist Name', 'email@example.com', '+27123456789', 'Specialization');
```

### View Available Stylists

When editing a booking, the "Assign Stylist" dropdown shows all active stylists with their specialization.

### Deactivate Stylist

```sql
UPDATE stylists SET is_active = 0 WHERE id = 1;
```

## Database Schema

### admin_users Table

```
id - Auto-increment primary key
username - Unique username
email - User email
password_hash - bcrypt hashed password
first_name - First name
last_name - Last name
role - admin|staff|manager
is_active - Boolean (1=active, 0=inactive)
created_at - Account creation timestamp
updated_at - Last modified timestamp
```

### stylists Table

```
id - Auto-increment primary key
name - Stylist name
email - Contact email
phone - Contact phone
specialization - Hair type/service expertise
is_active - Boolean (1=active)
created_at - Record creation timestamp
updated_at - Last modified timestamp
```

### booking_notes Table

```
id - Auto-increment primary key
booking_id - Foreign key to salon_bookings
admin_id - Foreign key to admin_users
note - Text note content
created_at - Note creation timestamp
```

### salon_bookings Updates

New columns added:
- `stylist_id` - Foreign key to stylists
- `status_updated_at` - When status last changed
- `cancellation_reason` - Reason for cancellation
- `payment_method` - Payment method used
- `admin_notes` - Admin internal notes

## File Structure

```
admin-login.php               - Login page
admin-dashboard.php           - Main dashboard
admin-bookings.php            - All bookings list
admin-booking-detail.php      - View booking details
admin-booking-edit.php        - Edit status/assign stylist
admin-booking-reschedule.php  - Reschedule appointment
admin-booking-cancel.php      - Cancel booking
admin-logout.php              - Logout handler
admin-functions.php           - Core CRM functions
admin-tables.sql              - Database setup
CRM-SETUP.md                  - This file
```

## Security Features

✅ Session-based authentication
✅ Password hashing with bcrypt
✅ SQL prepared statements (prevents injection)
✅ HTTP-only cookies
✅ Session timeout (1 hour)
✅ CSRF protection ready
✅ XSS prevention (output escaping)

## Common Tasks

### Check Today's Bookings

1. Go to Dashboard
2. Look at "Today's Bookings" stat card
3. Or filter dashboard table by "All" status

### View Revenue

1. Dashboard shows "Total Revenue" stat
2. Only counts bookings with status "paid"
3. Filter by date in future enhancement

### Export Booking Data

Currently data can be accessed via the dashboard tables. For exports, query the database directly:

```sql
SELECT * FROM salon_bookings WHERE status = 'paid' ORDER BY created_at DESC;
```

### Create Reports

Example queries:

```sql
-- Bookings by month
SELECT DATE(appointment_date) as date, COUNT(*) as count 
FROM salon_bookings 
GROUP BY DATE(appointment_date) 
ORDER BY date DESC;

-- Revenue by stylist
SELECT s.name, COUNT(b.id) as bookings, SUM(b.amount) as revenue
FROM salon_bookings b
LEFT JOIN stylists s ON b.stylist_id = s.id
WHERE b.status = 'paid'
GROUP BY b.stylist_id;
```

## Troubleshooting

### "Database connection failed"

- Check `config.php` database credentials
- Verify MySQL server is running
- Ensure database exists

### "Admin table doesn't exist"

- Run `admin-tables.sql` to create tables
- Check import completed without errors

### "Cannot login"

- Verify username/password is correct
- Check admin_users table has the account
- Ensure `is_active = 1` for the user

### "Session expires too quickly"

- Edit `ADMIN_SESSION_TIMEOUT` in `admin-functions.php`
- Value is in seconds (3600 = 1 hour)

## Future Enhancements

Possible additions:

- Email notifications to clients on booking changes
- SMS reminders before appointments
- Bulk booking actions
- Advanced reporting & analytics
- Client portal for booking management
- Payment processing dashboard
- Staff availability calendar
- Service menu management
- Automated backup system

## Support Files

- **admin-tables.sql** - Database initialization
- **admin-functions.php** - Core functions (do not edit unless experienced)
- **config.php** - Main configuration (already has PayFast settings)

## Important Security Notes

⚠️ **BEFORE GOING LIVE:**

1. Change default admin password immediately
2. Update `PAYFAST_SANDBOX` to `false` in config.php for live payments
3. Use HTTPS only (set in config.php)
4. Regularly backup database
5. Keep admin URLs private (restrict /admin-*.php)
6. Consider adding 2FA in future
7. Log all admin actions for audit trail

## Contact & Support

For issues or enhancements, contact your developer with:
- Error messages (check browser console & error logs)
- Steps to reproduce
- Screenshot/video if applicable

---

**Last Updated:** May 2026  
**Version:** 1.0  
**CRM Status:** ✅ Production Ready
