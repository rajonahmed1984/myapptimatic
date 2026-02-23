# React Pilot Staging Checklist: Apptimatic Email Show

## Scope
- Route: `GET /admin/apptimatic-email/messages/{message}` (`admin.apptimatic-email.show`)
- Flag: `FEATURE_ADMIN_APPTIMATIC_EMAIL_SHOW`
- Fallback: Blade view remains default when flag is `false`

## Enable
1. Set staging env:
   - `FEATURE_ADMIN_APPTIMATIC_EMAIL_SHOW=true`
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
   - ON returns Inertia page (`Admin/ApptimaticEmail/Inbox`).
3. Data parity:
   - Selected message and thread details match existing Blade output.
   - Inbox list links remain unchanged.
4. Non-regression:
   - Inbox route (`admin.apptimatic-email.inbox`) behavior remains unchanged.
   - Route-level middleware/permissions remain unchanged.

## Safety Gates
- `php artisan test --filter=ApptimaticEmailShowUiParityTest`
- `php artisan test`
- `npm run build`
- `php artisan config:cache`
- `php artisan route:cache`

## Rollback
1. Set `FEATURE_ADMIN_APPTIMATIC_EMAIL_SHOW=false`
2. Run:
   - `php artisan optimize:clear`
   - `php artisan config:cache`
   - `php artisan route:cache`

## Exit Criteria
- No ON/OFF parity drift in status code, auth behavior, and show rendering.
- No regression in inbox/show navigation behavior.