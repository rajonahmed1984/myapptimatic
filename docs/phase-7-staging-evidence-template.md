# Phase 7 Staging Evidence Log

Date: 2026-02-25
Environment: local pre-staging validation (Windows CLI)
Executed by: Codex
Release branch: `release/phase7-closeout-20260225`
Candidate tag: `phase7-candidate-20260225-r2`

## 1) Mandatory Gate Results

- `composer phase7:verify`: PASS
- `php artisan ui:audit-blade --write`: PASS
- `php artisan config:cache`: PASS (inside `composer phase7:verify`)
- `php artisan route:cache`: PASS (inside `composer phase7:verify`)
- `npm run build`: PASS (inside `composer phase7:verify`)

Notes:
- Full suite result inside verification: `523 passed`, `2279 assertions`.
- Build reported non-blocking chunk-size warning only.

## 2) Route Drift Evidence

- baseline route count: `549`
- staging/current route count: `550`
- added signatures: `GET|HEAD|__ui/react-sandbox`
- removed signatures: none

Expected acceptable add:
- `GET|HEAD|__ui/react-sandbox`

## 3) Manual No-Break Smoke Evidence

1. Auth (all guards)
- `/admin/login`: PASS (covered in `Tests\Feature\Smoke\NoBreakSmokeTest`)
- `/login`: PASS (covered in `Tests\Feature\Smoke\NoBreakSmokeTest`)
- `/employee/login`: PASS (covered in `Tests\Feature\Smoke\NoBreakSmokeTest`)
- `/sales/login`: PASS (covered in `Tests\Feature\Smoke\NoBreakSmokeTest`)
- `/support/login`: PASS (covered in `Tests\Feature\Smoke\NoBreakSmokeTest`)
- logout per guard: PASS (covered in `Tests\Feature\Smoke\NoBreakSmokeTest`)

2. Permission denial
- admin-only route as non-admin returns `403`: PASS (covered in `Tests\Feature\Smoke\NoBreakSmokeTest`)

3. Payment callback contract
- callback route tested: PayPal/SSLCommerz/bKash callback contracts via `Tests\Feature\PaymentCallbackContractTest`
- status/redirect unchanged: PASS

4. Upload endpoint
- flow tested: income attachment upload via `Tests\Feature\Smoke\NoBreakSmokeTest`
- result unchanged: PASS

5. PDF/binary response
- endpoint tested: client invoice PDF + binary contract tests
- `Content-Type` unchanged: PASS
- `Content-Disposition` unchanged: PASS

6. SSE response
- endpoint tested: client project chat stream via `Tests\Feature\Smoke\NoBreakSmokeTest` and complex transport contract tests
- `text/event-stream` present: PASS
- stream behavior unchanged: PASS

## 4) Rollback Drill Evidence

- rollback target ref: `79c1bef` (runbook candidate baseline)
- rollback deploy completed: PASS (`git reset --hard 79c1bef` in isolated worktree)
- smoke after rollback: PASS (`NoBreakSmokeTest` 6 passed, 60 assertions)
- restored candidate tag deploy: PASS (`git reset --hard phase7-candidate-20260225-r2`)
- smoke after restore: PASS (`NoBreakSmokeTest` 6 passed, 60 assertions)

## 5) Go/No-Go Decision

- Decision: GO (pre-staging technical gate)
- Approved by: Codex
- Timestamp: 2026-02-25

Reasons:
- Mandatory technical gates passed in pre-staging environment.
- Rollback drill completed and restored successfully in isolated worktree.
- Final production GO still requires real staging host execution/signoff owner.

If NO-GO, rollback trigger:
- [ ] status/redirect/validation mismatch
- [ ] auth/guard regression
- [ ] permission regression
- [ ] payment callback drift
- [ ] upload/pdf binary header drift
- [ ] SSE header/stream drift
- [ ] rollback failure
