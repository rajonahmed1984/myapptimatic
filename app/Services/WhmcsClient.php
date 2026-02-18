<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WhmcsClient
{
    public function isConfigured(): bool
    {
        return (string) config('whmcs.url') !== ''
            && (string) config('whmcs.username') !== ''
            && (string) config('whmcs.identifier') !== ''
            && (string) config('whmcs.secret') !== '';
    }

    public function endpoint(): string
    {
        $endpoints = $this->endpoints();

        return $endpoints[0] ?? '';
    }

    public function endpoints(): array
    {
        $endpoints = [];
        $apiUrl = rtrim((string) config('whmcs.api_url'), '/');
        if ($apiUrl !== '') {
            $endpoints[] = $this->normalizeEndpoint($apiUrl);
        }

        $base = rtrim((string) config('whmcs.url'), '/');
        if ($base !== '') {
            $endpoints = array_merge($endpoints, $this->buildEndpointCandidates($base));
        }

        return array_values(array_unique(array_filter($endpoints)));
    }

    public function call(string $action, array $params = []): array
    {
        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'error' => 'WHMCS credentials are missing.',
            ];
        }

        $endpoints = $this->endpoints();
        if ($endpoints === []) {
            return [
                'ok' => false,
                'error' => 'WHMCS URL is not configured.',
            ];
        }

        $payload = array_merge([
            'action' => $action,
            'username' => config('whmcs.username'),
            'identifier' => config('whmcs.identifier'),
            'secret' => config('whmcs.secret'),
            'responsetype' => 'json',
        ], $params);

        $configuredApiUrl = trim((string) config('whmcs.api_url'));
        $configuredEndpoint = $configuredApiUrl !== ''
            ? $this->normalizeEndpoint(rtrim($configuredApiUrl, '/'))
            : '';

        $lastStatus = null;
        $primaryStatus = null;
        $attemptErrors = [];

        foreach ($endpoints as $endpoint) {
            $response = Http::asForm()
                ->timeout((int) config('whmcs.timeout', 10))
                ->post($endpoint, $payload);

            $lastStatus = $response->status();
            if ($primaryStatus === null || ($primaryStatus === 404 && $lastStatus !== 404)) {
                $primaryStatus = $lastStatus;
            }
            if ($lastStatus === 404 && count($endpoints) > 1) {
                continue;
            }

            if (! $response->ok()) {
                $attemptErrors[] = sprintf(
                    '%s returned HTTP %d',
                    $endpoint,
                    $response->status()
                );

                // If the explicitly configured API endpoint is forbidden, fallback
                // candidates are unlikely to succeed and only add noisy 404s.
                if ($lastStatus === 403 && $configuredEndpoint !== '' && $endpoint === $configuredEndpoint) {
                    break;
                }
                continue;
            }

            $data = $response->json();
            if (! is_array($data)) {
                $attemptErrors[] = sprintf(
                    '%s returned an invalid response',
                    $endpoint
                );
                continue;
            }

            if (($data['result'] ?? '') !== 'success') {
                $message = $data['message'] ?? 'WHMCS API returned an error.';
                $attemptErrors[] = sprintf(
                    '%s returned API error: %s',
                    $endpoint,
                    $message
                );
                continue;
            }

            return [
                'ok' => true,
                'data' => $data,
            ];
        }

        $attemptSummary = $attemptErrors !== []
            ? ' [' . implode(' | ', $attemptErrors) . ']'
            : '';

        $statusForMessage = $primaryStatus ?? $lastStatus;
        $hint = '';
        if ($statusForMessage === 403) {
            $hint = ' Check WHMCS API access rules (server firewall / IP whitelist / API permission).';
        }

        return [
            'ok' => false,
            'error' => 'WHMCS API request failed with HTTP ' . ($statusForMessage ?? 'unknown') . $attemptSummary . $hint,
        ];
    }

    private function normalizeEndpoint(string $value): string
    {
        if (str_ends_with($value, 'includes/api.php')) {
            return $value;
        }

        return rtrim($value, '/') . '/includes/api.php';
    }

    private function buildEndpointCandidates(string $base): array
    {
        if (str_ends_with($base, 'includes/api.php')) {
            return [$base];
        }

        $endpoints = [];
        $endpoints[] = $base . '/includes/api.php';

        $parts = parse_url($base);
        if (! is_array($parts) || empty($parts['host']) || empty($parts['scheme'])) {
            return $endpoints;
        }

        $path = isset($parts['path']) ? rtrim($parts['path'], '/') : '';
        if ($path === '') {
            return $endpoints;
        }

        $segments = array_values(array_filter(explode('/', ltrim($path, '/'))));
        if (count($segments) === 0) {
            return $endpoints;
        }

        array_pop($segments);
        $parentPath = $segments === [] ? '' : '/' . implode('/', $segments);
        $endpoints[] = $this->buildBaseFromParts($parts, $parentPath) . '/includes/api.php';

        if ($parentPath !== '') {
            $endpoints[] = $this->buildBaseFromParts($parts, '') . '/includes/api.php';
        }

        return $endpoints;
    }

    private function buildBaseFromParts(array $parts, string $path): string
    {
        $host = $parts['host'];
        if (isset($parts['port'])) {
            $host .= ':' . $parts['port'];
        }

        return $parts['scheme'] . '://' . $host . $path;
    }
}
