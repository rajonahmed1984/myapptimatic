<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class CleanupBladeViews extends Command
{
    protected $signature = 'ui:cleanup-blade
        {--audit= : Path to blade audit json (default: storage/app/reports/blade-audit.json)}
        {--view=* : Explicit unreferenced view(s) to include in this batch}
        {--force-view=* : Explicit protected view(s) to include in this batch}
        {--execute : Perform file deletion (dry-run by default)}
        {--write : Write cleanup report json + markdown}';

    protected $description = 'Safely clean up unreferenced Blade views in small, auditable batches.';

    /**
     * Views that require explicit override via --force-view.
     *
     * @var array<int, string>
     */
    private array $protectedViews = [
        'errors.403',
        'errors.500',
    ];

    public function handle(): int
    {
        $auditPath = $this->resolveAuditPath();
        if (! File::exists($auditPath)) {
            $this->error("Audit file not found: {$auditPath}");
            $this->line('Run: php artisan ui:audit-blade --write');

            return self::FAILURE;
        }

        $payload = json_decode((string) File::get($auditPath), true);
        if (! is_array($payload)) {
            $this->error("Invalid audit json: {$auditPath}");

            return self::FAILURE;
        }

        $allViews = collect((array) ($payload['all_views'] ?? []));
        $unreferenced = collect((array) ($payload['unreferenced_views'] ?? []));
        $baseCandidates = collect((array) ($payload['cleanup_candidates'] ?? []));
        $explicitViews = collect((array) $this->option('view'))->filter()->values();
        $forcedViews = collect((array) $this->option('force-view'))->filter()->values();

        $selected = $this->buildSelectedViews($baseCandidates, $explicitViews, $forcedViews);

        if ($selected->isEmpty()) {
            $this->warn('No views selected for this batch.');
            $this->line('Tips:');
            $this->line('- Run audit first: php artisan ui:audit-blade --write');
            $this->line('- Add explicit views from unreferenced list using --view=...');

            return self::SUCCESS;
        }

        $evaluation = $selected->map(function (string $viewName) use ($allViews, $unreferenced, $forcedViews): array {
            $isKnown = $allViews->has($viewName);
            $isUnreferenced = $unreferenced->contains($viewName);
            $isProtected = in_array($viewName, $this->protectedViews, true);
            $isForced = $forcedViews->contains($viewName);

            $reasons = [];
            if (! $isKnown) {
                $reasons[] = 'unknown-view';
            }
            if (! $isUnreferenced) {
                $reasons[] = 'still-referenced';
            }
            if ($isProtected && ! $isForced) {
                $reasons[] = 'protected-view';
            }

            return [
                'view' => $viewName,
                'relative_path' => $isKnown ? (string) $allViews->get($viewName) : null,
                'absolute_path' => $isKnown ? resource_path('views/' . $allViews->get($viewName)) : null,
                'is_known' => $isKnown,
                'is_unreferenced' => $isUnreferenced,
                'is_protected' => $isProtected,
                'is_forced' => $isForced,
                'eligible' => $reasons === [],
                'reasons' => $reasons,
            ];
        })->values();

        $eligible = $evaluation->where('eligible', true)->values();
        $blocked = $evaluation->where('eligible', false)->values();

        $this->line('Blade Cleanup Batch');
        $this->line('-------------------');
        $this->line('Audit: ' . $auditPath);
        $this->line('Selected: ' . $evaluation->count());
        $this->line('Eligible: ' . $eligible->count());
        $this->line('Blocked: ' . $blocked->count());
        $this->newLine();

        foreach ($evaluation as $row) {
            $status = $row['eligible'] ? 'ELIGIBLE' : 'BLOCKED';
            $details = $row['eligible']
                ? (string) $row['relative_path']
                : implode(',', (array) $row['reasons']);
            $this->line(sprintf('[%s] %s (%s)', $status, $row['view'], $details));
        }

        $deleted = collect();
        if ((bool) $this->option('execute') && $eligible->isNotEmpty()) {
            $this->newLine();
            foreach ($eligible as $row) {
                $path = (string) $row['absolute_path'];
                if ($path === '' || ! File::exists($path)) {
                    $this->warn('Skip missing file: ' . $row['view']);
                    continue;
                }

                File::delete($path);
                $deleted->push([
                    'view' => $row['view'],
                    'relative_path' => $row['relative_path'],
                ]);
                $this->info('Deleted: ' . $row['view']);
            }
        }

        $report = [
            'generated_at' => now()->toIso8601String(),
            'audit_path' => $auditPath,
            'dry_run' => ! (bool) $this->option('execute'),
            'selected_count' => $evaluation->count(),
            'eligible_count' => $eligible->count(),
            'blocked_count' => $blocked->count(),
            'deleted_count' => $deleted->count(),
            'selected' => $selected->values()->all(),
            'eligible' => $eligible->values()->all(),
            'blocked' => $blocked->values()->all(),
            'deleted' => $deleted->values()->all(),
        ];

        if ((bool) $this->option('write')) {
            File::ensureDirectoryExists(storage_path('app/reports'));
            File::put(
                storage_path('app/reports/blade-cleanup-last.json'),
                json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );

            File::ensureDirectoryExists(base_path('docs'));
            File::put(
                base_path('docs/phase-7-blade-cleanup-report.md'),
                $this->buildMarkdownReport($report)
            );

            $this->newLine();
            $this->info('Wrote report files:');
            $this->line('- storage/app/reports/blade-cleanup-last.json');
            $this->line('- docs/phase-7-blade-cleanup-report.md');
        }

        if (! (bool) $this->option('execute')) {
            $this->newLine();
            $this->line('Dry-run mode: no files were deleted.');
            $this->line('Execute with: php artisan ui:cleanup-blade --execute');
        }

        return self::SUCCESS;
    }

    private function resolveAuditPath(): string
    {
        $fromOption = (string) ($this->option('audit') ?? '');
        if ($fromOption !== '') {
            return File::isAbsolutePath($fromOption)
                ? $fromOption
                : base_path($fromOption);
        }

        return storage_path('app/reports/blade-audit.json');
    }

    private function buildSelectedViews(Collection $base, Collection $explicit, Collection $forced): Collection
    {
        return $base
            ->merge($explicit)
            ->merge($forced)
            ->filter(fn (mixed $value): bool => is_string($value) && trim($value) !== '')
            ->map(fn (string $view): string => trim($view))
            ->unique()
            ->values();
    }

    private function buildMarkdownReport(array $report): string
    {
        $lines = [];
        $lines[] = '# Phase 7 Blade Cleanup Report';
        $lines[] = '';
        $lines[] = '- Generated at: ' . ($report['generated_at'] ?? now()->toIso8601String());
        $lines[] = '- Dry run: ' . (($report['dry_run'] ?? true) ? 'yes' : 'no');
        $lines[] = '- Selected: ' . (int) ($report['selected_count'] ?? 0);
        $lines[] = '- Eligible: ' . (int) ($report['eligible_count'] ?? 0);
        $lines[] = '- Blocked: ' . (int) ($report['blocked_count'] ?? 0);
        $lines[] = '- Deleted: ' . (int) ($report['deleted_count'] ?? 0);
        $lines[] = '';

        $lines[] = '## Deleted Views';
        $deleted = collect((array) ($report['deleted'] ?? []));
        if ($deleted->isEmpty()) {
            $lines[] = '- None.';
        } else {
            foreach ($deleted as $row) {
                $lines[] = '- `' . ($row['view'] ?? '') . '`';
            }
        }

        $lines[] = '';
        $lines[] = '## Blocked Views';
        $blocked = collect((array) ($report['blocked'] ?? []));
        if ($blocked->isEmpty()) {
            $lines[] = '- None.';
        } else {
            foreach ($blocked as $row) {
                $reasons = implode(',', (array) ($row['reasons'] ?? []));
                $lines[] = '- `' . ($row['view'] ?? '') . '` (' . $reasons . ')';
            }
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }
}
