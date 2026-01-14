# IMPLEMENTATION_NOTES

## Existing System Summary

Framework & "installed apps"
- This codebase is Laravel 11 (not Django); there is no `INSTALLED_APPS`.
- Providers/middleware are configured in `bootstrap/app.php`; only `AuthServiceProvider` is explicitly registered and middleware aliases include `admin`, `employee`, `salesrep`, `client`, and `client.block`.

Core models & relationships
- `app/Models/Project.php` owns project fields (name, type, status, dates, budgets, currency) and relations to `Customer`, `Order`, `Subscription`, `Invoice`, `ProjectTask`, `Employee` (pivot `employee_project`), and `SalesRepresentative` (pivot `project_sales_representative`).
- Project members are stored in:
  - `projects.customer_id` (customer)
  - `employee_project` pivot (employees)
  - `project_sales_representative` pivot (sales reps)
  - `projects.sales_rep_ids` JSON (also populated on create; duplicates pivot storage)
- `app/Models/ProjectTask.php` includes `assigned_type`/`assigned_id`, `assignee_id`, `customer_visible`, `progress`, and `created_by`. Start/due dates are locked after creation via model `booted()` hook.
- `app/Models/User.php` uses a `role` string with helpers (`isAdmin`, `isClient`, `isSales`, `isSupport`) and links to `Customer`. Employees are a separate Authenticatable model (`app/Models/Employee.php`), and sales reps are in `app/Models/SalesRepresentative.php`.

Permissions, roles, and access logic
- Middleware enforces role access (`EnsureAdmin`, `EnsureEmployee`, `EnsureSalesRep`, `EnsureClient`).
- Policies:
  - `ProjectPolicy::view`: admins can view all projects; clients can view their own; employees/sales reps can view projects they are assigned to.
  - `ProjectPolicy::createTask`: same as `view` (so all viewers can create tasks).
  - `ProjectTaskPolicy::view`: admins can view all; clients only see `customer_visible` tasks; employees/sales reps can see tasks if on the project or assigned to the task (plus `customer_visible` for employees under employee guard).
  - `ProjectTaskPolicy::update`: same as `view`; `delete` disallows completed/done tasks.

Task creation/editing flows
- Admins create tasks in two ways:
  - During project creation in `Admin/ProjectController@store` (required `tasks[]` with assignee parsing).
  - On the project show page via `Admin/ProjectTaskController@store`.
- Employees and sales reps can add tasks from their project show pages (`Employee/ProjectTaskController@store`, `SalesRep/ProjectTaskController@store`).
- Clients can add tasks (`Client/ProjectTaskController@store`) with `customer_visible = true` and same-day start/due dates.
- Updates happen through role-specific controllers; all call `authorize('update', $task)`. Dates are rejected on update and enforced immutable by the model hook.

Task visibility by role (effective behavior)
- Admin: sees all tasks (policy + admin view).
- Client: only tasks where `customer_visible = true` (controller query + policy).
- Employee (employee guard): only tasks assigned to the employee OR `customer_visible` (controller query), even if the employee is on the project.
- Sales rep: only tasks assigned to the rep OR `customer_visible` (controller query), even if the rep is on the project.
- Admin show view additionally filters tasks by role if the viewer is not admin, but admin routes typically use admin middleware.

Who can add/edit tasks today
- Add: anyone who can view the project (admins, assigned employees, assigned sales reps, the project’s customer).
- Edit: anyone who can view the task (admins; clients only if `customer_visible`; employees/sales reps if assigned or on the project).
- Delete: same as edit, but blocked for completed/done tasks.

Budget & currency handling
- Project fields include `total_budget`, `initial_payment_amount`, `currency` (free-text), plus legacy `budget_amount`, `planned_hours`, `hourly_cost`, `actual_hours`.
- `Admin/ProjectController@store` validates and stores `total_budget`, `initial_payment_amount`, `currency` (uppercased), and generates an initial invoice using those values.
- Profitability metrics in `Admin/DashboardController` and `Admin/ProjectController::financials()` use `budget_amount` + cost calculations; no `budget_remaining` or sales-rep-specific deductions exist.
- Global currency setting is managed in Admin Settings (currently `BDT`/`USD`), but project currency is free-text in forms.

