# Phase 2: low-risk production optimization

This phase enables framework and static-asset optimizations without changing
business logic, database queries, or response contracts.

## Pre-deployment

Run in staging first:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan optimize:clear
php artisan optimize
php artisan performance:readiness --strict
```

The readiness command is read-only. It checks the environment, debug setting,
Laravel caches, Vite manifest, cache/session/queue drivers, OPcache signal, and
ensures the Phase 1 monitor is disabled in production.

## Required production environment

```dotenv
APP_ENV=production
APP_DEBUG=false
PERFORMANCE_MONITOR_ENABLED=false

CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database
```

Redis may replace the database cache/session drivers after a separate staging
test. It is not required for this phase.

## Apache delivery changes

`public/.htaccess` now:

- enables compression for text, CSS, JavaScript, JSON, XML, and SVG when
  `mod_deflate` is available;
- sends one-year immutable caching only for `/build/assets/`, whose Vite
  filenames are content-hashed;
- does not apply long caching to uploads, logos, PDFs, or other non-versioned
  public files.

If Apache is behind a CDN or reverse proxy, verify that it preserves
`Cache-Control` and `Vary: Accept-Encoding`.

## OPcache

The current local CLI does not load Zend OPcache. Production web/FPM PHP should
enable it. A conservative starting configuration is:

```ini
opcache.enable=1
opcache.memory_consumption=192
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
```

With `validate_timestamps=0`, restart PHP-FPM/Apache after every deployment.
If reliable restarts are unavailable, use `opcache.validate_timestamps=1` and a
small `opcache.revalidate_freq`.

## Verification

After deployment:

```bash
php artisan about
php artisan performance:readiness --strict
php artisan queue:monitor default,ai --max=100
```

Check:

1. login and one page in each portal;
2. admin dashboard, invoices, accounting, reports, and VAT settings;
3. a versioned asset response has
   `Cache-Control: public, max-age=31536000, immutable`;
4. HTML responses are not assigned the immutable asset policy;
5. the Phase 1 performance headers are absent in production.

## Rollback

Framework caches are fully reversible:

```bash
php artisan config:clear
php artisan route:clear
php artisan event:clear
php artisan view:clear
```

To roll back Apache delivery headers, revert the Phase 2 block in
`public/.htaccess`; no database rollback is involved.
