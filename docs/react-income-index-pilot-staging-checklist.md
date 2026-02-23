# React Pilot Staging Checklist: Income Index

## Scope
- Route: `GET /admin/income` (`admin.income.index`)
- Flag: `FEATURE_ADMIN_INCOME_INDEX`
- Fallback: Blade view remains default when flag is `false`

## Enable
1. Set staging env:
   - `FEATURE_ADMIN_INCOME_INDEX=true`
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
   - ON returns Inertia page (`Admin/Income/Index`).
3. Behavior parity:
   - Search query and pagination remain GET-based and preserve URL behavior.
   - Attachment view links use the same existing backend route.
   - Navigation links to categories and add income remain unchanged.

## Safety Gates
- `php artisan test --filter=IncomeUiParityTest`
- `php artisan test`
- `npm run build`
- `php artisan config:cache`
- `php artisan route:cache`

## Rollback
1. Set `FEATURE_ADMIN_INCOME_INDEX=false`
2. Run:
   - `php artisan optimize:clear`
   - `php artisan config:cache`
   - `php artisan route:cache`

## Exit Criteria
- No ON/OFF parity drift in status code, auth behavior, and index rendering.
- No regression in search/pagination and attachment link behavior.