Admin customizations
- Admin UI is custom Blade views under `resources/views/admin/*` (no Laravel Nova).
- Projects list includes filters and status badges; project show page includes inline task editing and a task creation form with assignee selection.

## Existing Billing & Invoice Flow

Project model & financial fields
- `app/Models/Project.php` stores project financials: `total_budget`, `initial_payment_amount`, `currency`, plus legacy `budget_amount`, `planned_hours`, `hourly_cost`, `actual_hours`.
- Project invoices relate via `projects.id` -> `invoices.project_id` (added in `database/migrations/2026_02_15_000320_add_project_links_to_invoices.php`).
- `ProjectController@store` creates an initial invoice using `initial_payment_amount` and `currency` (invoice `type = project_initial_payment`).

Invoice model & relationships
- `app/Models/Invoice.php` has `customer_id`, optional `subscription_id`, optional `project_id`, `status`, `issue_date`, `due_date`, `paid_at`, `overdue_at`, `subtotal`, `late_fee`, `total`, `currency`, and `type`.
- `Invoice` belongs to `Customer`, `Subscription`, and `Project`. `Customer` has many `invoices` and `projects`.
- Invoice items are in `app/Models/InvoiceItem.php` and linked by `invoice_id`.

How invoices are currently generated
- Subscription billing: `app/Console/Commands/RunBillingCycle.php` runs `billing:run`, which calls `BillingService::generateInvoiceForSubscription()` for active subscriptions with `next_invoice_at <= today`.
- Project initial invoice: `app/Http/Controllers/Admin/ProjectController.php@store` creates an invoice immediately when a project is created.
- Client order flow: `app/Http/Controllers/Client/OrderController.php@store` creates a subscription, invoice, and order in a transaction.

Due dates & payment status handling
- Subscription invoices: due date logic in `BillingService::resolveDueDate()` (first subscription invoice due date = issue date; later invoices add `invoice_due_days`).
- Project initial invoices: due date = issue date + `invoice_due_days` from settings.
- Client order invoices: due date = issue date (no extra days in review/store flow).
- Overdue status: `StatusUpdateService::updateInvoiceOverdueStatus()` marks unpaid invoices overdue when `due_date < today`.
- Payment updates: `PaymentService` sets `status = paid`, `paid_at`, and triggers notifications/commission.

Invoice schedules / cron
- Laravel scheduler in `routes/console.php` runs `billing:run` daily (plus other recurring tasks).
- `/cron/billing` route (CronController) triggers `billing:run` via HTTP when a valid token is provided.
- `cron:monitor` is scheduled hourly to check cron health.

## Maintenance Plans & Billing (ProjectMaintenance)

Maintenance lifecycle
- Model: `app/Models/ProjectMaintenance.php` with `project_id`, `customer_id`, `title`, `amount`, `currency`, `billing_cycle`, `start_date`, `next_billing_date`, `last_billed_at`, `status`, `auto_invoice`, `sales_rep_visible`, `created_by` (soft deletes enabled).
- Create: optional maintenance rows during project creation or via `Admin/ProjectMaintenanceController` UI.
- Edit: maintenance edit page supports pausing/cancelling; cancelled plans cannot be reactivated.
- Delete: project deletion soft-deletes related maintenance records (model delete hook).

Billing rules (automatic)
- Engine: `app/Services/MaintenanceBillingService.php`.
- Eligible if `status = active`, `auto_invoice = true`, and `next_billing_date <= today`.
- Issue date = `next_billing_date` (or `start_date` fallback); due date = issue date + `invoice_due_days`.
- Invoice: `type = project_maintenance`, linked via `maintenance_id`, with one line item `Maintenance - {title} ({billing_cycle})`.
- Currency: maintenance currency if set, else project currency, else global setting.
- Idempotent: skips if an invoice already exists within the current billing window; still advances `next_billing_date`.
- Advances: monthly uses `addMonthNoOverflow`, yearly uses `addYear`; updates `last_billed_at`.

Cron setup
- `RunBillingCycle` runs maintenance billing alongside subscription billing; scheduled daily via `billing:run`.
- `/cron/billing` endpoint also triggers maintenance billing when the cron token is valid.

