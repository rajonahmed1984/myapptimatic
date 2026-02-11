<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Support\Currency;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProjectStatusAiService
{
    public function analyze(Project $project, GeminiService $geminiService): array
    {
        $prompt = $this->buildPrompt($project);
        $raw = $geminiService->generateText($prompt);
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
        $clean = trim($text);

        if (Str::startsWith($clean, '```')) {
            $clean = preg_replace('/^```[a-zA-Z]*\n|```$/m', '', $clean);
            $clean = trim((string) $clean);
        }

        $start = strpos($clean, '{');
        $end = strrpos($clean, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $json = substr($clean, $start, $end - $start + 1);
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            return null;
        }

        return [
            'summary' => Arr::get($decoded, 'summary'),
            'health' => Arr::get($decoded, 'health'),
            'highlights' => Arr::get($decoded, 'highlights'),
            'risks' => Arr::get($decoded, 'risks'),
            'next_steps' => Arr::get($decoded, 'next_steps'),
        ];
    }
}
