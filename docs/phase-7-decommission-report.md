# Phase 7 Decommission Report (Batch 1)

Date: 2026-02-23
Status: Completed for selected stable modules
Scope: Remove Blade fallback for admin chats, payment gateways, and payment proofs index pages

## Decommissioned Routes

- `admin.chats.index`
- `admin.payment-gateways.index`
- `admin.payment-proofs.index`

## What Changed

- Route middleware moved to Inertia-only for the three routes.
- Controllers now render Inertia directly (no `HybridUiResponder` fallback).
- Legacy feature keys removed:
  - `admin_chats_index`
  - `admin_payment_gateways_index`
  - `admin_payment_proofs_index`
- Legacy Blade views removed:
  - `resources/views/admin/chats/index.blade.php`
  - `resources/views/admin/payment-gateways/index.blade.php`
  - `resources/views/admin/payment-proofs/index.blade.php`
  - `resources/views/admin/payment-proofs/partials/table.blade.php`

## Parity and Safety

- Existing route names and URLs unchanged.
- Auth, guard, middleware, permissions unchanged.
- Action endpoints unchanged:
  - payment-proof approve/reject/receipt
  - payment-gateway edit/update
- SSE/PDF/upload/payment transport tests remain green.

## Verification

- `composer phase7:verify`

Latest run (2026-02-23): PASS

- Targeted decommission checks: PASS
- `npm run build`: PASS
- `php artisan config:cache`: PASS
- `php artisan route:cache`: PASS
- Full suite in same run: 399 passed, 1582 assertions

## Rollback

If any regression appears:

1. Revert the Phase 7 commit.
2. Run:
   - `php artisan optimize:clear`
   - `php artisan config:cache`
   - `php artisan route:cache`
