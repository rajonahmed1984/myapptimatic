# React Pilot Staging Checklist: Orders Index

## Scope
- Route: `GET /admin/orders` (`admin.orders.index`)
- Flag: `FEATURE_ADMIN_ORDERS_INDEX`
- Fallback: Blade view remains default when flag is `false`

## Enable
1. Set staging env:
   - `FEATURE_ADMIN_ORDERS_INDEX=true`
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
   - ON returns Inertia page (`Admin/Orders/Index`).
3. Data parity:
   - Order list columns and row values match existing table.
   - Pending rows keep accept/cancel actions on existing endpoints.
   - Delete action and show/invoice links remain unchanged.
4. Non-regression:
   - show/approve/cancel/update-plan/destroy flows remain backend-owned and unchanged.

## Safety Gates
- `php artisan test --filter=OrderUiParityTest`
- `php artisan test`
- `npm run build`
- `php artisan config:cache`
- `php artisan route:cache`

## Rollback
1. Set `FEATURE_ADMIN_ORDERS_INDEX=false`
2. Run:
   - `php artisan optimize:clear`
   - `php artisan config:cache`
   - `php artisan route:cache`

## Exit Criteria
- No ON/OFF parity drift in status code, auth behavior, and index rendering.
- No regression in order processing routes.
