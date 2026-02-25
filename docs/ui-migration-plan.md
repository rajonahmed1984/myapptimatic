# Blade to React + Inertia UI Migration Plan

## Phase 1 - Baseline and Route Classification
- Scope: route inventory, blade candidate refresh, non-UI endpoint classification.
- Deliverables:
  - regenerate `storage/app/routes-latest.json`
  - regenerate `storage/app/blade-route-candidates.json` and `storage/app/blade-ui-get-candidates.json`
  - write classification report in `storage/app/migration-reports/*-classification.json`
- Dependency removals: none.
- Test plan:
  - run `php scripts/run-ui-migration-phase.php phase-baseline`
  - validate `ui_not_detected=0`, `ambiguous=0`
- Rollout plan: no feature flag; reporting only.
- Definition of Done:
  - every route classified as UI, partial, or non-UI
  - no unclassified GET routes
- Metrics snapshot:
  - total routes
  - UI GET routes
  - ambiguous/not-detected count

## Phase 2 - Auth UI Completion
- Scope: `register`, `password.request`, `password.reset`, `admin.password.request`, `project-client.login`.
- Deliverables:
  - direct `Inertia::render(...)` in controllers
  - React pages under `resources/js/react/Pages/Auth/*`
  - shared form primitives and reCAPTCHA component reuse
- Dependency removals: remove any blade-auth render path for these routes.
- Test plan:
  - `php artisan test --filter=AuthPhaseAUiParityTest`
  - manual submit/fail/success flows for each route
- Rollout plan: direct cutover, no temporary flag.
- Definition of Done:
  - all 5 routes serve Inertia payload
  - blade auth views for those 5 routes unused
- Metrics snapshot:
  - converted UI pages
  - remaining full blade UI pages

## Phase 3 - Admin Dashboard and Customer Detail
- Scope: `admin.dashboard`, `admin.customers.show`.
- Deliverables:
  - controller props finalized for React
  - React pages/components for dashboard and customer detail
  - no `return view(...)` in route actions
- Dependency removals: remove wrapper reliance for these actions.
- Test plan:
  - feature tests for dashboard/customer show
  - manual auth + policy boundary checks
- Rollout plan: direct cutover with parity checklist.
- Definition of Done:
  - both routes directly render Inertia
  - flash/errors/filters preserved
- Metrics snapshot:
  - wrapper-dependent routes delta

## Phase 4 - Admin HR Employee Detail and Invoice Client View
- Scope: `admin.hr.employees.show`, `admin.invoices.client-view`.
- Deliverables:
  - React pages and shared detail/table modules
  - controller response parity for props
- Dependency removals: remove legacy blade usage for these pages.
- Test plan:
  - feature tests for authorization and status actions
  - manual table/filter/pagination checks
- Rollout plan: direct cutover.
- Definition of Done:
  - route-level policy behavior unchanged
  - no blade page render path remaining
- Metrics snapshot:
  - full blade UI pages remaining

## Phase 5 - Project Task Detail Shells (4 Portals)
- Scope:
  - `admin.projects.tasks.show`
  - `client.projects.tasks.show`
  - `employee.projects.tasks.show`
  - `rep.projects.tasks.show`
- Deliverables:
  - React page shell per portal with shared task detail modules
  - controllers return Inertia props directly
- Dependency removals: remove blade task detail page rendering.
- Test plan:
  - feature tests for all 4 portals
  - manual permission matrix verification
- Rollout plan: portal-by-portal release in same PR, with rollback by route mapping only.
- Definition of Done:
  - all 4 shells render from React
  - policy access unchanged
- Metrics snapshot:
  - converted UI pages count

## Phase 6 - Keep Non-UI Endpoints Intact
- Scope: explicit freeze list (webhooks, uploads, streams, downloads, redirects/utility).
- Deliverables:
  - classifier allowlist/denylist review
  - automated guard assertions for SSE headers and download responses
- Dependency removals: none.
- Test plan:
  - response code and header checks (`text/event-stream`, `Content-Disposition`, callback statuses)
- Rollout plan: no behavior change; protect existing behavior.
- Definition of Done:
  - zero non-UI endpoint regressions from migration
- Metrics snapshot:
  - non-UI endpoint count and category breakdown

## Phase 7 - Partial Blade Fragment Burn-down (Batch 1)
- Scope: high-traffic partial fragments (tasks index, chat blocks, activity blocks).
- Deliverables:
  - replace blade partial UI responses with React/Inertia partial reload or JSON-driven React rendering
  - preserve endpoint contracts until callers migrated
- Dependency removals: remove partial blade view dependencies incrementally.
- Test plan:
  - endpoint contract snapshots
  - manual high-frequency workflow checks
- Rollout plan: batch-based migration, rollback by endpoint group.
- Definition of Done:
  - batch-1 partials no longer require blade view templates
- Metrics snapshot:
  - remaining partial blade fragments list

## Phase 8 - Partial Blade Fragment Burn-down (Batch 2)
- Scope: remaining low-traffic fragments and modal/form snippets.
- Deliverables:
  - move residual fragments to React components
  - delete unused blade partials
- Dependency removals: remove legacy partial rendering helper paths.
- Test plan:
  - smoke tests across admin/client/employee/rep/support portals
- Rollout plan: final partial cleanup release.
- Definition of Done:
  - partial blade fragments reduced to approved exceptions only
- Metrics snapshot:
  - partial fragment count target near zero

Status snapshot (2026-02-25):
- Chat/task/activity partial Blade fragments removed.
- Chat/task/activity endpoints standardized to JSON-only payloads.
- Contract reference: `docs/chat-activity-json-contract.md`.
- Closeout metrics snapshot:
  - `total_ui_page_get_routes=204`
  - `converted_ui_pages=204`
  - `remaining_full_blade_ui_pages=0`
  - `remaining_partial_blade_fragments=0`
  - `wrapper_dependent_routes=0`
  - `ui_not_detected=0`

## Phase 9 - ConvertAdminViewToInertia Decommission
- Scope: middleware removal from route groups where no route depends on it.
- Deliverables:
  - remove middleware from route groups
  - keep temporary scoped usage only if explicitly approved
- Dependency removals:
  - deprecate `ConvertAdminViewToInertia` for UI flow
- Test plan:
  - route:list middleware diff
  - regression tests for all migrated pages
- Rollout plan: remove middleware in controlled PR with quick rollback.
- Definition of Done:
  - wrapper-dependent routes at or near zero
- Metrics snapshot:
  - wrapper-dependent route count

## Phase 10 - Final Blade UI Elimination and Hard Close
- Scope: ensure no full blade UI pages remain in routing.
- Deliverables:
  - delete unused blade full-page views
  - preserve only email templates and approved non-UI fragments
  - final migration report JSON
- Dependency removals: remove dead blade view references.
- Test plan:
  - `php artisan test`
  - `npm run build`
  - manual portal smoke
- Rollout plan: final cutover + post-release monitoring.
- Definition of Done:
  - remaining full blade UI pages = 0
  - all UI GET routes render Inertia directly
- Metrics snapshot:
  - final baseline vs end-state comparison
