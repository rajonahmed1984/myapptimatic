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
