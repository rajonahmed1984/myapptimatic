# Phase 7 Closeout Checklist (Go/No-Go)

- Updated: 2026-02-24
- Scope of latest batch:
  - `admin.project-maintenances.*` moved to direct Inertia + Blade removal
  - `admin.sales-reps.index/create/edit/show` moved to direct Inertia + Blade removal
  - `admin.invoices.index/create/show` moved to direct Inertia + Blade removal
  - `admin.projects.index/show` moved to direct Inertia + Blade removal
  - `admin.projects.overheads.index` moved to direct Inertia + Blade removal
  - `admin.income.dashboard` moved to direct Inertia + Blade removal
  - `admin.hr.leave-types.index` moved to direct Inertia + Blade removal

## Batch Evidence

- `app/Http/Controllers/Admin/ProjectMaintenanceController.php`
  - `index/create/edit/show` now return direct Inertia pages
- `app/Http/Controllers/Admin/SalesRepresentativeController.php`
  - `index/create/edit/show` now return direct Inertia pages
- `app/Http/Controllers/Admin/InvoiceController.php`
  - `listByStatus/create/show` now return direct Inertia pages
- `app/Http/Controllers/Admin/ProjectController.php`
  - `index/show` now return direct Inertia pages
- `app/Http/Controllers/Admin/ProjectOverheadController.php`
  - `index` now returns direct Inertia page
- `app/Http/Controllers/Admin/IncomeController.php`
  - `dashboard` now returns direct Inertia page
- `app/Http/Controllers/Admin/Hr/LeaveTypeController.php`
  - `index` now returns direct Inertia page
- Added React pages:
  - `resources/js/react/Pages/Admin/ProjectMaintenances/Index.jsx`
  - `resources/js/react/Pages/Admin/ProjectMaintenances/Form.jsx`
  - `resources/js/react/Pages/Admin/ProjectMaintenances/Show.jsx`
  - `resources/js/react/Pages/Admin/SalesReps/Index.jsx`
  - `resources/js/react/Pages/Admin/SalesReps/Form.jsx`
  - `resources/js/react/Pages/Admin/SalesReps/Show.jsx`
  - `resources/js/react/Pages/Admin/Invoices/Index.jsx`
  - `resources/js/react/Pages/Admin/Invoices/Create.jsx`
  - `resources/js/react/Pages/Admin/Invoices/Show.jsx`
  - `resources/js/react/Pages/Admin/Projects/Index.jsx`
  - `resources/js/react/Pages/Admin/Projects/Show.jsx`
  - `resources/js/react/Pages/Admin/Projects/Overheads/Index.jsx`
  - `resources/js/react/Pages/Admin/Income/Dashboard.jsx`
  - `resources/js/react/Pages/Admin/Hr/LeaveTypes/Index.jsx`
- Added parity coverage:
  - `tests/Feature/ProjectMaintenancesUiParityTest.php`
  - `tests/Feature/SalesRepsUiParityTest.php`
  - `tests/Feature/AdminInvoicesUiParityTest.php`
  - `tests/Feature/AdminProjectsUiParityTest.php`
  - `tests/Feature/AdminProjectOverheadUiParityTest.php`
  - `tests/Feature/IncomeUiParityTest.php` (dashboard parity assertions added)
  - `tests/Feature/HrLeaveTypesUiParityTest.php`
- Removed Blade views:
  - `resources/views/admin/project-maintenances/index.blade.php`
  - `resources/views/admin/project-maintenances/create.blade.php`
  - `resources/views/admin/project-maintenances/edit.blade.php`
  - `resources/views/admin/project-maintenances/show.blade.php`
  - `resources/views/admin/sales-reps/index.blade.php`
  - `resources/views/admin/sales-reps/create.blade.php`
  - `resources/views/admin/sales-reps/edit.blade.php`
  - `resources/views/admin/sales-reps/show.blade.php`
  - `resources/views/admin/invoices/index.blade.php`
  - `resources/views/admin/invoices/create.blade.php`
  - `resources/views/admin/invoices/show.blade.php`
  - `resources/views/admin/projects/index.blade.php`
  - `resources/views/admin/projects/show.blade.php`
  - `resources/views/admin/projects/overheads/index.blade.php`
  - `resources/views/admin/income/dashboard.blade.php`
  - `resources/views/admin/hr/leave-types/index.blade.php`

## Mandatory Gate Results

- `php artisan test` => PASS (`474 passed`)
- `npm run build` => PASS
- `php artisan config:cache` => PASS
- `php artisan route:cache` => PASS
- `php artisan ui:audit-blade --write` => PASS
  - `total_views: 112`
  - `referenced_views: 109`
  - `unreferenced_views: 3`
  - `cleanup_candidates: 0`
- Route signature (method+uri) drift vs baseline:
  - added: `GET|HEAD|__ui/react-sandbox`
  - removed: none

## Phase 7 Global Go/No-Go

- GO: Functional regression gates are green for current batch.
- NO-GO (full Phase 7 decommission): there are still many referenced Blade-backed pages (`115`).
- NO-GO trigger reasons:
  - Legacy bridge middleware still required for remaining route-backed Blade pages.
  - Remaining admin Blade-heavy modules include projects, HR, dashboard, customers, expenses, and `admin.sales-reps.show`.

## Remaining High-Volume Legacy Admin Views (sample)

- `admin.projects.*`
- `admin.hr.*`
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

1. `admin.projects.create/edit` direct Inertia migration with contract tests for store/update validation + redirects.
2. `admin.dashboard` direct Inertia migration, then `admin.expenses.*` as a separate batch.
