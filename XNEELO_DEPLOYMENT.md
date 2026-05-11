# Xneelo Dev Deployment Checklist

Use this checklist to upload and test the DIOS booking site on your xneelo development environment.

## 1) Prepare files

- Keep the current project folder structure unchanged.
- Upload all files and folders to your web root (`public_html`) or your chosen subfolder.
- If you use a subfolder, keep everything together in that subfolder.

## 2) Create the database

- In xneelo cPanel, create a MySQL database.
- Create a MySQL user and assign all privileges to that database.
- Import `salon_bookings.sql` into the database using phpMyAdmin.

## 3) Configure runtime values

`config.php` now supports environment variables and **per-service pricing**.

### Environment Variables

Set these values in your hosting environment if supported:

- `DB_HOST`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`
- `PAYFAST_MERCHANT_ID`
- `PAYFAST_MERCHANT_KEY`
- `PAYFAST_SANDBOX` (`true` for test, `false` for live)
- `PAYFAST_PASSPHRASE` (if used in PayFast)
- `SITE_URL` (optional)
- `PAYFAST_NOTIFY_URL_OVERRIDE` (optional but recommended)

### Per-Service Deposit Pricing

Deposit amounts are now calculated **per service**, not a fixed global amount. The booking form displays the service-specific deposit as soon as the customer selects their service.

**Default Service Prices:**
- Braids: R800.00
- Cornrows: R600.00
- Ponytail: R400.00
- Wig Installation: R750.00
- Hair Colour: R500.00
- Other Hair Styling: R500.00
- Makeup Artistry: R400.00
- Mobile Service: R200.00
- Other (unspecified): R500.00

**To customize service prices:** Edit `config.php` and modify the `getServicePriceMap()` function array values to match your pricing. All prices are in Rand (ZAR) and stored as DECIMAL(10,2).

**Note:** The global `BOOKING_DEPOSIT_AMOUNT` environment variable now serves as a fallback for services not in the price map. It defaults to R500.00.

If your environment does not support custom variables, set the values directly in `config.php`.

## 4) PayFast sandbox testing setup

- Keep `PAYFAST_SANDBOX=true` while testing.
- Ensure return/cancel/notify URLs point to your uploaded site URLs.
- Notify URL must be public HTTPS and reachable from PayFast.

## 5) Quick verification tests

- Open the home page and booking page.
- Submit a booking and proceed to PayFast sandbox.
- Complete a sandbox payment.
- Confirm you return to `success.php`.
- Confirm ITN calls `itn.php` and booking status becomes `paid` in DB.

## 6) Before production

- Switch `PAYFAST_SANDBOX=false`.
- Use live PayFast merchant credentials.
- Set `PAYFAST_NOTIFY_URL_OVERRIDE` to your live HTTPS ITN URL.
- Rotate any credentials that were exposed during development.
