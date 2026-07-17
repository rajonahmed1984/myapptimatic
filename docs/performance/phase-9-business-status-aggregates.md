# Phase 9: Business Status aggregate summaries

## Goal

Reduce Business Status cold-load time and PHP memory use as finance records
grow, without changing any dashboard totals.

## Previous behaviour

The Business Status service loaded every matching income and expense row,
including customer, invoice, project, category, employee, payroll-period, and
sales-representative relations. It then used the hydrated collections only to
calculate totals.

## Changes

- `IncomeEntryService::summary()` calculates manual and system income with SQL
  `SUM()` queries.
- `ExpenseEntryService::summary()` calculates manual expenses, salaries,
  contractual payouts, and sales payouts with SQL `SUM()` queries.
- Business Status now consumes those summaries and reuses the system-income
  aggregate for received income, removing a duplicate accounting query.
- CarrotHost income still uses its existing ten-minute remote transaction cache;
  only its already-normalized cached rows are summed locally.
- Existing date, source, category, expense-type, recurring-expense, and person
  filter behaviour is retained by the summary methods.
- Supporting date/status indexes were added for income and payout aggregate
  queries.

The existing short Business Status cache and `fresh` bypass remain unchanged.

## Deployment

Run the new index migration during the normal Laravel deployment:

```bash
php artisan migrate --force
```

The migration safely skips missing tables and tolerates equivalent indexes on
installations with custom database tuning.

## Verification

```bash
php artisan test tests/Feature/EntrySummaryServiceTest.php
php artisan test tests/Feature/BusinessStatusSummaryServiceTest.php
```

The parity test creates included and excluded rows for every local source and
confirms that aggregate summaries match the existing hydrated-entry totals.
