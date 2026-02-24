# Phase 7 Closeout Checklist (Go/No-Go)

- Updated: 2026-02-24
- Scope of latest batch:
  - `admin.project-maintenances.*` moved to direct Inertia + Blade removal
  - `admin.sales-reps.index/create/edit` moved to direct Inertia + Blade removal

## Batch Evidence

- `app/Http/Controllers/Admin/ProjectMaintenanceController.php`
  - `index/create/edit/show` now return direct Inertia pages
- `app/Http/Controllers/Admin/SalesRepresentativeController.php`
  - `index/create/edit` now return direct Inertia pages (`show` unchanged for safety)
- Added React pages:
  - `resources/js/react/Pages/Admin/ProjectMaintenances/Index.jsx`
  - `resources/js/react/Pages/Admin/ProjectMaintenances/Form.jsx`
  - `resources/js/react/Pages/Admin/ProjectMaintenances/Show.jsx`
  - `resources/js/react/Pages/Admin/SalesReps/Index.jsx`
  - `resources/js/react/Pages/Admin/SalesReps/Form.jsx`
- Added parity coverage:
  - `tests/Feature/ProjectMaintenancesUiParityTest.php`
  - `tests/Feature/SalesRepsUiParityTest.php`
- Removed Blade views:
  - `resources/views/admin/project-maintenances/index.blade.php`
  - `resources/views/admin/project-maintenances/create.blade.php`
  - `resources/views/admin/project-maintenances/edit.blade.php`
  - `resources/views/admin/project-maintenances/show.blade.php`
  - `resources/views/admin/sales-reps/index.blade.php`
  - `resources/views/admin/sales-reps/create.blade.php`
  - `resources/views/admin/sales-reps/edit.blade.php`

## Mandatory Gate Results

- `php artisan test` => PASS (`461 passed`)
- `npm run build` => PASS
- `php artisan config:cache` => PASS
- `php artisan route:cache` => PASS
- `php artisan ui:audit-blade --write` => PASS
  - `total_views: 121`
  - `referenced_views: 118`
  - `unreferenced_views: 3`
  - `cleanup_candidates: 0`
- Route signature (method+uri) drift vs baseline:
  - added: `GET|HEAD|__ui/react-sandbox`
  - removed: none

## Phase 7 Global Go/No-Go

- GO: Functional regression gates are green for current batch.
- NO-GO (full Phase 7 decommission): there are still many referenced Blade-backed pages (`118`).
- NO-GO trigger reasons:
  - Legacy bridge middleware still required for remaining route-backed Blade pages.
  - Remaining admin Blade-heavy modules include projects, HR, invoices, dashboard, customers, expenses, and `admin.sales-reps.show`.

## Remaining High-Volume Legacy Admin Views (sample)

- `admin.projects.*`
- `admin.hr.*`
- `admin.invoices.*`
- `admin.sales-reps.*`
- `admin.expenses.*`
- `admin.customers.show`

## Release Tag Prep (when GO is reached)

1. Verify clean safety gates:
   - `php artisan test`
   - `npm run build`
   - `php artisan config:cache`
   - `php artisan route:cache`
2. Verify Blade audit:
   - `php artisan ui:audit-blade --write`
3. Commit:
   - `git add -A`
   - `git commit -m "phase7: decommission legacy blade batch (inertia parity + tests)"`
4. Tag:
   - `git tag -a phase7-batch-<N> -m "Phase 7 batch <N> validated"`
5. Push:
   - `git push origin <branch-name>`
   - `git push origin phase7-batch-<N>`

## Next Recommended Batch

1. `admin.invoices.index/create/show` direct Inertia migration with contract tests before payment actions.
2. `admin.projects` read-only pages (`index`/`show`) with parity tests before form actions.
