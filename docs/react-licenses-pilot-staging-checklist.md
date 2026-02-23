# React Pilot Staging Checklist: Licenses Index

## Scope
- Route: `GET /admin/licenses` (`admin.licenses.index`)
- Flag: `FEATURE_ADMIN_LICENSES_INDEX`
- Fallback: Blade view remains default when flag is `false`

## Enable
1. Set staging env:
   - `FEATURE_ADMIN_LICENSES_INDEX=true`
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
   - ON returns Inertia page (`Admin/Licenses/Index`).
3. Data parity:
   - Search, table columns, and license row values match current Blade output.
   - Sync/manage actions call existing endpoints.
4. Non-regression:
   - License create/edit/sync/destroy backend behavior remains unchanged.

## Safety Gates
- `php artisan test --filter=LicenseUiParityTest`
- `php artisan test`
- `npm run build`
- `php artisan config:cache`
- `php artisan route:cache`

## Rollback
1. Set `FEATURE_ADMIN_LICENSES_INDEX=false`
2. Run:
   - `php artisan optimize:clear`
   - `php artisan config:cache`
   - `php artisan route:cache`

## Exit Criteria
- No ON/OFF parity drift in status code, auth behavior, and index rendering.
- No regression in license management flows.