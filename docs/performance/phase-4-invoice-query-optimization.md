# Phase 4: Invoice query optimization

## Implemented

- Invoice-list insights no longer hydrate the complete filtered invoice collection.
- Billed, collected, outstanding, effective statuses, partial payments, overdue amounts, and payment-proof watchlist counts are calculated with database aggregates.
- The paginated invoice rows no longer eager load every accounting entry and payment proof.
- Current-page payment and credit totals use correlated `withSum` queries.
- Pending and rejected proof indicators use `withExists` flags.
- Existing search, project, and status filters are applied to both pagination and aggregate insights.

## Performance effect

The response now hydrates at most the current 30 invoice models plus the relations required for display. Insight memory usage no longer grows linearly with the number of filtered invoices.

## Regression coverage

`AdminInvoicesUiParityTest` verifies:

- 30 displayed rows from a 32-invoice filtered dataset.
- Insights calculated from all 32 matching invoices.
- Billed, collected, outstanding, partial, overdue, cancelled, and effectively-paid results.
- No unbounded `SELECT * FROM invoices`.
- No accounting-entry or payment-proof collection eager loading for list rows.
