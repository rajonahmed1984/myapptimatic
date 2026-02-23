# React Pilot Staging Checklist: User Activity Summary

## Scope
- Route: `GET /admin/users/activity-summary` (`admin.users.activity-summary`)
- Flag: `FEATURE_ADMIN_USERS_ACTIVITY_SUMMARY_INDEX`
- Fallback: Blade view remains default when flag is `false`

## Enable
1. Set staging env:
   - `FEATURE_ADMIN_USERS_ACTIVITY_SUMMARY_INDEX=true`
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
   - ON returns Inertia page (`Admin/Users/ActivitySummary/Index`).
3. Filter parity:
   - Query filters (`type`, `user_id`, `from`, `to`) preserve the same behavior.
   - Reset link returns to unfiltered index.
4. Data rendering parity:
   - Online/offline indicator, session counts, durations, and last seen/login values render.
   - Optional range column appears only when both `from` and `to` are provided.

## Safety Gates
- `php artisan test --filter=UserActivitySummaryUiParityTest`
- `php artisan test`
- `npm run build`
- `php artisan config:cache`
- `php artisan route:cache`

## Rollback
1. Set `FEATURE_ADMIN_USERS_ACTIVITY_SUMMARY_INDEX=false`
2. Run:
   - `php artisan optimize:clear`
   - `php artisan config:cache`
   - `php artisan route:cache`

## Exit Criteria
- No ON/OFF parity drift in status code, auth behavior, and filter rendering.
- No regression in admin navigation or legacy Blade routes.
