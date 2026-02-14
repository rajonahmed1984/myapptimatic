# Deployment Notes

## Production Sanity Check (Auth/Session)

Set these environment values on production:

```env
APP_URL=https://my.apptimatic.com
SESSION_DRIVER=file
SESSION_COOKIE=apptimatic_session
SESSION_DOMAIN=my.apptimatic.com
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
LOGIN_TRACE=false
```

Notes:
- Use the exact host for `SESSION_DOMAIN` on single-host deployments (for example `my.apptimatic.com`, not `.apptimatic.com`).
- Avoid parent-domain cookies (for example `.apptimatic.com`) unless you explicitly need cross-subdomain session sharing.
- Keep `SESSION_SECURE_COOKIE=true` when traffic is HTTPS.
- Keep a unique `SESSION_COOKIE` per app to avoid collisions with other apps on the same parent domain.
- `SESSION_SAME_SITE=lax` is the recommended default for login/session flows.
- `APP_URL` should be the root URL only (scheme + host + optional port), without paths like `/admin`.

For temporary login/session diagnostics only:

```env
LOGIN_TRACE=true
```

This writes `[LOGIN_TRACE]` records to `storage/logs/laravel.log`. Set it back to `false` after debugging.

After deploy or env changes:

```bash
php artisan optimize:clear
```
