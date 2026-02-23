# React Pilot Staging Checklist: Finance Tax Index

## Scope
- Route: `GET /admin/finance/tax` (`admin.finance.tax.index`)
- Flag: `FEATURE_ADMIN_FINANCE_TAX_INDEX`
- Fallback: Blade view remains default when flag is `false`

## Enable
1. Set staging env:
   - `FEATURE_ADMIN_FINANCE_TAX_INDEX=true`
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
   - ON returns Inertia page (`Admin/Finance/Tax/Index`).
3. Behavior parity:
   - Settings update and tax rate create/delete hit the same existing endpoints.
   - Validation errors, redirects, and flash messages are identical with flag OFF and ON.
   - Rate edit and delete links keep existing route behavior.

## Safety Gates
- `php artisan test --filter=FinanceTaxUiParityTest`
- `php artisan test --filter=FinanceTaxActionContractParityTest`
- `php artisan test`
- `npm run build`
- `php artisan config:cache`
- `php artisan route:cache`

## Rollback
1. Set `FEATURE_ADMIN_FINANCE_TAX_INDEX=false`
2. Run:
   - `php artisan optimize:clear`
   - `php artisan config:cache`
   - `php artisan route:cache`

## Exit Criteria
- No ON/OFF parity drift in status code, auth behavior, redirects, and validation keys.
- No regression in tax settings and tax rate management behavior.
