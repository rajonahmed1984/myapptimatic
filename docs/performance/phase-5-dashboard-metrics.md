# Phase 5: Dashboard metrics optimization

## Implemented

- Business-status VAT totals are calculated with one SQL aggregate instead of loading invoice rows.
- Commission total, payable, and paid values are calculated with one conditional aggregate.
- Upcoming invoice totals/counts, upcoming expense totals/counts, and overdue invoice totals/counts each use a combined aggregate query.
- Standalone business-status metrics use a configurable 60-second cache keyed by user, reporting period, and projection window.
- Explicit AI generation and dashboard `ai=refresh` bypass the metrics cache.
- Setting `BUSINESS_STATUS_CACHE_SECONDS=0` disables this cache.

## Safety

- The response structure and money rounding remain unchanged.
- Cache scope includes the current user because task summaries can be user-specific.
- The cache duration is deliberately short to limit dashboard staleness.
- The existing 10-minute Gemini summary cache remains unchanged.

## Regression coverage

`BusinessStatusSummaryServiceTest` verifies:

- Finance totals and cashflow.
- VAT exclusive/inclusive aggregates.
- Commission totals and reversed-earning exclusion.
- Projection totals and counts.
- Overdue totals and counts.
- Cache hits and explicit fresh-cache bypass.
