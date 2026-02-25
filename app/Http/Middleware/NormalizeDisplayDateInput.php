<?php

namespace App\Http\Middleware;

use App\Support\DateTimeFormat;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizeDisplayDateInput
{
    public function handle(Request $request, Closure $next): Response
    {
        $payload = $request->all();
        if (! empty($payload)) {
            $request->merge($this->normalizePayload($payload));
        }

        return $next($request);
    }

    /**
     * @param array<string|int, mixed> $payload
     * @return array<string|int, mixed>
     */
    private function normalizePayload(array $payload, ?string $parentKey = null): array
    {
        foreach ($payload as $key => $value) {
            $effectiveKey = is_string($key) ? $key : $parentKey;

            if (is_array($value)) {
                $payload[$key] = $this->normalizePayload($value, $effectiveKey);
                continue;
            }

            if (! is_string($value) || ! $this->shouldNormalizeKey($effectiveKey)) {
                continue;
            }

            $payload[$key] = $this->normalizeValue($value);
        }

        return $payload;
    }

    private function shouldNormalizeKey(?string $key): bool
    {
        if (! is_string($key) || $key === '') {
            return false;
        }

        return (bool) preg_match('/(^|_)(date|at|time|from|to|until)$/i', $key);
    }

    private function normalizeValue(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return $value;
        }

        if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $trimmed) === 1) {
            $parsed = DateTimeFormat::parseDate($trimmed);
            return $parsed ? $parsed->format('Y-m-d') : $value;
        }

        if (preg_match('/^\d{2}-\d{2}-\d{4}\s+\d{1,2}:\d{2}\s*(AM|PM)$/i', $trimmed) === 1) {
            $parsed = DateTimeFormat::parseDateTime(strtoupper($trimmed));
            return $parsed ? $parsed->format('Y-m-d H:i') : $value;
        }

        if (preg_match('/^\d{1,2}:\d{2}\s*(AM|PM)$/i', $trimmed) === 1) {
            $parsed = DateTimeFormat::parseTime(strtoupper($trimmed));
            return $parsed ? $parsed->format('H:i') : $value;
        }

        return $value;
    }
}
