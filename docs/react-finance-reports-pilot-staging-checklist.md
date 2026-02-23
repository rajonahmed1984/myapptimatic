# React Pilot Staging Checklist: Finance Reports Index

## Scope
- Route: `GET /admin/finance/reports` (`admin.finance.reports.index`)
- Flag: `FEATURE_ADMIN_FINANCE_REPORTS_INDEX`
- Fallback: Blade view remains default when flag is `false`

## Enable
1. Set staging env:
   - `FEATURE_ADMIN_FINANCE_REPORTS_INDEX=true`
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
   - ON returns Inertia page (`Admin/Finance/Reports/Index`).
3. Data parity:
   - Filter form fields and source checkboxes retain values.
   - Summary and tax blocks render with expected totals.
   - Category, trend, and monthly tables render from same backend data.
4. Non-regression:
   - `admin.finance.tax.index` and other finance routes are unchanged.

## Safety Gates
- `php artisan test --filter=FinanceReportsUiParityTest`
- `php artisan test`
- `npm run build`
- `php artisan config:cache`
- `php artisan route:cache`

## Rollback
1. Set `FEATURE_ADMIN_FINANCE_REPORTS_INDEX=false`
2. Run:
   - `php artisan optimize:clear`
   - `php artisan config:cache`
   - `php artisan route:cache`

## Exit Criteria
- No ON/OFF parity drift in status code, auth behavior, and index rendering.
- No regression in adjacent finance routes.
