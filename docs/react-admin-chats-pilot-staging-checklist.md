# React Pilot Staging Checklist: Admin Chats Index

## Scope
- Route: `GET /admin/chats` (`admin.chats.index`)
- Flag: `FEATURE_ADMIN_CHATS_INDEX`
- Fallback: Blade view remains default when flag is `false`

## Enable
1. Set staging env:
   - `FEATURE_ADMIN_CHATS_INDEX=true`
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
   - ON returns Inertia page (`Admin/Chats/Index`).
3. Data parity:
   - Project rows, unread badges, and status text render correctly.
   - Pagination controls work the same way.
   - `Open Chat` links still hit existing backend routes.
4. Navigation parity:
   - `Projects` link still points to `admin.projects.index`.

## Safety Gates
- `php artisan test --filter=AdminChatUiParityTest`
- `php artisan test`
- `npm run build`
- `php artisan config:cache`
- `php artisan route:cache`

## Rollback
1. Set `FEATURE_ADMIN_CHATS_INDEX=false`
2. Run:
   - `php artisan optimize:clear`
   - `php artisan config:cache`
   - `php artisan route:cache`

## Exit Criteria
- No ON/OFF parity drift in status code, auth behavior, and chat index rendering.
- No regression in project chat pages and stream endpoints.
