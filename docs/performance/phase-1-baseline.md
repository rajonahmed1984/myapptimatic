# Phase 1 performance baseline

This phase measures the current Laravel + Inertia + React application without
changing page behavior. The request monitor is disabled by default and is
restricted to `local` and `staging`.

## Enable locally or on staging

Add the following values to the environment:

```dotenv
PERFORMANCE_MONITOR_ENABLED=true
PERFORMANCE_MONITOR_LOG=true
PERFORMANCE_MONITOR_LOG_MIN_MS=0
PERFORMANCE_MONITOR_SLOW_QUERY_MS=100
```

Then refresh configuration:

```bash
php artisan config:clear
```

Do not enable the monitor on production during Phase 1. The default allowed
environment list prevents it from running there even if the enabled flag is
set accidentally.

## Metrics

Every measured web response includes:

- `Server-Timing`: total application and database duration for browser DevTools.
- `X-Performance-Duration-Ms`: total server-side request time.
- `X-Performance-Query-Count`: SQL statements executed during the request.
- `X-Performance-Query-Time-Ms`: cumulative database time.
- `X-Performance-Payload-Bytes`: final response payload size when measurable.
- `X-Performance-Memory-Delta-Mb`: request memory growth.
- `X-Performance-Memory-Peak-Mb`: process peak memory.

When logging is enabled, structured entries use the marker
`[PERFORMANCE_BASELINE]` in `storage/logs/laravel.log`. Bindings are not logged.

## Representative pages

Warm each page once, then record three subsequent requests and use the median:

| Page | Route |
|---|---|
| Admin dashboard | `admin.dashboard` |
| Invoice list | `admin.invoices.index` |
| Accounting | `admin.accounting.index` |
| Customer list | `admin.customers.index` |
| Project list | `admin.projects.index` |
| Finance reports | `admin.finance.reports.index` |
| VAT settings | `admin.finance.tax.index` |

Record full navigation and any Inertia/partial navigation separately.

## Initial local dataset snapshot

Captured on 2026-07-18:

| Table | Rows |
|---|---:|
| users | 38 |
| customers | 23 |
| invoices | 105 |
| invoice_items | 106 |
| accounting_entries | 61 |
| projects | 27 |
| project_tasks | 160 |
| expenses | 83 |
| expense_invoices | 74 |
| commission_earnings | 29 |

The local environment was not optimized: debug mode was enabled and config,
event, and route caches were not built. These results must not be compared
directly with optimized staging results.

## Initial static findings

- Invoice lists are paginated at 30 rows, but invoice insight aggregation loads
  the entire filtered invoice collection.
- Customer lists are paginated at 30 rows.
- Project lists already use pagination in their main listing paths.
- Accounting currently loads the full filtered entry collection and also loads
  full customer and invoice option collections.
- Finance reports materialize multiple result collections for aggregation.

No optimization should be applied until request medians confirm the priority.
