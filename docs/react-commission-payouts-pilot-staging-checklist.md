# React Pilot Staging Checklist: Commission Payouts Index

## Scope
- Route: `GET /admin/commission-payouts` (`admin.commission-payouts.index`)
- Flag: `FEATURE_ADMIN_COMMISSION_PAYOUTS_INDEX`
- Fallback: Blade view remains default when flag is `false`

## Enable
1. Set staging env:
   - `FEATURE_ADMIN_COMMISSION_PAYOUTS_INDEX=true`
2. Rebuild caches:
   - `php artisan optimize:clear`
   - `php artisan config:cache`
   - `php artisan route:cache`

## Validation (ON/OFF parity)
1. Guard parity:
   - Master admin can access route (`200`) with flag OFF and ON.
   - Client role stays forbidden (`403`) with flag OFF and ON.
2. UI parity:
   - OFF returns Blade page.
   - ON returns Inertia page (`Admin/CommissionPayouts/Index`).
3. Data parity:
   - Payable-by-sales-rep cards keep counts and totals.
   - Payout history rows keep key fields, statuses, and show links.
   - Export and create links remain functional.
4. Non-regression:
   - Create/show/pay/reverse flows remain unchanged.

## Safety Gates
- `php artisan test --filter=CommissionPayoutUiParityTest`
- `php artisan test`
- `npm run build`
- `php artisan config:cache`
- `php artisan route:cache`

## Rollback
1. Set `FEATURE_ADMIN_COMMISSION_PAYOUTS_INDEX=false`
2. Run:
   - `php artisan optimize:clear`
   - `php artisan config:cache`
   - `php artisan route:cache`

## Exit Criteria
- No ON/OFF parity drift in status code, auth behavior, and index rendering.
- No regression in create/show/pay/reverse payout flows.
