# Phase 3: Server-side pagination

## Implemented

- Accounting Ledger and Transactions now use Laravel server-side pagination with 30 rows per page.
- Search and scope query parameters are retained in previous/next URLs.
- The React page receives only the current page of accounting rows and relations.
- Summary cards still represent the complete filtered result set through grouped SQL aggregates.
- Running balances remain chronological and correct across page boundaries. The opening balance for a page is calculated with one grouped aggregate query, then the current page is accumulated in memory.
- A composite `accounting_entries(entry_date, id)` index supports the stable newest-first pagination order.

## Existing pagination verified

- Admin invoices: 30 rows per page.
- Admin customers: 30 rows per page.
- Admin projects: 20 rows per page.
- Project task lists: 25 rows per page.

## Verification

- `AccountingUiParityTest` covers page size, totals, query-string retention, global summaries, and cross-page running balances.
- Laravel Pint, React standard checks, and the production Vite build are part of the Phase 3 verification.

## Follow-up completed

The invoice insight materialization identified here was replaced with SQL aggregates in Phase 4.
