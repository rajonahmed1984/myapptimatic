<?php

$allowedEnvironments = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('PERFORMANCE_MONITOR_ENVIRONMENTS', 'local,staging'))
)));

return [
    /*
    |--------------------------------------------------------------------------
    | Request performance baseline
    |--------------------------------------------------------------------------
    |
    | This monitor is opt-in and restricted to the listed environments. It
    | adds diagnostic response headers and can optionally write structured
    | request summaries to the application log.
    |
    */
    'enabled' => (bool) env('PERFORMANCE_MONITOR_ENABLED', false)
        && in_array((string) env('APP_ENV', 'production'), $allowedEnvironments, true),

    'log' => (bool) env('PERFORMANCE_MONITOR_LOG', false),

    'log_min_duration_ms' => (float) env('PERFORMANCE_MONITOR_LOG_MIN_MS', 0),

    'slow_query_ms' => (float) env('PERFORMANCE_MONITOR_SLOW_QUERY_MS', 100),

    'max_slow_queries' => (int) env('PERFORMANCE_MONITOR_MAX_SLOW_QUERIES', 5),

    /*
    | Short-lived cache for the standalone business-status metrics page.
    | Set to 0 to disable. Explicit AI generation and refresh requests bypass it.
    */
    'business_status_cache_seconds' => (int) env('BUSINESS_STATUS_CACHE_SECONDS', 60),

    /*
    | Filter-aware cache for Finance Reports source rows. Set to 0 to disable.
    | A request with fresh=1 bypasses the cache without clearing other filters.
    */
    'finance_report_cache_seconds' => (int) env('FINANCE_REPORT_CACHE_SECONDS', 60),
];
