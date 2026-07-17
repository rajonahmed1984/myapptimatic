<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CheckProductionReadiness extends Command
{
    protected $signature = 'performance:readiness
        {--strict : Return a non-zero exit code when a required production check fails}';

    protected $description = 'Check low-risk Laravel production performance prerequisites without changing state';

    public function handle(): int
    {
        $checks = $this->checks();

        $this->table(
            ['Check', 'Status', 'Required', 'Details'],
            array_map(fn (array $check): array => [
                $check['name'],
                $check['passed'] ? 'PASS' : 'ACTION',
                $check['required'] ? 'yes' : 'recommended',
                $check['details'],
            ], $checks)
        );

        $requiredFailures = collect($checks)
            ->where('required', true)
            ->where('passed', false)
            ->count();
        $recommendations = collect($checks)
            ->where('required', false)
            ->where('passed', false)
            ->count();

        if ($requiredFailures === 0) {
            $this->info('Required production readiness checks passed.');
        } else {
            $this->warn("{$requiredFailures} required production readiness check(s) need action.");
        }

        if ($recommendations > 0) {
            $this->warn("{$recommendations} recommended optimization(s) need review.");
        }

        if ($this->option('strict') && $requiredFailures > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{name: string, passed: bool, required: bool, details: string}>
     */
    private function checks(): array
    {
        $environment = app()->environment();
        $cacheStore = (string) config('cache.default');
        $sessionDriver = (string) config('session.driver');
        $queueConnection = (string) config('queue.default');
        $compiledViews = File::glob(storage_path('framework/views/*.php'));
        $opcacheLoaded = extension_loaded('Zend OPcache');
        $opcacheEnabled = filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOL);

        return [
            $this->check(
                'Application environment',
                $environment === 'production',
                true,
                "APP_ENV={$environment}; production deployments must use production"
            ),
            $this->check(
                'Debug mode',
                ! (bool) config('app.debug'),
                true,
                'APP_DEBUG='.((bool) config('app.debug') ? 'true' : 'false')
            ),
            $this->check(
                'Configuration cache',
                app()->configurationIsCached(),
                true,
                app()->configurationIsCached() ? 'cached' : 'run php artisan config:cache'
            ),
            $this->check(
                'Route cache',
                app()->routesAreCached(),
                true,
                app()->routesAreCached() ? 'cached' : 'run php artisan route:cache'
            ),
            $this->check(
                'Event cache',
                $this->eventsAreCached(),
                false,
                $this->eventsAreCached() ? 'cached' : 'run php artisan event:cache'
            ),
            $this->check(
                'Compiled views',
                count($compiledViews) > 0,
                false,
                count($compiledViews).' compiled view file(s)'
            ),
            $this->check(
                'Vite production manifest',
                File::isFile(public_path('build/manifest.json')),
                true,
                File::isFile(public_path('build/manifest.json'))
                    ? 'public/build/manifest.json exists'
                    : 'run npm ci && npm run build'
            ),
            $this->check(
                'Application cache store',
                ! in_array($cacheStore, ['', 'array', 'null'], true),
                true,
                "CACHE_STORE={$cacheStore}"
            ),
            $this->check(
                'Session driver',
                ! in_array($sessionDriver, ['', 'array'], true),
                true,
                "SESSION_DRIVER={$sessionDriver}"
            ),
            $this->check(
                'Queue connection',
                ! in_array($queueConnection, ['', 'sync'], true),
                false,
                "QUEUE_CONNECTION={$queueConnection}"
            ),
            $this->check(
                'OPcache (CLI signal)',
                $opcacheLoaded && $opcacheEnabled,
                false,
                $opcacheLoaded
                    ? 'extension loaded; opcache.enable='.(ini_get('opcache.enable') ?: '0')
                    : 'Zend OPcache is not loaded; verify the web/FPM PHP configuration'
            ),
            $this->check(
                'Performance monitor in production',
                $environment !== 'production' || ! (bool) config('performance.enabled'),
                true,
                $environment === 'production'
                    ? ((bool) config('performance.enabled') ? 'enabled' : 'disabled')
                    : 'not evaluated outside production; currently '
                        .((bool) config('performance.enabled') ? 'enabled' : 'disabled')
            ),
        ];
    }

    /**
     * @return array{name: string, passed: bool, required: bool, details: string}
     */
    private function check(string $name, bool $passed, bool $required, string $details): array
    {
        return compact('name', 'passed', 'required', 'details');
    }

    private function eventsAreCached(): bool
    {
        return method_exists(app(), 'eventsAreCached')
            ? app()->eventsAreCached()
            : File::isFile(base_path('bootstrap/cache/events.php'));
    }
}
