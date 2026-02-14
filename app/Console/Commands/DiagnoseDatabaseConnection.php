<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DiagnoseDatabaseConnection extends Command
{
    protected $signature = 'diagnostics:db-connection {--connection= : Connection name from config/database.php}';

    protected $description = 'Print safe database connection diagnostics and run a simple DB ping.';

    public function handle(): int
    {
        $connectionName = (string) ($this->option('connection') ?: config('database.default', 'mysql'));
        $connectionConfig = config("database.connections.{$connectionName}");

        if (! is_array($connectionConfig)) {
            $this->error("Connection [{$connectionName}] is not configured.");
            return self::FAILURE;
        }

        $driver = (string) ($connectionConfig['driver'] ?? 'unknown');
        $host = (string) ($connectionConfig['host'] ?? 'n/a');
        $port = (string) ($connectionConfig['port'] ?? 'n/a');
        $database = (string) ($connectionConfig['database'] ?? 'n/a');
        $username = $this->maskUsername($connectionConfig['username'] ?? null);

        $this->line("Connection: {$connectionName}");
        $this->line("Driver: {$driver}");
        $this->line("Host: {$host}");
        $this->line("Port: {$port}");
        $this->line("Database: {$database}");
        $this->line("Username: {$username}");

        try {
            DB::connection($connectionName)->select('SELECT 1 AS ping');
            $this->info('DB ping: OK');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('DB ping: FAILED');
            $this->line('Hint: MySQL service not running / wrong host / wrong port.');
            $this->line('Hint: Verify DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME in .env.');
            $this->line('Hint: Run php artisan config:clear after changing .env.');
            $this->line('Error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function maskUsername(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            return '(empty)';
        }

        if (strlen($value) <= 2) {
            return str_repeat('*', strlen($value));
        }

        return substr($value, 0, 1) . str_repeat('*', strlen($value) - 2) . substr($value, -1);
    }
}
