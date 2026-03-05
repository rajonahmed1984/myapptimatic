# Phase 5 Decision: `/cron/billing` stays Blade (non-portal operational endpoint)

Date: 2026-03-05

## Decision
- Keep `/cron/billing` server-rendered via `resources/views/cron/billing.blade.php`.

## Why
- This route is an operational endpoint for cron execution, not a portal UI screen.
- It is protected by `restrict.cron` middleware (`signed` URL + optional HMAC/IP checks) and a business token check in `CronController`.
- Converting this endpoint to Inertia/React adds frontend runtime dependencies without product value and increases operational risk.

## Safety checks added
- Added smoke tests in `tests/Feature/Smoke/CronBillingEndpointSmokeTest.php`:
  - Invalid business token with valid signature returns `403 Unauthorized`.
  - Valid signed request + valid token returns `200` success view and executes `billing:run`.

## Notes
- Blade remains the correct rendering mode for this endpoint under the migration rule:
  - Keep Blade for non-portal operational flows, PDFs, emails, and error pages.
