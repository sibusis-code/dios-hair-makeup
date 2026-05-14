# 📤 UPLOAD CHECKLIST — What to Upload to Server

## CRITICAL: Order of Operations

### **Step 1: SQL Migration (MUST DO FIRST!)**
**File:** `MIGRATION-add-booking-details.sql`
- Open cPanel → phpMyAdmin
- Select `dios_hair` database
- Click "SQL" tab at top
- Copy/paste entire contents of this file
- Click "Go"
- Wait for success message

⚠️ **This must run before testing new bookings!**

---

### **Step 2: Upload These 3 PHP Files**
Via FTP or cPanel File Manager, upload to root directory:

#### ✅ **itn.php** (MODIFIED)
- **What changed:** Now captures ALL booking details (service, location, stylist, sub_type, hair_length, notes) when payment is confirmed
- **Size:** ~14 KB
- **Upload to:** `/public_html/itn.php`

#### ✅ **booking.php** (MODIFIED)  
- **What changed:** Enhanced validation + sanitization (prevents injection, validates phone/email, limits input length)
- **Size:** ~22 KB
- **Upload to:** `/public_html/booking.php`

#### ✅ **admin-booking-detail.php** (COMPLETELY REWRITTEN)
- **What changed:** New professional layout showing ALL booking info + "Share with Staff" button for print-friendly staff brief
- **Size:** ~27 KB  
- **Upload to:** `/public_html/admin-booking-detail.php`

---

### **Step 3: Reference/Documentation (Optional Upload)**
These don't need to go on server, keep locally:

- `MIGRATION-add-booking-details.sql` — Backup this locally
- `DEPLOYMENT-GUIDE.md` — Read this carefully before uploading
- `admin-functions.php` — NO CHANGE needed (but referenced in DEPLOYMENT-GUIDE)
- `admin-dashboard.php` — NO CHANGE needed

---

## 📋 Upload Summary Table

| File | Status | Action | Size |
|------|--------|--------|------|
| `MIGRATION-add-booking-details.sql` | 🆕 NEW | Run in phpMyAdmin | N/A |
| `itn.php` | 🔄 MODIFIED | Upload via FTP | ~14 KB |
| `booking.php` | 🔄 MODIFIED | Upload via FTP | ~22 KB |
| `admin-booking-detail.php` | 🔄 MODIFIED | Upload via FTP | ~27 KB |
| All other files | ⏸️ NO CHANGE | Do nothing | — |

---

## 🚀 Exact Upload Steps

### **Via cPanel File Manager (Easiest):**
1. Log into cPanel → File Manager
2. Navigate to `/public_html/`
3. Click "Upload" button
4. Select and upload these 3 files in ANY order:
   - `itn.php`
   - `booking.php`
   - `admin-booking-detail.php`
5. Verify files appear in file list with correct sizes

### **Via FTP (If you prefer):**
1. Open FTP client (FileZilla, WinSCP, etc.)
2. Connect to your server
3. Navigate to `/public_html/`
4. Drag & drop these 3 files:
   - `itn.php`
   - `booking.php`
   - `admin-booking-detail.php`

---

## ✅ Verification After Upload

### **1. Check Files Exist:**
```
Browser: https://development.mplai.co.za/
Look for: Booking form, admin dashboard still load without errors
```

### **2. Test Booking Form:**
- Go to: https://development.mplai.co.za/booking.php
- Try submitting incomplete form → should get validation error ✓
- Try entering number in name field → should reject ✓
- Try past date → should reject ✓

### **3. Test Admin Interface:**
- Go to: https://development.mplai.co.za/admin-login.php
- Login with admin credentials
- Go to: Dashboard → View a booking
- Look for: "Share with Staff" button in top right ✓
- Look for: All fields populated (Service, Location, Preferred Stylist, Special Instructions) ✓

### **4. Test Staff Share:**
- Click "Share with Staff" button
- New tab opens with print-friendly view ✓
- Try Ctrl+P / Cmd+P to print → works? ✓

---

## 🔧 If Something Goes Wrong

### **Error: "Booking not found"**
- Check that `admin-booking-detail.php` uploaded correctly
- Reload page with Ctrl+Shift+R (hard refresh)

### **Error: "Payment processing failed"**
- Check that `itn.php` uploaded correctly
- Verify PayFast settings in `.env` file on server

### **Error: Form validation not working**
- Check that `booking.php` uploaded correctly
- Check browser console for JavaScript errors (F12)

### **New bookings don't show service/location**
- **Critical:** Did you run the SQL migration?
- Check phpMyAdmin → salon_bookings → structure
- Columns should show: `service`, `location`, `sub_type`, `hair_length`, `client_notes`, `preferred_stylist`, `pf_payment_id`

---

## 📝 Client Testing Recommendation

After upload, create a test booking to verify:
1. Complete the booking form
2. Pay with PayFast sandbox
3. Go to admin dashboard
4. Find the booking
5. **Verify ALL these fields show data:**
   - ✓ Service name
   - ✓ Location  
   - ✓ Preferred Stylist
   - ✓ Special Instructions (if you entered any)
   - ✓ Date/Time
   - ✓ Payment status
   - ✓ Deposit amount

---

## 💾 Backup Recommendation

Before uploading, keep backups of these files locally:
- `itn.php` (old version)
- `booking.php` (old version)  
- `admin-booking-detail.php` (old version)

This way if you need to revert, you have the originals.

---

## 🎯 What You Should Test After Deployment

| Action | Expected Result |
|--------|-----------------|
| View dashboard | See TODAY's bookings with count badges |
| Click on booking | See all service details displayed |
| Click "Share with Staff" | Print-friendly view opens in new tab |
| Fill booking form incompletely | Get validation error (don't allow submit) |
| Enter invalid phone | Form rejects it |
| Try to book past date | Form rejects it |
| Complete booking & pay | New booking appears in admin dashboard with all data |

---

## ✨ Done!

Once all files are uploaded and migration runs successfully, your system will:

✅ Capture ALL client booking information
✅ Validate and sanitize all inputs securely
✅ Display complete information to admin
✅ Allow one-click staff sharing
✅ Show what needs to be done, where, and how
✅ Operate like a professional smart CRM system

**Your salon booking system is now production-ready and security hardened.**
