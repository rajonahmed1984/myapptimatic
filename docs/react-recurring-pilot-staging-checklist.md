# Recurring Expenses React Pilot Staging Checklist

## Scope
- Module:
  - `admin.expenses.recurring.index`
  - `admin.expenses.recurring.create`
  - `admin.expenses.recurring.show`
  - `admin.expenses.recurring.edit`
- Flags:
  - `FEATURE_ADMIN_EXPENSES_RECURRING_INDEX=true`
  - `FEATURE_ADMIN_EXPENSES_RECURRING_CREATE=true`
  - `FEATURE_ADMIN_EXPENSES_RECURRING_SHOW=true`
  - `FEATURE_ADMIN_EXPENSES_RECURRING_EDIT=true`
- Duration: one full release cycle (minimum 7 days)

## Pre-Enable Gate
1. Confirm latest deploy passed:
   - `php artisan test`
   - `npm run build`
   - `php artisan config:cache`
   - `php artisan route:cache`
2. Confirm rollback switches are available in staging env config.
3. Confirm route/cache were rebuilt after env changes.

## Daily Evidence Collection
1. Functional checks (manual):
   - Recurring index list/pagination.
   - Recurring create form validation and submit.
   - Recurring show summary cards and both tables.
   - Recurring edit form validation and submit.
   - Invoice payment modal submit path.
   - Advance payment create path.
   - Resume/stop actions and flash messages.
2. Contract checks (automated):
   - `php artisan test --filter=RecurringExpenseUiParityTest`
   - `php artisan test --filter=RecurringExpenseActionContractParityTest`
3. Error signals:
   - `storage/logs/laravel.log` for `Inertia`, `View`, `ValidationException`, `419`.
   - Browser console errors on React pilot pages.

## Rollback Trigger
- Any regression in status codes, redirects, validation keys, or permission behavior.
- Any payment-path failure from recurring show page.
- Any repeated production-like error in staging logs tied to pilot routes.

## Rollback Steps
1. Set:
   - `FEATURE_ADMIN_EXPENSES_RECURRING_INDEX=false`
   - `FEATURE_ADMIN_EXPENSES_RECURRING_CREATE=false`
   - `FEATURE_ADMIN_EXPENSES_RECURRING_SHOW=false`
   - `FEATURE_ADMIN_EXPENSES_RECURRING_EDIT=false`
2. Rebuild caches:
   - `php artisan optimize:clear`
   - `php artisan config:cache`
   - `php artisan route:cache`
3. Re-run smoke/parity tests before retest.

## Exit Criteria (Expand to Next Module)
1. Zero rollback events during the full cycle.
2. No unresolved parity failures.
3. No unresolved pilot-route log errors.
4. Stakeholder sign-off that Blade fallback is no longer needed for this module.
