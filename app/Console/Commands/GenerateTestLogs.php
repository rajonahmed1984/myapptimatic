<?php

namespace App\Console\Commands;

use App\Models\SystemLog;
use Illuminate\Console\Command;

class GenerateTestLogs extends Command
{
    protected $signature = 'logs:generate {--count=20}';
    protected $description = 'Generate test system logs for email and module categories';

    public function handle()
    {
        $count = $this->option('count');

        // Generate email logs
        for ($i = 0; $i < intval($count / 2); $i++) {
            SystemLog::create([
                'category' => 'email',
                'level' => 'info',
                'message' => 'Email sent.',
                'ip_address' => '127.0.0.1',
                'user_id' => null,
                'context' => [
                    'subject' => 'Test Email ' . ($i + 1),
                    'to' => ['test@example.com'],
                    'from' => ['noreply@apptimatic.com'],
                    'html' => '<p>Test email content</p>',
                ],
            ]);
        }

        // Generate module logs
        for ($i = 0; $i < intval($count / 2); $i++) {
            SystemLog::create([
                'category' => 'module',
                'level' => $i % 3 === 0 ? 'error' : 'info',
                'message' => $i % 3 === 0 ? 'Payment failed.' : 'Status update completed.',
                'ip_address' => '127.0.0.1',
                'user_id' => null,
                'context' => [
                    'status' => 'completed',
                    'records_updated' => rand(5, 50),
                ],
            ]);
        }

        $this->info("Generated {$count} test logs successfully!");
    }
}
