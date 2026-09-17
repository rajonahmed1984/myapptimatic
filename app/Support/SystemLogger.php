<?php

namespace App\Support;

use App\Models\SystemLog;
use Illuminate\Support\Facades\Schema;

class SystemLogger
{
    public static function write(
        string $category,
        string $message,
        array $context = [],
        ?int $userId = null,
        ?string $ipAddress = null,
        string $level = 'info'
    ): void {
        try {
            if (! Schema::hasTable('system_logs')) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        if ($userId === null && ! app()->runningInConsole()) {
            $userId = auth()->id();
        }

        // Cron and queue jobs have no client; request()->ip() there is a fake 127.0.0.1.
        if ($ipAddress === null && ! app()->runningInConsole() && app()->bound('request')) {
            $ipAddress = request()->ip();
        }

        try {
            SystemLog::create([
                'category' => $category,
                'level' => $level,
                'message' => $message,
                'context' => $context,
                'user_id' => $userId,
                'ip_address' => $ipAddress,
            ]);
        } catch (\Throwable) {
            // Logging should never break the request flow.
        }
    }
}
