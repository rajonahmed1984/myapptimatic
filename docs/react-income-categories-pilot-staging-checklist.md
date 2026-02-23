# React Pilot Staging Checklist: Income Categories Index

## Scope
- Route: `GET /admin/income/categories` (`admin.income.categories.index`)
- Flag: `FEATURE_ADMIN_INCOME_CATEGORIES_INDEX`
- Fallback: Blade view remains default when flag is `false`

## Enable
1. Set staging env:
   - `FEATURE_ADMIN_INCOME_CATEGORIES_INDEX=true`
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
   - ON returns Inertia page (`Admin/Income/Categories/Index`).
3. Behavior parity:
   - Add/update/delete actions hit the same POST/PUT/DELETE endpoints.
   - Validation errors, redirects, and flash messages are identical with flag OFF and ON.
   - Delete guard error (`category`) still blocks categories linked to income.

## Safety Gates
- `php artisan test --filter=IncomeCategoryUiParityTest`
- `php artisan test --filter=IncomeCategoryActionContractParityTest`
- `php artisan test`
- `npm run build`
- `php artisan config:cache`
- `php artisan route:cache`

## Rollback
1. Set `FEATURE_ADMIN_INCOME_CATEGORIES_INDEX=false`
2. Run:
   - `php artisan optimize:clear`
   - `php artisan config:cache`
   - `php artisan route:cache`

## Exit Criteria
- No ON/OFF parity drift in status code, auth behavior, redirects, and validation keys.
- No regression in category add/update/delete behavior.
