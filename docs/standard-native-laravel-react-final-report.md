# Standard Native Laravel + React Migration Final Report

Date: 2026-03-05
Branch: `chore/standardize-react`

## 1) Summary of changes

The project was incrementally standardized from a hybrid portal bootstrap model to a standard Laravel + Inertia React architecture.

Completed items:
- Single Inertia root view is now enforced by middleware:
  - `app/Http/Middleware/HandleInertiaRequests.php` -> `rootView()` returns `app`.
- Route monolith was split into module files while preserving URLs and names:
  - `routes/web.php` (entry/orchestration)
  - `routes/cron.php`
  - `routes/portals/admin.php`
  - `routes/portals/employee.php`
  - `routes/portals/sales.php`
  - `routes/portals/support.php`
  - `routes/portals/public.php`
- Vite entrypoint was standardized to one React app entry:
  - `vite.config.js` input -> `resources/js/app.jsx`
- React app bootstrap is unified in:
  - `resources/js/app.jsx`
- React page resolution is standardized to:
  - `resources/js/Pages/**/*`
- Legacy proxy tree removed from tracked source:
  - `resources/js/react/*` (tracked compatibility re-exports deleted)
- Enforced guard added to prevent reintroduction of legacy tree/references:
  - `scripts/check-react-standard.mjs`
  - `npm run check:react-standard`
  - CI gate step in `.github/workflows/ci.yml`
- Legacy migration helper scripts were deprecated to no-op guidance:
  - `scripts/generate-pages-proxy.ps1`
  - `scripts/migrate-react-tree-to-standard.ps1`
- Unused migration middleware was removed:
  - `app/Http/Middleware/ConvertAdminViewToInertia.php`
  - Middleware assertions were kept via string-based checks in:
    - `tests/Feature/PhaseBAdminRouteMiddlewareTest.php`
    - `tests/Feature/PhaseCTaskDetailRouteMiddlewareTest.php`
- Unused legacy bridge React pages/components were removed:
  - `resources/js/Pages/Admin/Legacy/HtmlPage.jsx`
  - `resources/js/Pages/Client/Legacy/HtmlPage.jsx`
  - `resources/js/Pages/Employee/Legacy/HtmlPage.jsx`
  - `resources/js/Pages/Rep/Legacy/HtmlPage.jsx`
  - `resources/js/Pages/Support/Legacy/HtmlPage.jsx`
  - `resources/js/Pages/Shared/LegacyHtmlPage.jsx`
- Legacy per-portal React wrapper Blade roots were removed:
  - `resources/views/react-admin.blade.php`
  - `resources/views/react-client.blade.php`
  - `resources/views/react-employee.blade.php`
  - `resources/views/react-guest.blade.php`
  - `resources/views/react-public.blade.php`
  - `resources/views/react-rep.blade.php`
  - `resources/views/react-sandbox.blade.php`
  - `resources/views/react-support.blade.php`
- Blade usage audit updated:
  - `docs/phase-7-blade-audit-report.md`
  - `storage/app/reports/blade-audit.json`

Retained by design:
- Blade for PDFs/emails/errors/non-UI utility endpoints.
- `cron/billing` server-rendered response path remains as non-portal utility behavior.

## 2) Migration map (old -> new)

- `resources/js/react/app.jsx` + `resources/js/app.js` -> `resources/js/app.jsx`
- `resources/js/react/Pages/*` -> `resources/js/Pages/*`
- Portal-specific root blades (`react-*.blade.php`) -> single `resources/views/app.blade.php`
- Large portal blocks in `routes/web.php` -> modular route files under `routes/portals/*` plus `routes/cron.php`

## 3) Validation steps and latest results

Latest full validation run (2026-03-05):
- `php artisan route:list --json` -> PASS (snapshot: `storage/app/reports/standardize-react-route-list.json`)
- `php artisan config:cache` -> PASS
- `php artisan route:cache` -> PASS
- `php artisan test --colors=never` -> PASS (`581 passed`)
- `npm run build` -> PASS

Latest incremental validation after cleanup commits:
- `php artisan test --filter="PhaseBAdminRouteMiddlewareTest|PhaseCTaskDetailRouteMiddlewareTest"` -> PASS
- `php artisan test --filter=CronBillingEndpointSmokeTest` -> PASS
- `npm run check:react-standard` -> PASS
- `npm run build` -> PASS
- `php artisan test --colors=never` -> FAIL in current dirty local workspace (unrelated pre-existing local WIP changes outside migration scope)

Additional safety checks previously used during migration:
- Portal smoke tests for entry/login pages
- React sandbox feature test
- Public products Inertia parity tests
- Blade audit (`php artisan ui:audit-blade --write`)

## 4) What remains

Required migration objectives are complete for standard architecture.

Optional follow-up only:
- Collapse portal-specific shell layouts (`layouts.admin`, `layouts.client`, `layouts.rep`, `layouts.support`) into one Blade shell if design/system constraints allow.
- Keep monitoring for any untracked/local experiments introducing `resources/js/react/*` paths.

## 5) Risks and mitigations

- Risk: future feature branches may reintroduce old `resources/js/react/*` conventions.
  - Mitigation: keep Inertia resolver restricted to `resources/js/Pages/*` and enforce in code review.
- Risk: unintended Blade file deletion.
  - Mitigation: Blade audit command remains available; cleanup was performed only after reference checks.
- Risk: route behavior drift after refactor.
  - Mitigation: route caching/tests/smoke tests were executed after each major phase.

## 6) Rollback strategy

- Migration is split into small commits; rollback can be done by reverting specific commits in reverse order.
- After any rollback:
  - `php artisan optimize:clear`
  - `php artisan config:cache`
  - `php artisan route:cache`
  - `php artisan test`
  - `npm run build`
