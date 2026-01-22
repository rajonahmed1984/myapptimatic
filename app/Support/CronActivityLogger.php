<?php

namespace App\Support;

use App\Models\CronRun;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CronActivityLogger
{
    public static function track(Event $event, string $command): void
    {
        $runId = null;
        $outputPath = self::outputPath($command);

        $event->sendOutputTo($outputPath);

        $event->before(function () use ($command, &$runId, $outputPath) {
            try {
                self::ensureOutputDirectory($outputPath);
                File::put($outputPath, '');
                $run = CronRun::create([
                    'command' => $command,
                    'status' => 'running',
                    'started_at' => now(),
                ]);
                $runId = $run->id;
            } catch (\Throwable) {
                $runId = null;
            }
        });

        $event->after(function () use (&$runId, $outputPath) {
            if (! $runId) {
                return;
            }

            try {
                $finishedAt = now();
                $run = CronRun::find($runId);
                $duration = $run?->started_at?->diffInMilliseconds($finishedAt);

                CronRun::whereKey($runId)->update([
                    'finished_at' => $finishedAt,
                    'duration_ms' => $duration,
                    'output_excerpt' => self::readOutput($outputPath),
                ]);
            } catch (\Throwable) {
                // Avoid breaking the scheduler on logging issues.
            }
        });

        $event->onSuccess(function () use (&$runId) {
            if (! $runId) {
                return;
            }

            try {
                CronRun::whereKey($runId)->update(['status' => 'success']);
            } catch (\Throwable) {
                // Ignore logging failures.
            }
        });

        $event->onFailure(function () use (&$runId, $outputPath) {
            if (! $runId) {
                return;
            }

            try {
                CronRun::whereKey($runId)->update([
                    'status' => 'failed',
                    'error_excerpt' => self::readOutput($outputPath),
                ]);
            } catch (\Throwable) {
                // Ignore logging failures.
            }
        });
    }

    private static function outputPath(string $command): string
    {
        $slug = Str::slug($command);
        return storage_path('logs/cron-activity/' . $slug . '.log');
    }

    private static function ensureOutputDirectory(string $outputPath): void
    {
        $directory = dirname($outputPath);
        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }
    }

    private static function readOutput(string $outputPath): ?string
    {
        if (! File::exists($outputPath)) {
            return null;
        }

        $contents = trim((string) File::get($outputPath));
        if ($contents === '') {
            return null;
        }

        return Str::limit($contents, 500);
    }
}
