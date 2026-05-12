# DIOS CRM - Quick Reference Guide

## Login
🔐 **URL:** `yoursite.com/admin-login.php`

**Default Account:**
- Username: `admin`
- Password: `admin123` (change immediately!)

## Dashboard Navigation

### Left Sidebar Menu
- **Dashboard** - Main overview & stats
- **Pending** - Quick filter to pending bookings
- **Confirmed** - Quick filter to confirmed bookings
- **Paid** - Quick filter to paid bookings
- **Completed** - Quick filter to completed bookings
- **Cancelled** - Quick filter to cancelled bookings
- **Manage Bookings** - View all bookings in one table
- **Logout** - Exit CRM

## Quick Actions

### View Booking Details
1. From Dashboard, click **"View"** on any booking
2. See full client info, appointment details, payment ID
3. View all internal notes & history

### Change Booking Status
1. Click booking "View" button
2. Click **"Edit Booking"**
3. Select new status from dropdown
4. Assign stylist if needed
5. Click **"Save Changes"**

**Status Flow:**
- `pending` → `confirmed` (client confirmed)
- `confirmed` → `paid` (payment received)
- `paid` → `completed` (service done)
- Any status → `cancelled` (with reason)

### Reschedule Appointment
1. Open booking detail
2. Click **"Reschedule"**
3. Select new date (must be future)
4. Select new time (8 AM - 5 PM slots)
5. Click **"Reschedule"**

### Cancel Booking
1. Open booking detail
2. Click **"Cancel Booking"**
3. Enter reason for cancellation
4. Click **"Confirm Cancellation"**

### Add Internal Notes
1. Open booking detail
2. Scroll to "Notes & History"
3. Type note in text area
4. Click **"Add Note"**
5. Note appears with your name & timestamp

### Assign Stylist
1. Open booking detail
2. Click **"Edit Booking"**
3. Select stylist from dropdown
4. Click **"Save Changes"**

## Dashboard Stats

**Top Right Corner Cards:**
- 📊 **Total Bookings** - All bookings ever
- ⏳ **Pending Confirmation** - Need confirmation/not paid
- 📅 **Today's Bookings** - Scheduled for today
- 💰 **Total Revenue** - All paid deposits

## Filtering Bookings

**On Dashboard:**
1. Use dropdown **"Filter by Status"**
2. Select: All, Pending, Confirmed, Paid, Completed, Cancelled
3. Table updates instantly

## Keyboard Shortcuts

None configured yet - all actions use buttons/menus.

## Keyboard Tips

- **Tab** - Navigate form fields
- **Enter** - Submit forms
- **ESC** - Close confirmation dialogs (careful!)

## Common Issues & Fixes

### "Booking not found"
- Booking ID may be wrong
- Booking may be deleted
- Check dashboard for correct booking

### "Session expired"
- Re-login at `/admin-login.php`
- Session times out after 1 hour of inactivity

### "Can't reschedule"
- Time slot may be taken
- Try different time
- Check dashboard for conflicting bookings

### "Password doesn't work"
- Check CAPS LOCK
- Default: `admin123`
- Admin must change this on first login
- Contact admin to reset

## Tips & Best Practices

✅ **DO:**
- Add notes for every booking change
- Assign stylists to bookings for scheduling
- Update status when client confirms
- Mark as paid when payment received
- Cancel with reason for tracking

❌ **DON'T:**
- Share login credentials
- Leave browser open unattended
- Delete bookings (use Cancel instead)
- Edit client email/phone (contact admin)
- Log in from public WiFi (use VPN)

## Daily Checklist

- ✓ Check "Today's Bookings" count
- ✓ Confirm any new pending bookings
- ✓ Update statuses as clients arrive
- ✓ Mark completed services as "completed"
- ✓ Add notes for any special requests
- ✓ Check for cancelled/rescheduled appointments

## Client Communication

When cancelling a booking, use clear reasons:
- "Client requested cancellation"
- "Double booking - rescheduled to [date]"
- "Business closure - offer rescheduling"
- "No-show - contact client to reschedule"

## Need Help?

📧 Contact: [admin email]
📞 Phone: [support number]
⏰ Hours: [business hours]

---

**Last Updated:** May 2026
**Version:** DIOS CRM 1.0
