<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MeasureRequestPerformance
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (! config('performance.enabled', false)) {
            return $next($request);
        }

        $startedAt = hrtime(true);
        $memoryAtStart = memory_get_usage(true);
        $connection = DB::connection();
        $wasLoggingQueries = $this->isLoggingQueries($connection);
        $queryOffset = 0;

        if ($wasLoggingQueries) {
            $queryOffset = count($connection->getQueryLog());
        } else {
            $connection->flushQueryLog();
            $connection->enableQueryLog();
        }

        try {
            $response = $next($request);
        } finally {
            $queryLog = array_slice($connection->getQueryLog(), $queryOffset);

            if (! $wasLoggingQueries) {
                $connection->disableQueryLog();
                $connection->flushQueryLog();
            }
        }

        $durationMs = (hrtime(true) - $startedAt) / 1_000_000;
        $queryTimeMs = array_sum(array_map(
            static fn (array $query): float => (float) ($query['time'] ?? 0),
            $queryLog
        ));
        $payloadBytes = $this->responseBytes($response);
        $memoryDeltaMb = max(0, memory_get_usage(true) - $memoryAtStart) / 1_048_576;
        $memoryPeakMb = memory_get_peak_usage(true) / 1_048_576;

        $metrics = [
            'method' => $request->method(),
            'path' => '/'.ltrim($request->path(), '/'),
            'route' => $request->route()?->getName(),
            'status' => $response instanceof Response ? $response->getStatusCode() : null,
            'duration_ms' => round($durationMs, 2),
            'query_count' => count($queryLog),
            'query_time_ms' => round($queryTimeMs, 2),
            'payload_bytes' => $payloadBytes,
            'memory_delta_mb' => round($memoryDeltaMb, 2),
            'memory_peak_mb' => round($memoryPeakMb, 2),
            'user_id' => $request->user()?->getAuthIdentifier(),
            'slow_queries' => $this->slowQueries($queryLog),
        ];

        if ($response instanceof Response) {
            $this->addHeaders($response, $metrics);
        }

        if (
            config('performance.log', false)
            && $durationMs >= (float) config('performance.log_min_duration_ms', 0)
        ) {
            Log::info('[PERFORMANCE_BASELINE]', $metrics);
        }

        return $response;
    }

    private function isLoggingQueries(ConnectionInterface $connection): bool
    {
        return method_exists($connection, 'logging') && $connection->logging();
    }

    /**
     * @param  array<int, array<string, mixed>>  $queryLog
     * @return array<int, array{time_ms: float, sql: string}>
     */
    private function slowQueries(array $queryLog): array
    {
        $threshold = (float) config('performance.slow_query_ms', 100);
        $limit = max(0, (int) config('performance.max_slow_queries', 5));

        return collect($queryLog)
            ->filter(fn (array $query): bool => (float) ($query['time'] ?? 0) >= $threshold)
            ->sortByDesc(fn (array $query): float => (float) ($query['time'] ?? 0))
            ->take($limit)
            ->map(fn (array $query): array => [
                'time_ms' => round((float) ($query['time'] ?? 0), 2),
                'sql' => (string) ($query['query'] ?? ''),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    private function addHeaders(Response $response, array $metrics): void
    {
        $serverTiming = array_filter([
            $response->headers->get('Server-Timing'),
            sprintf('app;dur=%.2f', $metrics['duration_ms']),
            sprintf('db;dur=%.2f', $metrics['query_time_ms']),
        ]);

        $response->headers->set('Server-Timing', implode(', ', $serverTiming));
        $response->headers->set('X-Performance-Duration-Ms', (string) $metrics['duration_ms']);
        $response->headers->set('X-Performance-Query-Count', (string) $metrics['query_count']);
        $response->headers->set('X-Performance-Query-Time-Ms', (string) $metrics['query_time_ms']);
        $response->headers->set('X-Performance-Payload-Bytes', (string) ($metrics['payload_bytes'] ?? 'unknown'));
        $response->headers->set('X-Performance-Memory-Delta-Mb', (string) $metrics['memory_delta_mb']);
        $response->headers->set('X-Performance-Memory-Peak-Mb', (string) $metrics['memory_peak_mb']);
    }

    private function responseBytes(mixed $response): ?int
    {
        if (
            ! $response instanceof Response
            || $response instanceof BinaryFileResponse
            || $response instanceof StreamedResponse
        ) {
            return null;
        }

        $content = $response->getContent();

        return is_string($content) ? strlen($content) : null;
    }
}
