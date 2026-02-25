# Phase 7 Staging Runbook (Go/No-Go + Rollback Drill)

Date: 2026-02-25  
Repo: `origin https://github.com/rajonahmed1984/myapptimatic.git`  
Base branch: `main`  
Current HEAD (at writing): `315ef2b`

## 1) Release Branch and Candidate Tag

```bash
git fetch origin --prune
git checkout main
git pull --ff-only origin main

# Cut release branch for staging validation
git switch -c release/phase7-closeout-20260225

# Optional immutable candidate tag before staging deploy
git tag -a phase7-candidate-20260225 -m "Phase 7 closeout candidate for staging"
git push origin release/phase7-closeout-20260225
git push origin phase7-candidate-20260225
```

## 2) Staging Deploy

```bash
# On staging server
git fetch origin --prune
git checkout release/phase7-closeout-20260225
git pull --ff-only origin release/phase7-closeout-20260225

composer install --no-interaction --prefer-dist --optimize-autoloader
npm ci
npm run build

php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan migrate --force
```

## 3) Mandatory Phase 7 Gate on Staging

```bash
composer phase7:verify
php artisan ui:audit-blade --write
```

Expected:
- `composer phase7:verify` passes.
- `npm run build`, `config:cache`, `route:cache` pass inside verification.
- Blade audit reports no cleanup blockers for current batch.

## 4) Manual No-Break Smoke (Staging)

Run and record results for:

1. Auth guards
- `/admin/login`, `/login`, `/employee/login`, `/sales/login`, `/support/login` login and logout.

2. Permissions
- Visit one admin-only route as non-admin and confirm `403` contract remains unchanged.

3. Payment callback contract
- Trigger one safe callback test path (PayPal/SSLCommerz/bKash test callback route used in staging flow) and verify status+redirect unchanged.

4. Upload endpoint
- Submit one known upload flow (e.g. income attachment or support attachment) and verify success + file availability.

5. PDF/binary download
- Download one invoice/pdf and confirm binary headers:
  - `Content-Type` correct
  - `Content-Disposition` unchanged

6. SSE contract
- Open one SSE endpoint used by chat/stream and confirm:
  - `Content-Type: text/event-stream`
  - stream remains open and emits events.

## 5) Route Signature Drift Check

```bash
php artisan route:list --json > storage/app/current-routes-staging.json
```

Compare against baseline `storage/app/baseline-routes.json`.

Acceptable for current state:
- Added: `GET|HEAD|__ui/react-sandbox`
- Removed: none

## 6) Rollback Drill (Mandatory)

```bash
# Identify prior commit before release head
git log --oneline -n 5

# Hard rollback target example:
# previous main commit before candidate (replace <PREV_SHA>)
git checkout release/phase7-closeout-20260225
git reset --hard <PREV_SHA>

composer install --no-interaction --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan migrate --force
```

After rollback:
- Re-run quick smoke for auth/permission/payment/upload/pdf/SSE.
- Confirm regression (if any) disappears and baseline behavior is restorable.

Restore candidate after drill:

```bash
git fetch origin --tags
git checkout release/phase7-closeout-20260225
git reset --hard phase7-candidate-20260225

composer install --no-interaction --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan migrate --force
```

## 7) Go/No-Go Decision

GO only if all are true:

1. `composer phase7:verify` passes on staging.
2. Manual no-break smoke passes (all 6 buckets).
3. Route drift acceptable only (`/__ui/react-sandbox` addition).
4. Rollback drill executed and verified.
5. No auth/guard/permission/payment/upload/pdf/SSE contract drift observed.

NO-GO triggers:

1. Any status/redirect/validation/flash mismatch on migrated flows.
2. Any binary header drift on upload/pdf.
3. SSE content-type/buffering regressions.
4. Rollback cannot restore previous stable behavior quickly.

## 8) Production Tag + Deploy (After GO)

```bash
git checkout release/phase7-closeout-20260225
git pull --ff-only origin release/phase7-closeout-20260225
git tag -a phase7-release-20260225 -m "Phase 7 closeout production release"
git push origin phase7-release-20260225
```

Deploy production from `phase7-release-20260225` with standard deploy steps:

```bash
composer install --no-interaction --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan migrate --force
```

## 9) Fast Production Rollback

```bash
# Replace with last known good tag
git fetch origin --tags
git checkout <LAST_KNOWN_GOOD_TAG>

composer install --no-interaction --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan migrate --force
```

Then execute smoke checks immediately.
