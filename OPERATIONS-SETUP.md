# Operations Setup

## 1) Health check

Endpoint: `health-check.php`

- Optional protection token via `.env`:

```env
HEALTHCHECK_TOKEN=your-long-random-token
```

- If token is set, call:

`https://your-domain/health-check.php?token=your-long-random-token`

- Return codes:
  - `200` = healthy
  - `503` = degraded (one or more checks failed)

## 2) Cleanup stale payment attempts

Script: `cleanup-stale-attempts.php`

- Optional protection token for web-trigger:

```env
CLEANUP_TOKEN=another-long-random-token
STALE_ATTEMPTS_HOURS=24
```

- Dry run (web):

`https://your-domain/cleanup-stale-attempts.php?token=...&dry_run=1`

- Execute delete (web):

`https://your-domain/cleanup-stale-attempts.php?token=...`

- CLI execution:

```bash
php cleanup-stale-attempts.php --dry_run=1
php cleanup-stale-attempts.php
php cleanup-stale-attempts.php --hours=48
```

## 3) Cron (recommended)

Run hourly or daily via hosting cron:

```bash
php /home/your-account/public_html/cleanup-stale-attempts.php
```

## 4) Admin settings UI

Page: `admin-settings.php`

Use this page to update:
- Business info
- Services
- Time slots
- Stylists
- Service-location-stylist mappings
- Service-slot restrictions

No code edits are needed for these updates.

## 5) DB indexes

Run once:

`db-maintenance-indexes.sql`

This adds maintenance and mapping indexes for better performance.
