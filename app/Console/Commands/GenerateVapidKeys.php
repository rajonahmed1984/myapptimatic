<?php

namespace App\Console\Commands;

use App\Services\Push\WebPushClient;
use Illuminate\Console\Command;

class GenerateVapidKeys extends Command
{
    protected $signature = 'push:vapid-keys
        {--write : Save the keys to .env instead of only printing them}
        {--force : Replace keys that are already in .env}';

    protected $description = 'Generate the VAPID key pair used for browser (Web Push) notifications';

    public function handle(): int
    {
        $keys = WebPushClient::generateVapidKeys();
        $lines = [
            'VAPID_PUBLIC_KEY' => $keys['public_key'],
            'VAPID_PRIVATE_KEY' => $keys['private_key'],
        ];

        if (! $this->option('write')) {
            foreach ($lines as $name => $value) {
                $this->line("{$name}={$value}");
            }

            return self::SUCCESS;
        }

        $path = app()->environmentFilePath();
        if (! is_file($path)) {
            $this->error("No .env file at {$path}.");

            return self::FAILURE;
        }

        $env = (string) file_get_contents($path);
        if (! $this->option('force') && preg_match('/^VAPID_PRIVATE_KEY=\S+/m', $env)) {
            // New keys would cut off every browser already subscribed.
            $this->warn('VAPID keys are already set in .env. Pass --force to replace them; existing subscribers would have to allow notifications again.');

            return self::FAILURE;
        }

        foreach ($lines as $name => $value) {
            $pattern = '/^'.preg_quote($name, '/').'=.*$/m';
            $env = preg_match($pattern, $env)
                ? (string) preg_replace($pattern, "{$name}={$value}", $env)
                : rtrim($env, "\r\n").PHP_EOL."{$name}={$value}".PHP_EOL;
        }

        file_put_contents($path, $env);

        $this->info('VAPID keys saved to .env. Run `php artisan config:clear` if the config is cached.');

        return self::SUCCESS;
    }
}
