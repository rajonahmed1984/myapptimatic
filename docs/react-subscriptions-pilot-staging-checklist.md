# React Pilot Staging Checklist: Subscriptions Index

## Scope
- Route: `GET /admin/subscriptions` (`admin.subscriptions.index`)
- Flag: `FEATURE_ADMIN_SUBSCRIPTIONS_INDEX`
- Fallback: Blade view remains default when flag is `false`

## Enable
1. Set staging env:
   - `FEATURE_ADMIN_SUBSCRIPTIONS_INDEX=true`
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
   - ON returns Inertia page (`Admin/Subscriptions/Index`).
3. Data parity:
   - Search, table columns, and subscription row values match current Blade output.
   - Manage and delete actions call existing endpoints.
4. Non-regression:
   - Subscription create/edit/delete backend behavior remains unchanged.

## Safety Gates
- `php artisan test --filter=SubscriptionUiParityTest`
- `php artisan test`
- `npm run build`
- `php artisan config:cache`
- `php artisan route:cache`

## Rollback
1. Set `FEATURE_ADMIN_SUBSCRIPTIONS_INDEX=false`
2. Run:
   - `php artisan optimize:clear`
   - `php artisan config:cache`
   - `php artisan route:cache`

## Exit Criteria
- No ON/OFF parity drift in status code, auth behavior, and index rendering.
- No regression in subscription create/edit/delete flows.