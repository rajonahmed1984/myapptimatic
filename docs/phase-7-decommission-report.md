# Phase 7 Decommission Report

Date: 2026-02-24  
Status: Completed for batches 1, 2, 3, 4, and 5

## Batch 1 Scope

Removed Blade fallback for:

- `admin.chats.index`
- `admin.payment-gateways.index`
- `admin.payment-proofs.index`

Batch 1 removals:

- Feature keys:
  - `admin_chats_index`
  - `admin_payment_gateways_index`
  - `admin_payment_proofs_index`
- Blade views:
  - `resources/views/admin/chats/index.blade.php`
  - `resources/views/admin/payment-gateways/index.blade.php`
  - `resources/views/admin/payment-proofs/index.blade.php`
  - `resources/views/admin/payment-proofs/partials/table.blade.php`

## Batch 2 Scope

Removed Blade fallback for:

- `admin.users.activity-summary`
- `admin.automation-status`
- `admin.commission-payouts.index`
- `admin.accounting.index`
- `admin.accounting.ledger`
- `admin.accounting.transactions`
- `admin.logs.activity`
- `admin.logs.admin`
- `admin.logs.module`
- `admin.logs.email`
- `admin.logs.ticket-mail-import`

Batch 2 removals:

- Feature keys:
  - `admin_users_activity_summary_index`
  - `admin_automation_status_index`
  - `admin_commission_payouts_index`
  - `admin_accounting_index`
  - `admin_logs_index`
- Blade views:
  - `resources/views/admin/accounting/index.blade.php`
  - `resources/views/admin/automation-status.blade.php`
  - `resources/views/admin/commission-payouts/index.blade.php`
  - `resources/views/admin/logs/index.blade.php`
  - `resources/views/admin/users/activity-summary.blade.php`

## Batch 3 Scope

Removed Blade fallback for:

- `admin.products.index`
- `admin.plans.index`
- `admin.subscriptions.index`
- `admin.licenses.index`
- `admin.orders.index`
- `admin.support-tickets.index`

Batch 3 removals:

- Feature keys:
  - `admin_products_index`
  - `admin_plans_index`
  - `admin_subscriptions_index`
  - `admin_licenses_index`
  - `admin_orders_index`
  - `admin_support_tickets_index`
- Blade views:
  - `resources/views/admin/products/index.blade.php`
  - `resources/views/admin/plans/index.blade.php`
  - `resources/views/admin/subscriptions/index.blade.php`
  - `resources/views/admin/licenses/index.blade.php`
  - `resources/views/admin/orders/index.blade.php`
  - `resources/views/admin/support-tickets/index.blade.php`

## Batch 4 Scope

Removed Blade fallback for:

- `admin.apptimatic-email.inbox`
- `admin.apptimatic-email.show`
- `admin.income.index`
- `admin.income.categories.index`
- `admin.finance.reports.index`
- `admin.finance.payment-methods.index`
- `admin.finance.tax.index`

Batch 4 removals:

- Feature keys:
  - `admin_apptimatic_email_inbox`
  - `admin_apptimatic_email_show`
  - `admin_income_index`
  - `admin_income_categories_index`
  - `admin_finance_reports_index`
  - `admin_finance_payment_methods_index`
  - `admin_finance_tax_index`
- Blade views:
  - `resources/views/admin/apptimatic-email/inbox.blade.php`
  - `resources/views/admin/apptimatic-email/show.blade.php`
  - `resources/views/admin/income/index.blade.php`
  - `resources/views/admin/income/categories/index.blade.php`
  - `resources/views/admin/finance/reports/index.blade.php`
  - `resources/views/admin/finance/payment-methods/index.blade.php`
  - `resources/views/admin/finance/tax/index.blade.php`

## Batch 5 Scope

Removed Blade fallback for:

- `admin.expenses.index`
- `admin.expenses.categories.index`
- `admin.expenses.recurring.index`
- `admin.expenses.recurring.create`
- `admin.expenses.recurring.show`
- `admin.expenses.recurring.edit`

Batch 5 removals:

- Feature keys:
  - `admin_expenses_index`
  - `admin_expenses_categories_index`
  - `admin_expenses_recurring_index`
  - `admin_expenses_recurring_create`
  - `admin_expenses_recurring_show`
  - `admin_expenses_recurring_edit`
- Blade views:
  - `resources/views/admin/expenses/index.blade.php`
  - `resources/views/admin/expenses/categories/index.blade.php`
  - `resources/views/admin/expenses/recurring/index.blade.php`
  - `resources/views/admin/expenses/recurring/create.blade.php`
  - `resources/views/admin/expenses/recurring/show.blade.php`
  - `resources/views/admin/expenses/recurring/edit.blade.php`

## Parity and Safety

- Existing route names and URLs unchanged.
- Auth, guard, role and permission checks unchanged.
- Action endpoints unchanged:
  - payment-proof approve/reject/receipt
  - payment-gateway edit/update
  - accounting create/store/edit/update/destroy
  - commission-payout create/store/show/pay/reverse
  - logs resend/delete
  - products/plans/subscriptions/licenses/orders/support-tickets actions
  - apptimatic-email inbox/show
  - income and income-category actions
  - finance reports/payment-methods/tax actions
  - expenses/expense-categories/recurring actions
- SSE/PDF/upload/payment transport contracts remain green.

Phase 7 final state:

- All admin React migration feature gates were removed.
- No remaining `react.ui:admin_*` route middleware gates.
- Legacy Blade decommission for migrated admin pages is complete.

## Verification

Command: `composer phase7:verify`

Latest run (2026-02-24): PASS

- Decommission UI parity checks: PASS
- Complex-module transport contract checks: PASS
- Smoke checks: PASS
- `npm run build`: PASS
- `php artisan config:cache`: PASS
- `php artisan route:cache`: PASS
- Full suite in same run: 400 passed, 1618 assertions

## Rollback

If any regression appears:

1. Revert the Phase 7 commit.
2. Run:
   - `php artisan optimize:clear`
   - `php artisan config:cache`
   - `php artisan route:cache`
