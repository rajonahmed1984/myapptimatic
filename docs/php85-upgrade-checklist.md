# PHP 8.5 Infrastructure Upgrade Checklist

## Scope
- Application codebase is currently compatible with PHP `^8.2` and was dependency-checked against PHP `8.5.0` using Composer platform simulation.
- Keep production/runtime on current stable PHP until this checklist is complete.

## Pre-Upgrade
1. Build a staging image/VM with PHP 8.5 and required extensions:
   - `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `iconv`, `json`, `libxml`, `mbstring`, `openssl`, `pcre`, `phar`, `session`, `tokenizer`, `xml`, `xmlwriter`.
2. Run:
   - `composer install --no-interaction`
   - `composer check-platform-reqs`
3. Confirm OPcache settings match production profile.

## Application Validation
1. Clear and rebuild framework caches:
   - `php artisan optimize:clear`
   - `php artisan config:cache`
   - `php artisan route:cache`
2. Run full regression suite:
   - `php artisan test`
3. Run frontend build:
   - `npm ci`
   - `npm run build`

## Runtime Validation
1. Validate all login portals (admin/client/employee/sales/support).
2. Validate permission denial paths (403 behavior unchanged).
3. Validate payment callbacks and invoice flows.
4. Validate uploads and binary download headers (PDFs/documents).
5. Validate SSE endpoints (`text/event-stream`) and chat polling.
6. Validate queue workers and scheduled commands.

## Rollback Plan
1. Keep previous PHP image/runtime available for immediate switch-back.
2. Rollback sequence:
   - Revert runtime to previous PHP version.
   - Redeploy same application artifact.
   - `php artisan optimize:clear`
3. Re-run smoke tests after rollback.

## Exit Criteria
- Full tests green on PHP 8.5 staging.
- `composer check-platform-reqs` green.
- No regression in auth, permissions, payments, uploads/PDF, queues, SSE/chat.
- Staging burn-in completed without new runtime errors.