Invoice linkage & visibility
- `invoices.maintenance_id` links maintenance invoices; `ProjectMaintenance::invoices()` exposes history.
- Admin invoices index supports filtering by `maintenance_id`.
- Project show pages surface maintenance plans and invoice counts; sales rep/client views show read-only maintenance info.

## Full Relationship & Database Audit (Pre-Fix)

Scope reviewed (code + schema)
- Models: all under `app/Models`, with emphasis on Project, ProjectTask, ProjectMaintenance, Invoice, Customer, User, Employee, SalesRepresentative.
- Migrations: all under `database/migrations`, with focus on projects, tasks, invoices, maintenance, employees, sales reps, and pivot tables.
- Seeders: `database/seeders/DatabaseSeeder.php`, `database/seeders/DemoDataSeeder.php`.
- Policies: `app/Policies/ProjectPolicy.php`, `app/Policies/ProjectTaskPolicy.php`, `app/Policies/ProjectTaskSubtaskPolicy.php`.
- Controllers/views: project/task/maintenance flows in admin, client, employee, sales rep areas; invoice filters; task assignment views.
- Raw DB usage: services/controllers with `DB::transaction`, `DB::table`, `selectRaw`, and migrations with `DB::statement`.

Relationship map (by model)
- Project (`projects`)
  - PK: `id`.
  - FKs: `customer_id` (required, customers, cascade), `order_id` (nullable, orders, nullOnDelete), `subscription_id` (nullable, subscriptions, nullOnDelete), `advance_invoice_id` (nullable, invoices, nullOnDelete), `final_invoice_id` (nullable, invoices, nullOnDelete).
  - Relations: belongsTo `Customer`, `Order`, `Subscription`, `Invoice` (advance/final); hasMany `ProjectTask`, `ProjectMaintenance`, `Invoice`; belongsToMany `Employee` via `employee_project`; belongsToMany `SalesRepresentative` via `project_sales_representative` (pivot `amount`).
  - Notes: `sales_rep_ids` JSON duplicates pivot mapping; soft deletes added by both `2026_01_13_232314_add_soft_deletes_to_projects_table.php` and `2026_02_20_000480_add_deleted_at_to_projects_table.php` (guarded, but duplicated intent).
- ProjectTask (`project_tasks`)
  - PK: `id`.
  - FKs: `project_id` (required, projects, cascade), `assignee_id` (nullable, users, nullOnDelete), `created_by` (nullable, users, nullOnDelete).
  - Relations: belongsTo `Project`, `User` (assignee); hasMany `ProjectTaskMessage`, `ProjectTaskAssignment`, `ProjectTaskActivity`, `ProjectTaskSubtask`.
  - Notes: `assigned_type` + `assigned_id` are pseudo-polymorphic (no FK), and `project_task_assignments` mirrors assignments; `customer_visible` gates client visibility.
- ProjectMaintenance (`project_maintenances`)
  - PK: `id`.
  - FKs: `project_id` (required, projects, restrictOnDelete), `customer_id` (required, customers, cascade), `created_by` (nullable, users, nullOnDelete).
  - Relations: belongsTo `Project`, `Customer`, `User` (creator); hasMany `Invoice` via `maintenance_id`.
  - Notes: `sales_rep_visible` boolean column added in `2026_02_20_000470_add_sales_rep_visible_to_project_maintenances_table.php`; soft deletes are in the create table plus a separate guarded migration (`2026_01_13_232024_add_soft_deletes_to_project_maintenances_table.php`).
- Invoice (`invoices`)
  - PK: `id`.
  - FKs: `customer_id` (required, customers, cascade), `subscription_id` (nullable, subscriptions, nullOnDelete), `project_id` (nullable, projects, nullOnDelete), `maintenance_id` (nullable, project_maintenances, nullOnDelete).
  - Relations: belongsTo `Customer`, `Subscription`, `Project`, `ProjectMaintenance`; hasMany `InvoiceItem`, `AccountingEntry`, `PaymentAttempt`, `PaymentProof`, `Order`, `ClientRequest`.
  - Notes: `type` column added in `2026_02_15_000320_add_project_links_to_invoices.php`.
