# Deployment Notes

## Production Auth/Session Baseline

Set these environment values on production:

```env
APP_URL=https://my.apptimatic.com
TRUSTED_PROXIES=*
SESSION_DRIVER=database
SESSION_COOKIE=myapptimatic_session
SESSION_DOMAIN=my.apptimatic.com
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
LOGIN_TRACE=false
```

## Shared Root Domain Safety (WHMCS/Other Apps)

If WHMCS or another app shares the same root domain, always:
- Set a unique `SESSION_COOKIE` per app (example: `myapptimatic_session`).
- Set `SESSION_DOMAIN` to the exact app host (example: `my.apptimatic.com`).
- Avoid parent-domain cookies like `.apptimatic.com` unless intentional cross-subdomain session sharing is required.

## Cache Bypass For Login Routes

Never cache any login page or login POST response for:
- `/login*`
- `/admin/login*`
- `/employee/login*`
- `/sales/login*`
- `/support/login*`

Cloudflare rule guidance:
- Set Cache Level: Bypass.
- Disable "Cache Everything" for these paths.
- Do not serve stale content for these paths.

Nginx guidance:

```nginx
location ~* ^/(login|admin/login|employee/login|sales/login|support/login) {
    add_header Cache-Control "no-store, no-cache, must-revalidate, max-age=0" always;
    add_header Pragma "no-cache" always;
    add_header Expires "0" always;
    proxy_no_cache 1;
    proxy_cache_bypass 1;
}
```

## Temporary Login Diagnostics

Enable:

```env
LOGIN_TRACE=true
```

Then inspect:
- `[LOGIN_TRACE]` logs from `App\Http\Middleware\LoginTrace`.
- `[CSRF_419]` logs from `bootstrap/app.php` exception handlers.

Disable `LOGIN_TRACE` after debugging.

## Deploy Commands

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan migrate --force
```

## Verify Effective Session Config On Live

```bash
php artisan tinker --execute="dump(config('app.url')); dump(config('session.driver')); dump(config('session.cookie')); dump(config('session.domain')); dump(config('session.secure')); dump(config('session.same_site'));"
php artisan tinker --execute="dump(strlen((string) config('app.key')) > 0 ? 'APP_KEY_SET' : 'APP_KEY_MISSING');"
```

## cURL Login Flow Verification (Cookie Jar)

Use a cookie jar to confirm CSRF + session persistence.

```bash
# 1) GET login: expect Set-Cookie + hidden _token
curl -isk -c cookies.txt https://my.apptimatic.com/login | sed -n '1,30p'

# 2) Extract CSRF token
TOKEN=$(curl -sk -c cookies.txt https://my.apptimatic.com/login | grep -oP 'name="_token" value="\K[^"]+')

# 3) POST wrong credentials: expect 302 back to /login and session/errors persisted
curl -isk -b cookies.txt -c cookies.txt \
  -X POST https://my.apptimatic.com/login \
  --data-urlencode "_token=$TOKEN" \
  --data-urlencode "email=wrong@example.com" \
  --data-urlencode "password=wrongpass" | sed -n '1,30p'

# 4) POST valid credentials: expect 302 to dashboard and Set-Cookie continuity
curl -isk -b cookies.txt -c cookies.txt \
  -X POST https://my.apptimatic.com/login \
  --data-urlencode "_token=$TOKEN" \
  --data-urlencode "email=valid@example.com" \
  --data-urlencode "password=valid-password" | sed -n '1,30p'
```

Repeat the same pattern for:
- `https://my.apptimatic.com/admin/login`
- `https://my.apptimatic.com/employee/login`
- `https://my.apptimatic.com/sales/login`
- `https://my.apptimatic.com/support/login`
