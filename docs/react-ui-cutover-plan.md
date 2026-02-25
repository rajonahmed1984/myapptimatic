# React UI Full Cutover Plan

## Phase 1: Baseline + Route Classification
- Scope: all routes from `storage/app/routes-current.json`, `storage/app/blade-route-candidates.json`, `storage/app/blade-ui-get-candidates.json`
- Deliverables:
  - regenerate route snapshot (`routes-latest.json`)
  - classify routes into UI page GET / non-UI endpoint / partial fragment / ambiguous
  - produce baseline metrics report
- Dependency removals: none
- Test plan:
  - run classifier script
  - verify 13 full Blade targets still exist in route list
- Rollout plan: no runtime changes
- Done checklist:
  - classification JSON exists
  - baseline metrics JSON exists
  - 13 target routes confirmed
- Metrics snapshot:
  - total UI page GET routes
  - remaining full Blade UI pages
  - wrapper-dependent routes

## Phase 2: Auth UI Cutover (Phase A)
- Scope:
  - `register`
  - `password.request`
  - `password.reset`
  - `admin.password.request`
  - `project-client.login`
- Deliverables:
  - direct `Inertia::render(...)` in controllers
  - React pages under `resources/js/react/Pages/Auth/*`
  - shared auth layout and form/flash primitives
  - route middleware updates for Inertia root handling
  - retire converted Blade pages
- Dependency removals:
  - reduce full Blade UI count by at least these 5 routes
- Test plan:
  - GET route inertia render assertions
  - redirect/validation contract checks on forgot/project-login/register
  - run targeted auth tests
- Rollout plan:
  - no feature flag (existing route behavior preserved)
- Done checklist:
  - all 5 routes render Inertia
  - no auth guard/redirect behavior drift
  - converted Blade files removed or unreferenced
- Metrics snapshot:
  - remaining full Blade UI pages
  - wrapper-dependent routes

## Phase 3: Admin Standalone UI Cutover (Phase B)
- Scope:
  - `admin.dashboard`
  - `admin.customers.show`
  - `admin.hr.employees.show`
  - `admin.invoices.client-view`
- Deliverables:
  - direct Inertia render for each route
  - React pages and structured props for each screen
  - parity assertions for redirects/validation/permissions
- Dependency removals:
  - remove wrapper reliance for those routes
- Test plan:
  - route/component assertions
  - authorization checks
  - action contract tests
- Rollout plan: no flag unless route behavior mismatch risk appears
- Done checklist:
  - 4 routes on direct Inertia
  - parity tests green
- Metrics snapshot updated

## Phase 4: Project Task Detail UI Shell Cutover (Phase C)
- Scope:
  - `admin/projects/{project}/tasks/{task}`
  - `client/projects/{project}/tasks/{task}`
  - `employee/projects/{project}/tasks/{task}`
  - `sales/projects/{project}/tasks/{task}`
- Deliverables:
  - page-shell React conversion
  - direct Inertia controller response for task detail shell
- Dependency removals:
  - remove wrapper reliance for all 4 task detail routes
- Test plan:
  - task detail view parity by portal
  - permission matrix checks
- Rollout plan: no changes to stream/upload/download endpoints
- Done checklist:
  - all 4 task detail GET pages on Inertia
  - SSE/chat/download/upload unchanged
- Metrics snapshot updated

## Phase 5: Partial Fragment Batch 1 (Tasks Index Tables)
- Scope:
  - `admin/tasks`, `client/tasks`, `employee/tasks`, `sales/tasks` partial responses
- Deliverables:
  - replace Blade table partial responses with React-friendly payload path
  - maintain current endpoint URLs
- Dependency removals:
  - reduce Blade partial fragment count
- Test plan:
  - legacy AJAX compatibility assertions
  - response contract tests
- Rollout plan: dual-support until frontend calls are migrated
- Done checklist: no functional regression in tasks pages
- Metrics snapshot updated

## Phase 6: Partial Fragment Batch 2 (Project Chat/Activity Fragments)
- Scope:
  - task/project chat partial message and activity fragment routes
- Deliverables:
  - JSON/inertia partial payload responses replacing Blade fragments where safe
- Dependency removals:
  - further reduce partial Blade fragment count
- Test plan:
  - SSE headers untouched
  - attachment/download routes unchanged
  - contract tests on incremental loading
- Rollout plan: incremental module-by-module
- Done checklist: chat/activity parity maintained
- Metrics snapshot updated

## Phase 7: Wrapper Burn-Down
- Scope:
  - all routes still using `ConvertAdminViewToInertia`
- Deliverables:
  - route-by-route removal of wrapper from groups where no longer needed
  - direct controller Inertia rendering
- Dependency removals:
  - wrapper-dependent routes aggressively reduced
- Test plan:
  - parity suites + smoke suite
- Rollout plan: remove wrapper from one portal/module slice at a time
- Done checklist:
  - wrapper near zero (temporary exceptions explicitly listed)
- Metrics snapshot updated

## Phase 8: Blade UI Cleanup
- Scope:
  - converted UI blade views and route references
- Deliverables:
  - delete converted Blade UI files
  - verify no `ViewNotFound` risk for kept routes
- Dependency removals:
  - full Blade UI pages count reaches zero
- Test plan:
  - run blade audit
  - run route list + tests
- Rollout plan: delete in validated batches
- Done checklist:
  - zero full Blade UI pages
  - Blade only for allowed templates/fragments
- Metrics snapshot updated

## Phase 9: Non-UI Endpoint Lock + Safety Validation
- Scope:
  - payment callbacks, uploads, streams, downloads, redirects
- Deliverables:
  - explicit no-conversion guardrails in docs/tests
  - transport/header contract tests
- Dependency removals: none
- Test plan:
  - callback/upload/pdf/sse contract suites
  - smoke suite
- Rollout plan: no endpoint behavior changes
- Done checklist:
  - all critical non-UI contracts unchanged
- Metrics snapshot updated

## Phase 10: Final Certification
- Scope: full application
- Deliverables:
  - final migration report
  - risk register validation
  - go/no-go checklist
- Dependency removals:
  - final wrapper and blade UI counts validated
- Test plan:
  - full test suite
  - production build
  - route stability checks
- Rollout plan: staged release with rollback proof
- Done checklist:
  - all no-break checks PASS
  - final GO recommendation issued
