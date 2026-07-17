# Phase 7: Finance Reports warm-cache optimization

## Goal

Make repeated Finance Reports navigation and filter revisits faster without
changing report totals, filters, or the Inertia response contract.

## Changes

- Income and expense source rows are cached for 60 seconds using a key that
  includes the date range, selected sources, income basis, and category filters.
- Cache entries are shared across authorized admins because the underlying
  finance report data is business-wide, not user-specific.
- `?fresh=1` bypasses the Finance Reports row cache for an explicit refresh.
- The configured cache can be disabled with
  `FINANCE_REPORT_CACHE_SECONDS=0`.
- Invoiced and received system-income queries now select only the columns used
  by the report.
- VAT totals are calculated in one SQL aggregate query instead of loading every
  matching invoice into PHP memory.

The existing ten-minute CarrotHost transaction cache remains independent.
`fresh=1` bypasses the Finance Reports cache, but does not force a remote WHMCS
API refresh.

## Configuration

```dotenv
FINANCE_REPORT_CACHE_SECONDS=60
```

A short TTL limits staleness while making repeated report visits substantially
cheaper. Use the `fresh=1` query parameter when newly entered local finance data
must appear immediately.

## Verification

```bash
php artisan test tests/Feature/FinanceReportsUiParityTest.php
```

The regression test warms the cache, inserts another income row, verifies that
the warm response is reused, and confirms that `fresh=1` returns the new total.
