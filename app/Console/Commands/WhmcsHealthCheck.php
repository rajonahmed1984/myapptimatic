<?php

namespace App\Console\Commands;

use App\Services\WhmcsClient;
use Carbon\Carbon;
use Illuminate\Console\Command;

class WhmcsHealthCheck extends Command
{
    protected $signature = 'whmcs:health-check
        {--action=GetTransactions : WHMCS API action to test}
        {--startdate= : Start date (Y-m-d), used for GetTransactions}
        {--enddate= : End date (Y-m-d), used for GetTransactions}
        {--json : Output JSON only}';

    protected $description = 'Run a quick WHMCS configuration and API health check.';

    public function handle(WhmcsClient $client): int
    {
        $action = trim((string) $this->option('action'));
        if ($action === '') {
            $action = 'GetTransactions';
        }

        $startDate = $this->normalizeDate((string) $this->option('startdate')) ?? now()->toDateString();
        $endDate = $this->normalizeDate((string) $this->option('enddate')) ?? now()->toDateString();

        $params = [];
        if (strcasecmp($action, 'GetTransactions') === 0) {
            $params = [
                'startdate' => $startDate,
                'enddate' => $endDate,
                'orderby' => 'date',
                'order' => 'desc',
                'limitstart' => 0,
                'limitnum' => 1,
            ];
        }

        $endpoints = $client->endpoints();
        $result = $client->call($action, $params);

        $payload = [
            'generated_at' => now()->toDateTimeString(),
            'configured' => $client->isConfigured(),
            'action' => $action,
            'params' => $params,
            'whmcs' => [
                'url' => (string) config('whmcs.url'),
                'api_url' => (string) config('whmcs.api_url'),
                'username' => (string) config('whmcs.username'),
                'identifier_present' => (string) config('whmcs.identifier') !== '',
                'secret_present' => (string) config('whmcs.secret') !== '',
            ],
            'endpoint_candidates' => $endpoints,
            'ok' => (bool) ($result['ok'] ?? false),
            'error' => $result['error'] ?? null,
            'response_result' => $result['data']['result'] ?? null,
            'response_totalresults' => $result['data']['totalresults'] ?? null,
            'response_message' => $result['data']['message'] ?? null,
        ];

        if ((bool) $this->option('json')) {
            $this->line(json_encode($payload, JSON_UNESCAPED_SLASHES));

            return $payload['ok'] ? self::SUCCESS : self::FAILURE;
        }

        $this->info('WHMCS Health Check');
        $this->line('Generated at: ' . $payload['generated_at']);
        $this->line('Action: ' . $payload['action']);
        $this->line('Configured: ' . ($payload['configured'] ? 'yes' : 'no'));
        $this->newLine();

        $this->line('Config');
        $this->table(
            ['Key', 'Value'],
            [
                ['whmcs.url', $payload['whmcs']['url'] ?: '--'],
                ['whmcs.api_url', $payload['whmcs']['api_url'] ?: '--'],
                ['whmcs.username', $payload['whmcs']['username'] ?: '--'],
                ['whmcs.identifier', $payload['whmcs']['identifier_present'] ? 'present' : 'missing'],
                ['whmcs.secret', $payload['whmcs']['secret_present'] ? 'present' : 'missing'],
            ]
        );

        $this->line('Endpoint candidates');
        if ($endpoints === []) {
            $this->warn('No endpoint candidate found.');
        } else {
            foreach ($endpoints as $index => $endpoint) {
                $this->line(sprintf('%d. %s', $index + 1, $endpoint));
            }
        }

        $this->newLine();
        if ($payload['ok']) {
            $this->info('API check: SUCCESS');
            if ($payload['response_totalresults'] !== null) {
                $this->line('totalresults: ' . (string) $payload['response_totalresults']);
            }
        } else {
            $this->error('API check: FAILED');
            $this->line((string) ($payload['error'] ?? 'Unknown error'));
        }

        return $payload['ok'] ? self::SUCCESS : self::FAILURE;
    }

    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}

