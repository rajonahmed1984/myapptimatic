<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Support\Currency;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ProjectStatusAiService
{
    public function analyze(Project $project, GeminiService $geminiService): array
    {
        $prompt = $this->buildPrompt($project);
        $raw = $geminiService->generateText($prompt, [
            'max_output_tokens' => 4096,
        ]);
        $data = $this->extractJson($raw);

        return [
            'raw' => $raw,
            'data' => $data,
        ];
    }

    private function buildPrompt(Project $project): string
    {
        $project->loadMissing(['customer', 'employees', 'salesRepresentatives']);

        $statusCounts = $project->tasks()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $openCount = (int) (($statusCounts['pending'] ?? 0)
            + ($statusCounts['todo'] ?? 0)
            + ($statusCounts['blocked'] ?? 0));
        $inProgressCount = (int) ($statusCounts['in_progress'] ?? 0);
        $completedCount = (int) (($statusCounts['completed'] ?? 0) + ($statusCounts['done'] ?? 0));
        $totalTasks = (int) $statusCounts->sum();

        $overdueCount = (int) ProjectTask::query()
            ->where('project_id', $project->id)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->whereNotIn('status', ['completed', 'done'])
            ->count();

        $dueSoonCount = (int) ProjectTask::query()
            ->where('project_id', $project->id)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '>=', now()->toDateString())
            ->whereDate('due_date', '<=', now()->addDays(7)->toDateString())
            ->whereNotIn('status', ['completed', 'done'])
            ->count();

        $recentTasks = ProjectTask::query()
            ->where('project_id', $project->id)
            ->orderByDesc('created_at')
            ->limit(8)
            ->get(['title', 'status', 'due_date'])
            ->map(fn ($task) => [
                'title' => $task->title,
                'status' => $task->status,
                'due_date' => $task->due_date?->format('Y-m-d'),
            ])
            ->all();

        $budget = (float) ($project->total_budget ?? 0);
        $overhead = (float) ($project->overhead_total ?? 0);
        $budgetWithOverhead = $budget + $overhead;
        $salesRepTotal = (float) ($project->sales_rep_total ?? 0);
        $contractTotal = (float) ($project->contract_amount ?? $project->contract_employee_total_earned ?? 0);
        $payoutsTotal = $salesRepTotal + $contractTotal;
        $initialPayment = (float) ($project->initial_payment_amount ?? 0);
        $remainingBudget = $budgetWithOverhead - $initialPayment;
        $profit = $budgetWithOverhead - $payoutsTotal;

        $currency = $project->currency;
        if (! $currency || ! Currency::isAllowed(strtoupper($currency))) {
            $currency = Currency::DEFAULT;
        }

        $payload = [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'status' => $project->status,
                'type' => $project->type,
                'start_date' => $project->start_date?->format('Y-m-d'),
                'expected_end_date' => $project->expected_end_date?->format('Y-m-d'),
                'due_date' => $project->due_date?->format('Y-m-d'),
                'customer' => $project->customer?->name,
            ],
            'tasks' => [
                'total' => $totalTasks,
                'open' => $openCount,
                'in_progress' => $inProgressCount,
                'completed' => $completedCount,
                'overdue' => $overdueCount,
                'due_soon_7d' => $dueSoonCount,
                'recent' => $recentTasks,
                'by_status' => $statusCounts->map(fn ($count) => (int) $count)->toArray(),
            ],
            'financials' => [
                'currency' => $currency,
                'budget' => round($budget, 2),
                'overhead_total' => round($overhead, 2),
                'budget_with_overhead' => round($budgetWithOverhead, 2),
                'initial_payment' => round($initialPayment, 2),
                'remaining_budget' => round($remainingBudget, 2),
                'sales_rep_total' => round($salesRepTotal, 2),
                'contract_total' => round($contractTotal, 2),
                'payouts_total' => round($payoutsTotal, 2),
                'profit' => round($profit, 2),
            ],
            'people' => [
                'employees' => $project->employees->pluck('name')->filter()->values()->all(),
                'sales_reps' => $project->salesRepresentatives->pluck('name')->filter()->values()->all(),
            ],
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return <<<PROMPT
You are a project delivery analyst. Write a concise project status summary in Bengali.

Rules:
- Use only the provided data. Do not invent facts.
- Output MUST be strict JSON (no code fences) with keys: summary, health, highlights, risks, next_steps.
- summary: 2-3 sentences.
- health: green / yellow / red.
- highlights, risks, next_steps: arrays of 2-4 bullet strings.

Project data (JSON):
{$json}
PROMPT;
    }

    private function extractJson(string $text): ?array
    {
        $clean = $this->stripCodeFences(trim($text));

        $decoded = $this->decodeObject($clean);

        if (! is_array($decoded)) {
            $start = strpos($clean, '{');
            $end = strrpos($clean, '}');
            if ($start === false || $end === false || $end <= $start) {
                return $this->fallbackParse($clean);
            }

            $json = substr($clean, $start, $end - $start + 1);
            $decoded = $this->decodeObject($json);
        }

        if (! is_array($decoded)) {
            return $this->fallbackParse($clean);
        }

        $normalized = $this->normalizePayload($decoded);

        if ($normalized['summary'] === null && empty($normalized['highlights']) && empty($normalized['risks']) && empty($normalized['next_steps'])) {
            return $this->fallbackParse($clean);
        }

        return $normalized;
    }

    private function stripCodeFences(string $text): string
    {
        $clean = preg_replace('/^\s*```[a-zA-Z0-9_-]*\s*[\r\n]?/u', '', $text);
        $clean = preg_replace('/[\r\n]?\s*```\s*$/u', '', (string) $clean);

        return trim((string) $clean);
    }

    private function decodeObject(string $json): ?array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function normalizePayload(array $decoded): array
    {
        $payload = [
            'summary' => Arr::get($decoded, 'summary'),
            'health' => Arr::get($decoded, 'health'),
            'highlights' => Arr::get($decoded, 'highlights'),
            'risks' => Arr::get($decoded, 'risks'),
            'next_steps' => Arr::get($decoded, 'next_steps'),
        ];

        $nestedData = Arr::get($decoded, 'data');
        if (is_array($nestedData)) {
            foreach (array_keys($payload) as $key) {
                if (($payload[$key] === null || $payload[$key] === '') && Arr::has($nestedData, $key)) {
                    $payload[$key] = Arr::get($nestedData, $key);
                }
            }
        }

        $nestedFromSummary = $this->extractNestedPayload($payload['summary']);
        if (is_array($nestedFromSummary)) {
            foreach (array_keys($payload) as $key) {
                if (($payload[$key] === null || $payload[$key] === '') && Arr::has($nestedFromSummary, $key)) {
                    $payload[$key] = Arr::get($nestedFromSummary, $key);
                }
            }
        }

        $summary = is_string($payload['summary']) ? trim($payload['summary']) : null;
        if ($summary === '') {
            $summary = null;
        }

        $health = is_string($payload['health']) ? strtolower(trim($payload['health'])) : null;
        if (! in_array($health, ['green', 'yellow', 'red'], true)) {
            $health = null;
        }

        return [
            'summary' => $summary,
            'health' => $health,
            'highlights' => $this->normalizeList($payload['highlights']),
            'risks' => $this->normalizeList($payload['risks']),
            'next_steps' => $this->normalizeList($payload['next_steps']),
        ];
    }

    private function extractNestedPayload(mixed $value): ?array
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $candidate = trim($value);
        $candidate = $this->stripCodeFences($candidate);

        $decoded = $this->decodeObject($candidate);
        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($candidate, '{');
        $end = strrpos($candidate, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $json = substr($candidate, $start, $end - $start + 1);

        return $this->decodeObject($json);
    }

    private function normalizeList(mixed $value): array
    {
        if (is_array($value)) {
            return collect($value)
                ->map(fn ($item) => is_string($item) ? trim($item) : '')
                ->filter()
                ->values()
                ->all();
        }

        if (is_string($value)) {
            $parts = preg_split('/\r\n|\r|\n/u', $value) ?: [];

            return collect($parts)
                ->map(fn ($item) => trim((string) preg_replace('/^[-*•]\s*/u', '', $item)))
                ->filter()
                ->values()
                ->all();
        }

        return [];
    }

    private function fallbackParse(string $text): ?array
    {
        $summary = null;
        if (preg_match('/\"summary\"\\s*:\\s*\"((?:\\\\\"|[^\"])*)\"/s', $text, $summaryMatch)) {
            $summary = json_decode('"' . $summaryMatch[1] . '"');
        } elseif (preg_match('/\"summary\"\\s*:\\s*\"(.+)/s', $text, $summaryMatch)) {
            $summary = stripcslashes(rtrim($summaryMatch[1], "`\r\n\t "));
        }

        if (! is_string($summary) || trim($summary) === '') {
            return null;
        }

        return [
            'summary' => trim($summary),
            'health' => null,
            'highlights' => [],
            'risks' => [],
            'next_steps' => [],
        ];
    }
}
