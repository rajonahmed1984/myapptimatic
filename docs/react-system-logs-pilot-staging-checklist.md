# React Pilot Staging Checklist: System Logs

## Scope
- Routes:
  - `GET /admin/logs/activity` (`admin.logs.activity`)
  - `GET /admin/logs/admin` (`admin.logs.admin`)
  - `GET /admin/logs/module` (`admin.logs.module`)
  - `GET /admin/logs/email` (`admin.logs.email`)
  - `GET /admin/logs/ticket-mail-import` (`admin.logs.ticket-mail-import`)
- Flag: `FEATURE_ADMIN_LOGS_INDEX`
- Fallback: Blade view remains default when flag is `false`

## Enable
1. Set staging env:
   - `FEATURE_ADMIN_LOGS_INDEX=true`
2. Rebuild caches:
   - `php artisan optimize:clear`
   - `php artisan config:cache`
   - `php artisan route:cache`

## Validation (ON/OFF parity)
1. Guard parity:
   - Master admin can access all log tabs (`200`) with flag OFF and ON.
   - Client role stays forbidden (`403`) with flag OFF and ON.
2. UI parity:
   - OFF returns Blade page.
   - ON returns Inertia page (`Admin/Logs/Index`).
3. Data parity:
   - Tabs render and switch correctly.
   - Table columns (date/user/ip/level/message) match.
   - Empty state and pagination previous/next behavior match.

## Safety Gates
- `php artisan test --filter=SystemLogUiParityTest`
- `php artisan test`
- `npm run build`
- `php artisan config:cache`
- `php artisan route:cache`

## Rollback
1. Set `FEATURE_ADMIN_LOGS_INDEX=false`
2. Run:
   - `php artisan optimize:clear`
   - `php artisan config:cache`
   - `php artisan route:cache`

## Exit Criteria
- No ON/OFF parity drift in status code, auth behavior, and log tab rendering.
- No regression in log resend/delete POST/DELETE routes.
