# Phase 6 Audit: Blade + Middleware Cleanup Candidates (No Deletions)

Date: 2026-03-05

## Scope
- Runtime reference audit only.
- No Blade file or middleware class deleted in this phase.

## Blade audit result
- Command used: `php artisan ui:audit-blade`
- Summary:
  - `total_views: 43`
  - `referenced_views: 43`
  - `unreferenced_views: 0`
  - `cleanup_candidates: 0`

## Runtime observations
- `/cron/billing` remains intentional Blade (operational endpoint), documented in:
  - `docs/phase-5-cron-billing-standardization-note.md`
- The following legacy login Blade view keys are still present in `Portal::map()` but current login UI flow is Inertia:
  - `auth.login`
  - `auth.admin-login`
  - `employee.auth.login`
  - `sales.auth.login`
  - `support.auth.login`
- `resources/views/admin/hr/employees/show.blade.php` exists as a legacy artifact; active runtime path serves `Inertia::render('Admin/Hr/Employees/Show', ...)`.

## Middleware observations
- `ConvertAdminViewToInertia` is not wired in route middleware aliases or web middleware stack.
- Existing tests already enforce this remains unwired:
  - `tests/Feature/PhaseBAdminRouteMiddlewareTest.php`
  - `tests/Feature/PhaseCTaskDetailRouteMiddlewareTest.php`

## Decision
- Keep all candidate files/classes for now.
- Deletion requires explicit evidence from:
  - route/runtime access logs,
  - staging parity validation,
  - and a rollback-ready small-batch cleanup commit.
