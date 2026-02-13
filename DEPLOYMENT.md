# Deployment Notes

## Production Sanity Check (Auth/Session)

Set these environment values on production:

```env
APP_URL=https://my.apptimatic.com
SESSION_DRIVER=file
SESSION_DOMAIN=my.apptimatic.com
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
```

Notes:
- Use the exact host for `SESSION_DOMAIN` on single-host deployments (for example `my.apptimatic.com`, not `.apptimatic.com`).
- Keep `SESSION_SECURE_COOKIE=true` when traffic is HTTPS.
- `SESSION_SAME_SITE=lax` is the recommended default for login/session flows.
- `APP_URL` should be the root URL only (scheme + host + optional port), without paths like `/admin`.

After deploy or env changes:

```bash
php artisan optimize:clear
```