- Customer (`customers`)
  - PK: `id`.
  - FKs: `referred_by_affiliate_id` (nullable, affiliates, set null). `default_sales_rep_id` is present (nullable) but added as `unsignedBigInteger` without FK.
  - Relations: hasMany `User`, `Subscription`, `Invoice`, `Order`, `Project`, `ProjectMaintenance`, `ClientRequest`, `SupportTicket`, `AccountingEntry`, `PaymentAttempt`, `PaymentProof`; belongsTo `SalesRepresentative` (default sales rep) and `Affiliate` (referral); hasOne `Affiliate`.
- User (`users`)
  - PK: `id`.
  - FKs: `customer_id` (nullable, customers, nullOnDelete).
  - Relations: belongsTo `Customer`; hasMany `SupportTicket`, `ClientRequest`, `Order`; hasOne `Employee`.
- Employee (`employees`)
  - PK: `id`.
  - FKs: `user_id` (nullable, users, nullOnDelete), `manager_id` (nullable, employees, nullOnDelete).
  - Relations: belongsTo `User`, belongsTo `Employee` (manager), hasMany `Employee` (reports), hasMany `EmployeeCompensation`, `Timesheet`, `LeaveRequest`, `PayrollItem`; hasOne active compensation; belongsToMany `Project` via `employee_project`.
- SalesRepresentative (`sales_representatives`)
  - PK: `id`.
  - FKs: `user_id` (nullable, users, nullOnDelete), `employee_id` (nullable, employees, nullOnDelete).
  - Relations: belongsTo `User`, belongsTo `Employee`; hasMany `CommissionEarning`, `CommissionPayout`; belongsToMany `Project` via `project_sales_representative`.

Pivot and task-related tables
- `employee_project`: `project_id` + `employee_id` (both required, FK cascade), unique on pair.
- `project_sales_representative`: `project_id` + `sales_representative_id` (both required, FK cascade), `amount` decimal, unique on pair.
- `project_task_assignments`: `project_task_id` (FK cascade), `assignee_type` + `assignee_id` (no FK), unique on `(project_task_id, assignee_type, assignee_id)`.
- `project_task_messages`, `project_task_activities`, `project_task_subtasks`: all keyed by `project_task_id` FK with cascade delete; author/actor IDs are stored without FK because of role-based types.

Controllers and views referencing relations (selected)
- Controllers: `Admin/ProjectController` (employees, sales reps, tasks, maintenances, invoices), `Client/ProjectController` (maintenances), `SalesRep/ProjectController` (sales reps + maintenances), `Employee/ProjectController` (employees pivot), `ProjectTaskViewController` (project employees/sales reps for assignees), `Admin/InvoiceController` (maintenance filter).
- Views: `resources/views/admin/projects/*` (employees, sales reps, maintenances), `resources/views/client/projects/*` and `resources/views/rep/projects/show.blade.php` (maintenances), `resources/views/admin/sales-reps/show.blade.php` (projects and task counts).

Raw DB queries and direct SQL usage
- Services: `app/Services/MaintenanceBillingService.php`, `app/Services/BillingService.php` (selectRaw), `app/Services/CommissionService.php`, `app/Services/PayrollService.php`, `app/Services/MilestoneInvoiceService.php`, `app/Services/AutomationStatusService.php`.
- Controllers: `Admin/DashboardController.php`, `Admin/SalesRepresentativeController.php`, `Admin/Hr/EmployeeController.php`, `Admin/CommissionPayoutController.php`, `Admin/LicenseController.php`, `Client/OrderController.php`, `Api/LicenseVerificationController.php`.
- Models: `app/Models/Order.php` uses `selectRaw` for sequence generation.
- Migrations: `2026_02_15_000320_add_project_links_to_invoices.php`, `2026_02_15_000330_create_project_assignments_tables.php` use `DB::statement`; `2026_02_20_000410_create_project_task_assignments_table.php` uses `DB::table` for backfill.

## Database Structure Verification (Pre-Fix)

Migration state
- `php artisan migrate:status` reports all migrations ran through `2026_02_20_000480_add_deleted_at_to_projects_table.php`.

