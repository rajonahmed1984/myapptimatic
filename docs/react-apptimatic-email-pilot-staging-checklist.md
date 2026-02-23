# React Pilot Staging Checklist: Apptimatic Email Inbox

## Scope
- Route: `GET /admin/apptimatic-email` (`admin.apptimatic-email.inbox`)
- Flag: `FEATURE_ADMIN_APPTIMATIC_EMAIL_INBOX`
- Fallback: Blade view remains default when flag is `false`

## Enable
1. Set staging env:
   - `FEATURE_ADMIN_APPTIMATIC_EMAIL_INBOX=true`
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
   - Inbox rows match sender/subject/snippet/date.
   - Selected thread details are visible.
   - Message links keep existing show route (`admin.apptimatic-email.show`).
4. Non-regression:
   - `admin.apptimatic-email.show` remains unchanged Blade behavior.

## Safety Gates
- `php artisan test --filter=ApptimaticEmailUiParityTest`
- `php artisan test`
- `npm run build`
- `php artisan config:cache`
- `php artisan route:cache`

## Rollback
1. Set `FEATURE_ADMIN_APPTIMATIC_EMAIL_INBOX=false`
2. Run:
   - `php artisan optimize:clear`
   - `php artisan config:cache`
   - `php artisan route:cache`

## Exit Criteria
- No ON/OFF parity drift in status code, auth behavior, and inbox rendering.
- No regression in email show route behavior.