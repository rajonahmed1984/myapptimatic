# React + Inertia Full UI Migration Plan

## Scope Guardrails
- Convert only GET UI pages to direct `Inertia::render(...)` in controllers.
- Keep non-UI routes unchanged: callbacks/webhooks, uploads, streams/SSE, downloads/PDF, utility redirects/responses.
- Preserve auth/guards, policies, throttles, flash/errors, status codes and payload shapes.

## Phase 1 — Baseline & Route Evidence
**Scope**
- Route inventory, Blade candidate discovery, UI/non-UI classification.

**Deliverables**
- Generate `storage/app/routes-latest.json`.
- Generate `storage/app/blade-route-candidates.json` and `storage/app/blade-ui-get-candidates.json`.
- Generate classification + metrics reports under `storage/app/migration-reports/`.

**Dependency removal**
- None.

**Test plan**
- Verify report files exist and parse as JSON.
- Spot-check known routes across classification buckets.

**Rollout**
- Internal only.

**Definition of Done**
- Baseline metrics snapshot committed.

**Metrics snapshot**
- `total_ui_page_get_routes`
- `remaining_full_blade_ui_pages`
- `remaining_partial_blade_fragments`
- `wrapper_dependent_routes`

## Phase 2 — Auth Pages (Phase A)
**Scope**
- `register`, `password.request`, `password.reset`, `admin.password.request`, `project-client.login`.

**Deliverables**
- Controllers return `Inertia::render(...)` directly.
- React pages under `resources/js/react/Pages/Auth/*` with validation and flash parity.
- Route/guard parity preserved.

**Dependency removal**
- Remove wrapper dependency for these routes.

**Test plan**
- Manual: load pages, submit invalid forms, success redirects.
- Automated: feature tests for status, redirects, session errors.

**Rollout**
- Direct rollout (no flag) unless portal-specific regression appears.

**Definition of Done**
- All 5 routes render React via Inertia.
- No Blade UI rendering for these routes.

**Metrics snapshot**
- `remaining_full_blade_ui_pages` decreases or remains unchanged if already migrated.

## Phase 3 — Admin Standalone Pages (Phase B-1)
**Scope**
- `admin.dashboard`, `admin.customers.show`, `admin.hr.employees.show`, `admin.invoices.client-view`.

**Deliverables**
- Controller-level direct Inertia renders.
- React pages in `Pages/Admin/...`.
- Shared table/card/section primitives reused.

**Dependency removal**
- Remove wrapper dependency for these route names.

**Test plan**
- Permission checks (authorized vs unauthorized).
- Data parity against previous Blade output.

**Rollout**
- Route-by-route rollout.

**Definition of Done**
- 4 routes fully Inertia-driven.

**Metrics snapshot**
- Wrapper count and full-Blade count burn-down.

## Phase 4 — Task Detail Shells (Phase C-1)
**Scope**
- `admin/client/employee/rep` task detail show routes.

**Deliverables**
- Convert page shell to React.
- Keep existing SSE/chat/activity endpoints unchanged.

**Dependency removal**
- Remove full Blade page dependency for task detail route set.

**Test plan**
- Task load, permissions by portal, tab navigation, chat visibility.

**Rollout**
- Portal-by-portal.

**Definition of Done**
- All 4 show routes serve Inertia pages.

**Metrics snapshot**
- `remaining_full_blade_ui_pages` should hit zero.

## Phase 5 — Tasks Index Partials (Phase D-1)
**Scope**
- Tasks index partial fragments across admin/client/employee/rep.

**Deliverables**
- Replace Blade partial responses with Inertia partial reload or JSON + React rendering.

**Dependency removal**
- Decrease Blade partial dependencies for task index.

**Test plan**
- Filter/search/sort/pagination parity.

**Rollout**
- Progressive by portal.

**Definition of Done**
- Legacy partial fallback no longer used for task index.

**Metrics snapshot**
- Partial fragment count burn-down.

## Phase 6 — Chat & Activity UI Fragments (Phase D-2)
**Scope**
- Project chat/task chat/activity Blade fragments.

**Deliverables**
- React message list/composer/actions.
- Preserve existing SSE endpoints and message APIs.

**Dependency removal**
- Remove Blade fragment rendering from chat UI path.

**Test plan**
- Reply/pin/react/edit/delete/attach, read-state and streaming updates.

**Rollout**
- Admin first, then client/employee/rep.

**Definition of Done**
- No Blade fragment rendering in chat page UI.

**Metrics snapshot**
- Partial fragment count decreases substantially.

## Phase 7 — Wrapper Burn-Down (Platform)
**Scope**
- Routes still using `ConvertAdminViewToInertia`.

**Deliverables**
- Replace wrapper usage with direct controller Inertia render for migrated UI pages.
- Maintain explicit allowlist for temporary legacy endpoints.

**Dependency removal**
- Wrapper count aggressively reduced.

**Test plan**
- Route middleware diff checks.
- Smoke test critical navigation.

**Rollout**
- Chunked removal by module.

**Definition of Done**
- Wrapper near-zero and tracked with allowlist.

**Metrics snapshot**
- `wrapper_dependent_routes` trend.

## Phase 8 — Blade Cleanup
**Scope**
- Remove unused converted page Blade files.

**Deliverables**
- Delete/retire Blade UI files no longer referenced.
- Keep only allowed Blade categories (emails/non-UI approved).

**Dependency removal**
- Full Blade UI pages removed.

**Test plan**
- Dead-file reference checks.

**Rollout**
- Safe delete with route/controller verification.

**Definition of Done**
- No converted UI route references deleted Blade files.

**Metrics snapshot**
- Remaining Blade UI page count = 0.

## Phase 9 — Regression & Hardening
**Scope**
- Auth, permissions, session flash/errors, serialization and performance.

**Deliverables**
- Regression checklist execution.
- Route-level smoke script.

**Dependency removal**
- Remove temporary shims/feature flags.

**Test plan**
- Full feature tests + targeted portal smoke tests.

**Rollout**
- Controlled release window.

**Definition of Done**
- No blocker regressions.

**Metrics snapshot**
- Final pre-close metrics report.

## Phase 10 — Finalization
**Scope**
- Compliance closeout and documentation.

**Deliverables**
- Final migration report JSON.
- Final checklist showing zero full Blade UI pages.

**Dependency removal**
- Wrapper usage limited to explicitly approved exceptions only.

**Test plan**
- Repeat baseline scripts and compare against Phase 1.

**Rollout**
- Permanent state.

**Definition of Done**
- Migration objective achieved and documented.

**Metrics snapshot**
- `remaining_full_blade_ui_pages = 0`
- `remaining_partial_blade_fragments` minimized/approved
- `wrapper_dependent_routes` near zero and justified