Live schema validation (key tables)
- `projects`: FK constraints to `customers`, `orders`, `subscriptions`, and `invoices` (advance/final) are present with ON DELETE SET NULL for invoice/order/subscription and CASCADE for customer; `deleted_at` exists.
- `project_tasks`: FK constraints on `project_id`, `assignee_id`, `created_by` present; `assigned_type` + `assigned_id` remain unbounded (expected pseudo-polymorphic).
- `project_task_assignments`, `project_task_messages`, `project_task_activities`, `project_task_subtasks`: FK on `project_task_id` present; author/actor/assignee IDs have no FKs due to type-based routing.
- `project_maintenances`: FK constraints to `projects`, `customers`, and `users` (created_by) present; `deleted_at` exists; `project_id` has implicit RESTRICT (no ON DELETE action listed).
- `invoices`: FK constraints to `customers`, `subscriptions`, `projects`, `project_maintenances` present; ON DELETE SET NULL for optional relations.
- Pivots: `employee_project` and `project_sales_representative` have FK constraints with CASCADE and uniqueness on the pair.

Schema mismatches / missing constraints
- `customers.default_sales_rep_id` has only an index; there is no FK to `sales_representatives`.
- `orders.sales_rep_id` and `subscriptions.sales_rep_id` have indexes only; no FK to `sales_representatives`.
- Duplicate soft-delete migrations ran for `projects` and `project_maintenances` (both tables have a single `deleted_at`; migrations are redundant but not conflicting).
- Competing role defaults: `users.role` defaults to `master_admin` (from `2025_01_05_000001_add_role_to_users_table.php`), while `2025_12_28_063230_add_role_and_customer_to_users_table.php` would default to `client` only if the column didn’t already exist; final default remains `master_admin`.
- Duplicate relationship storage: `projects.sales_rep_ids` JSON column exists alongside the `project_sales_representative` pivot (both store sales rep linkage).

Orphaned record checks (live DB)
- No orphans detected for projects/tasks/maintenances/invoices/pivots/employee/sales-rep linkages (all checks returned zero).

## Detected Relationship Issues

Relationship definition vs usage mismatches
- Task actor/author type naming is inconsistent: `salesrep` (no underscore) is used by `TaskActivityLogger`, `ProjectTaskViewController`, and `ProjectTaskChatController`, while Sales Rep task controllers write `actor_type = sales_rep`. `ProjectTaskActivity::actorName()` and `ProjectTaskMessage::authorName()` only recognize `salesrep`, so `sales_rep` activity entries will not resolve to `salesRepActor` for name/label lookup.
- Task assignment sources are duplicated: `project_tasks.assigned_type/assigned_id` (used by policies and legacy logic) and `project_task_assignments` (used by views and assignee UI). There is no automatic consistency enforcement between the two sources after the backfill migration.
- Legacy `project_tasks.assignee_id` (User FK) coexists with employee/sales-rep assignment via `assigned_type/assigned_id` and the assignments table. `ProjectTask::assignee()` only resolves a `User`, so tasks assigned to employees/sales reps will not map via this relation.

Relations not scoped to type (risk of mis-association)
- `ProjectTaskAssignment::employee()` and `salesRep()` both resolve off `assignee_id` without filtering on `assignee_type`. If IDs overlap between employees and sales reps, eager-loading both can attach the wrong model.
- `ProjectTaskActivity` and `ProjectTaskMessage` relations (`userActor`, `employeeActor`, `salesRepActor`, `userAuthor`, `employeeAuthor`, `salesRepAuthor`) are not scoped by `actor_type`/`author_type`. The correct relation depends on runtime type values and can be wrong if IDs overlap.

Missing or asymmetric relations / constraints affecting Eloquent integrity
- `Customer::defaultSalesRep()`, `Order::salesRep()`, and `Subscription::salesRep()` map to `sales_rep_id`/`default_sales_rep_id`, but the live schema does not define FKs for these columns. The relations work at the ORM layer but are not enforced by DB constraints.
- `projects.sales_rep_ids` JSON column duplicates the `project_sales_representative` pivot relation. Both are written in code, but only the pivot is used for relationship queries, creating potential drift.

## Data Integrity & Logic Validation (Pre-Fix)

Integrity checks (live DB)
- No orphaned records found for projects, tasks, maintenances, invoices, invoice items, employees, sales reps, or pivots (all counts = 0).
- No project-task assignment drift detected: `project_task_assignments` is empty and no `project_tasks.assigned_type` values exist in current data, so alignment checks are not yet applicable.
- No task assignments outside project membership (employees/sales reps) detected (all counts = 0).
- No mismatches between maintenance/customer or invoice/customer/project links detected (all counts = 0).
- `projects.sales_rep_ids` JSON is not populated in current data (0 projects checked), so JSON vs pivot drift could not be validated on live records.

