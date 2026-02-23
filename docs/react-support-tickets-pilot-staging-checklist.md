# React Pilot Staging Checklist: Support Tickets Index

## Scope
- Route: `GET /admin/support-tickets` (`admin.support-tickets.index`)
- Flag: `FEATURE_ADMIN_SUPPORT_TICKETS_INDEX`
- Fallback: Blade view remains default when flag is `false`

## Enable
1. Set staging env:
   - `FEATURE_ADMIN_SUPPORT_TICKETS_INDEX=true`
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
   - ON returns Inertia page (`Admin/SupportTickets/Index`).
3. Data parity:
   - Status filter chips render counts and active state.
   - Ticket list columns and paging render correctly.
   - Reply/View/Delete actions point to existing endpoints.
4. Non-regression:
   - create/show/reply/update/delete support ticket flows stay unchanged.

## Safety Gates
- `php artisan test --filter=SupportTicketUiParityTest`
- `php artisan test`
- `npm run build`
- `php artisan config:cache`
- `php artisan route:cache`

## Rollback
1. Set `FEATURE_ADMIN_SUPPORT_TICKETS_INDEX=false`
2. Run:
   - `php artisan optimize:clear`
   - `php artisan config:cache`
   - `php artisan route:cache`

## Exit Criteria
- No ON/OFF parity drift in status code, auth behavior, and index rendering.
- No regression in support ticket create/show/reply/update/delete flows.
