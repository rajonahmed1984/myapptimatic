<?php

namespace App\Console\Commands;

use App\Support\AuthFresh\Portal;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class AuditBladeUsage extends Command
{
    protected $signature = 'ui:audit-blade {--write : Write JSON + Markdown reports}';

    protected $description = 'Audit Blade view usage and list conservative cleanup candidates.';

    /**
     * @var array<int, string>
     */
    private array $reservedPrefixes = [
        'layouts.',
        'components.',
        'errors.',
        'mail.',
        'emails.',
        'auth.',
        'project-client.',
    ];

    public function handle(): int
    {
        $allViews = $this->collectBladeViews();
        $references = $this->collectReferences($allViews);
        $implicitViews = $this->collectImplicitViews();

        foreach ($implicitViews as $viewName) {
            if (! isset($allViews[$viewName])) {
                continue;
            }

            $references[$viewName][] = '[implicit] framework/runtime reference';
        }

        $referenced = collect(array_keys($references))
            ->filter(fn (string $viewName) => isset($allViews[$viewName]))
            ->values();

        $unreferenced = $allViews->keys()
            ->reject(fn (string $viewName) => $referenced->contains($viewName))
            ->values();

        $cleanupCandidates = $unreferenced
            ->reject(fn (string $viewName) => $this->isReserved($viewName))
            ->reject(fn (string $viewName) => str_contains($viewName, '.partials.'))
            ->reject(fn (string $viewName) => str_ends_with($viewName, '.partials'))
            ->values();

        $summary = [
            'scanned_at' => now()->toIso8601String(),
            'total_views' => $allViews->count(),
            'referenced_views' => $referenced->count(),
            'unreferenced_views' => $unreferenced->count(),
            'cleanup_candidates' => $cleanupCandidates->count(),
        ];

        $this->line('Blade Audit Summary');
        $this->line('-------------------');
        foreach ($summary as $key => $value) {
            $this->line(sprintf('%s: %s', $key, (string) $value));
        }

        if ($cleanupCandidates->isNotEmpty()) {
            $this->newLine();
            $this->line('Top cleanup candidates (conservative):');
            foreach ($cleanupCandidates->take(30) as $viewName) {
                $this->line("- {$viewName}");
            }
        }

        if ($this->option('write')) {
            $jsonPayload = [
                'summary' => $summary,
                'implicit_views' => $implicitViews->values()->all(),
                'all_views' => $allViews->all(),
                'referenced_views' => $referenced->all(),
                'unreferenced_views' => $unreferenced->all(),
                'cleanup_candidates' => $cleanupCandidates->all(),
                'references' => $references,
            ];

            File::ensureDirectoryExists(storage_path('app/reports'));
            File::put(
                storage_path('app/reports/blade-audit.json'),
                json_encode($jsonPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );

            File::ensureDirectoryExists(base_path('docs'));
            File::put(
                base_path('docs/phase-7-blade-audit-report.md'),
                $this->buildMarkdownReport($summary, $cleanupCandidates, $unreferenced)
            );

            $this->info('Wrote report files:');
            $this->line('- storage/app/reports/blade-audit.json');
            $this->line('- docs/phase-7-blade-audit-report.md');
        }

        return self::SUCCESS;
    }

    /**
     * @return Collection<string, string>
     */
    private function collectBladeViews(): Collection
    {
        $viewsPath = resource_path('views');
        $result = [];

        foreach (File::allFiles($viewsPath) as $file) {
            $path = $file->getPathname();
            if (! str_ends_with($path, '.blade.php')) {
                continue;
            }

            $relative = str_replace('\\', '/', ltrim(str_replace($viewsPath, '', $path), '/\\'));
            $viewName = str_replace('/', '.', preg_replace('/\.blade\.php$/', '', $relative) ?? $relative);

            $result[$viewName] = $relative;
        }

        ksort($result);

        return collect($result);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function collectReferences(Collection $allViews): array
    {
        $patterns = [
            "/\\bview\\s*\\(\\s*['\\\"]([^'\\\"]+)['\\\"]/i",
            "/@extends\\s*\\(\\s*['\\\"]([^'\\\"]+)['\\\"]/i",
            "/@include(?:If|When|Unless|First)?\\s*\\(.*?['\\\"]([^'\\\"]+)['\\\"]/is",
            "/@component\\s*\\(\\s*['\\\"]([^'\\\"]+)['\\\"]/i",
            "/@each\\s*\\(\\s*['\\\"]([^'\\\"]+)['\\\"]/i",
        ];

        $scanRoots = [
            app_path(),
            base_path('routes'),
            base_path('config'),
            resource_path('views'),
            base_path('bootstrap'),
        ];

        $references = [];
        $knownViews = array_fill_keys($allViews->keys()->all(), true);

        foreach ($scanRoots as $root) {
            if (! is_dir($root)) {
                continue;
            }

            foreach (File::allFiles($root) as $file) {
                $path = $file->getPathname();
                if (
                    ! str_ends_with($path, '.php')
                    && ! str_ends_with($path, '.blade.php')
                ) {
                    continue;
                }

                $relative = str_replace('\\', '/', ltrim(str_replace(base_path(), '', $path), '/\\'));
                $content = (string) File::get($path);
                if ($content === '') {
                    continue;
                }

                foreach ($patterns as $pattern) {
                    if (! preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                        continue;
                    }

                    foreach ($matches[1] as $capture) {
                        $rawView = (string) ($capture[0] ?? '');
                        $offset = (int) ($capture[1] ?? 0);
                        $viewName = $this->normalizeViewName($rawView);
                        if ($viewName === null) {
                            continue;
                        }

                        $lineNo = substr_count(substr($content, 0, max(0, $offset)), "\n") + 1;
                        $references[$viewName][] = "{$relative}:{$lineNo}";
                    }
                }

                // Also catch literal dynamic references where view names are assigned in arrays/config.
                if (preg_match_all("/['\\\"]([A-Za-z0-9._\\/-]+)['\\\"]/", $content, $literalMatches, PREG_OFFSET_CAPTURE)) {
                    foreach ($literalMatches[1] as $capture) {
                        $rawView = (string) ($capture[0] ?? '');
                        $offset = (int) ($capture[1] ?? 0);
                        $viewName = $this->normalizeViewName($rawView);
                        if ($viewName === null || ! isset($knownViews[$viewName])) {
                            continue;
                        }

                        $lineNo = substr_count(substr($content, 0, max(0, $offset)), "\n") + 1;
                        $references[$viewName][] = "{$relative}:{$lineNo} [literal]";
                    }
                }

                // Anonymous Blade components: <x-nav-link>, <x-status-badge />, etc.
                if (preg_match_all('/<x-([a-z0-9\\-:.]+)\\b/i', $content, $componentMatches, PREG_OFFSET_CAPTURE)) {
                    foreach ($componentMatches[1] as $capture) {
                        $rawComponent = (string) ($capture[0] ?? '');
                        $offset = (int) ($capture[1] ?? 0);
                        $viewName = $this->normalizeComponentViewName($rawComponent);
                        if ($viewName === null || ! isset($knownViews[$viewName])) {
                            continue;
                        }

                        $lineNo = substr_count(substr($content, 0, max(0, $offset)), "\n") + 1;
                        $references[$viewName][] = "{$relative}:{$lineNo} [component]";
                    }
                }
            }
        }

        foreach ($references as $viewName => $sources) {
            $references[$viewName] = array_values(array_unique($sources));
        }

        ksort($references);

        return $references;
    }

    private function normalizeViewName(string $raw): ?string
    {
        $view = trim($raw);
        if ($view === '') {
            return null;
        }

        $view = str_replace('/', '.', $view);
        $view = preg_replace('/\.blade\.php$/', '', $view) ?? $view;

        if (str_starts_with($view, 'vendor::')) {
            return null;
        }

        return $view;
    }

    private function normalizeComponentViewName(string $raw): ?string
    {
        $component = trim($raw);
        if ($component === '') {
            return null;
        }

        $component = str_replace(':', '.', $component);

        return 'components.' . $component;
    }

    /**
     * @return Collection<int, string>
     */
    private function collectImplicitViews(): Collection
    {
        $implicit = [
            'react-admin',
            'react-client',
            'react-employee',
            'react-rep',
            'react-support',
            'react-sandbox',
            'errors.404',
        ];

        foreach (['web', 'admin', 'employee', 'sales', 'support'] as $portal) {
            try {
                $implicit[] = Portal::loginView($portal);
            } catch (\Throwable) {
                // Ignore unknown portal values.
            }
        }

        return collect(array_values(array_unique($implicit)));
    }

    private function isReserved(string $viewName): bool
    {
        foreach ($this->reservedPrefixes as $prefix) {
            if (str_starts_with($viewName, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function buildMarkdownReport(array $summary, Collection $cleanupCandidates, Collection $unreferenced): string
    {
        $lines = [];
        $lines[] = '# Phase 7 Blade Audit Report';
        $lines[] = '';
        $lines[] = '- Generated at: ' . ($summary['scanned_at'] ?? now()->toIso8601String());
        $lines[] = '- Total Blade views: ' . $summary['total_views'];
        $lines[] = '- Referenced views: ' . $summary['referenced_views'];
        $lines[] = '- Unreferenced views: ' . $summary['unreferenced_views'];
        $lines[] = '- Conservative cleanup candidates: ' . $summary['cleanup_candidates'];
        $lines[] = '';
        $lines[] = '## Safety Decision';
        $lines[] = '- No direct app-wide Blade deletion is executed automatically.';
        $lines[] = '- Candidate files require per-route traffic verification and parity checks before deletion.';
        $lines[] = '- Keep rollback by deleting in small batches and tagging each batch.';
        $lines[] = '';
        $lines[] = '## Conservative Cleanup Candidates (Top 50)';

        $candidateList = $cleanupCandidates->take(50)->values();
        if ($candidateList->isEmpty()) {
            $lines[] = '- None.';
        } else {
            foreach ($candidateList as $viewName) {
                $lines[] = '- `' . $viewName . '`';
            }
        }

        $lines[] = '';
        $lines[] = '## Unreferenced Views (Top 50)';
        $unrefList = $unreferenced->take(50)->values();
        if ($unrefList->isEmpty()) {
            $lines[] = '- None.';
        } else {
            foreach ($unrefList as $viewName) {
                $lines[] = '- `' . $viewName . '`';
            }
        }

        $lines[] = '';
        $lines[] = '## Rollback Matrix';
        $lines[] = '- Trigger: Any route 500/404 mismatch or parity test failure.';
        $lines[] = '- Action 1: Revert current deletion batch commit only.';
        $lines[] = '- Action 2: Clear caches (`php artisan optimize:clear`).';
        $lines[] = '- Action 3: Re-run full safety gates.';

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }
}
