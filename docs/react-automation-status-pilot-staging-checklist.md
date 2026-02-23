# React Pilot Staging Checklist: Automation Status

## Scope
- Route: `GET /admin/automation-status` (`admin.automation-status`)
- Flag: `FEATURE_ADMIN_AUTOMATION_STATUS_INDEX`
- Fallback: Blade view remains default when flag is `false`

## Enable
1. Set staging env:
   - `FEATURE_ADMIN_AUTOMATION_STATUS_INDEX=true`
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
   - ON returns Inertia page (`Admin/AutomationStatus/Index`).
3. Content parity:
   - Status badge, cron health cards, AI queue cards, daily actions all render.
   - Last error box shows only when backend reports failed status + error.
   - Portal clock updates live in UI.
4. Navigation parity:
   - `Cron settings` link routes to `admin.settings.edit?tab=cron`.

## Safety Gates
- `php artisan test --filter=AutomationStatusUiParityTest`
- `php artisan test`
- `npm run build`
- `php artisan config:cache`
- `php artisan route:cache`

## Rollback
1. Set `FEATURE_ADMIN_AUTOMATION_STATUS_INDEX=false`
2. Run:
   - `php artisan optimize:clear`
   - `php artisan config:cache`
   - `php artisan route:cache`

## Exit Criteria
- No ON/OFF parity drift in status code, auth behavior, and rendered content.
- No regression reported in adjacent admin navigation or ajax-engine flow.