Fix strategy (if integrity issues appear)
- Prefer data repair over deletion: align task assignments by inserting missing rows in `project_task_assignments` to match `project_tasks.assigned_type/assigned_id`, or update assigned fields to reflect the canonical assignments list.
- For project membership mismatches, prefer adding the employee/sales-rep pivot entry or reassigning the task to a valid project member.
- For invoice/maintenance/customer mismatches, update the child record’s `customer_id`/`project_id` to match the parent entity.
- For `sales_rep_ids` JSON drift, treat the pivot as source-of-truth and rebuild JSON from pivot (keep legacy column for backward compatibility).
- Only soft-delete records when repair is not possible; log any such decisions.

## Verified Existing Behavior (Pre-Change)

Task visibility per role (current behavior)
- Admin: sees all tasks for a project (policy allows all; admin views don’t filter).
- Customer: sees only tasks where `customer_visible = true` (controller query + policy).
- Employee: sees tasks assigned to them OR `customer_visible` (controller query in `Employee/ProjectController@show`), even if assigned to the project.
- Sales Rep: sees tasks assigned to them OR `customer_visible` (controller query in `SalesRep/ProjectController@show`), even if assigned to the project.

Task creation/editing per role (current behavior)
- Admin: can add tasks (project show + project create), edit/update any visible task.
- Employee: can add tasks on assigned projects (policy uses project membership), edit tasks they can view.
- Sales Rep: can add tasks on assigned projects (policy uses project membership), edit tasks they can view.
- Customer: can add tasks (project show), edit tasks only if `customer_visible = true` (policy).
- All task updates block changes to `start_date`/`due_date` (controller guard + model hook).

Budget storage & usage (current behavior)
- Stored fields: `total_budget`, `initial_payment_amount`, `currency` (free text), and legacy `budget_amount`.
- `total_budget` + `initial_payment_amount` are required on project create; initial invoice is created using those values.
- Profitability metrics and `financials()` use legacy `budget_amount` with hourly cost math; no remaining-budget calculation exists.

Currency handling (current behavior)
- Project currency is stored as a free-text `string` (max 10), uppercased on create.
- Settings enforce a global currency (currently `BDT`/`USD`), but project currency is not constrained by that list.

Project members (current behavior)
- Customer: `projects.customer_id`.
- Employees: `employee_project` pivot (many-to-many).
- Sales reps: `project_sales_representative` pivot (many-to-many).
- Legacy duplication: `projects.sales_rep_ids` JSON is still written on create and not used consistently elsewhere.

## Phase 7 - Post-Fix Summary

Before vs after schema
- Added FK constraints for sales-rep references: `customers.default_sales_rep_id`, `orders.sales_rep_id`, `subscriptions.sales_rep_id` now point to `sales_representatives.id` (null on delete) via `2026_02_20_000490_add_sales_rep_foreign_keys_to_core_tables.php`.
- Normalized sales-rep type values across task-related tables (`salesrep` -> `sales_rep`) via `2026_02_20_000500_normalize_sales_rep_type_values.php`.

Fixed relations / consistency changes
- Task activity and message actor/author relations are now scoped by type (prevents mis-association when IDs overlap).
- Task assignment relations are scoped by `assignee_type`, and labels support both `sales_rep` and legacy `salesrep`.
- Controllers and activity logger now emit `sales_rep` consistently for new records.

Data corrections applied
- Type normalization migration ran; current data shows no remaining `salesrep` entries in task activity/message/assignment/task tables.
- No orphan fixes required (integrity checks were clean).

Legacy structures retained
- `projects.sales_rep_ids` JSON retained for backward compatibility (pivot remains canonical).
- `project_tasks.assigned_type/assigned_id` retained alongside `project_task_assignments` (dual assignment sources still exist).

Testing status
- `php artisan test` has known failures (ProjectTask immutability progress assertion, project currency validation, task activity/chat/link, upload task validation). These need follow-up in Phase 6 fixes if required.
