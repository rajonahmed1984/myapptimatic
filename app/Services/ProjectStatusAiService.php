<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskSubtask;
use App\Support\Currency;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProjectStatusAiService
{
    public function analyze(Project $project, GeminiService $geminiService, ChatAiSummaryCache $summaryCache): array
    {
        $context = $this->buildContext($project, $summaryCache);
        $prompt = $this->buildPrompt($context);
        $raw = $geminiService->generateText($prompt, [
            'max_output_tokens' => 4096,
        ]);
        $data = $this->extractJson($raw);

        return [
            'raw' => $raw,
            'data' => $data,
            'context' => $context,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContext(Project $project, ChatAiSummaryCache $summaryCache): array
    {
        $project->loadMissing([
            'customer',
            'employees',
            'salesRepresentatives',
            'overheads',
        ]);

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

        $nextDueTask = ProjectTask::query()
            ->where('project_id', $project->id)
            ->whereNotNull('due_date')
            ->whereNotIn('status', ['completed', 'done'])
            ->orderBy('due_date')
            ->orderBy('id')
            ->first(['id', 'title', 'status', 'start_date', 'due_date']);

        $subtaskCounts = ProjectTaskSubtask::query()
            ->join('project_tasks', 'project_tasks.id', '=', 'project_task_subtasks.project_task_id')
            ->where('project_tasks.project_id', $project->id)
            ->selectRaw('COUNT(project_task_subtasks.id) as total')
            ->selectRaw('SUM(CASE WHEN project_task_subtasks.is_completed = 1 THEN 1 ELSE 0 END) as completed_count')
            ->selectRaw('SUM(CASE WHEN project_task_subtasks.is_completed = 0 THEN 1 ELSE 0 END) as open_count')
            ->selectRaw("SUM(CASE WHEN project_task_subtasks.is_completed = 0 AND project_task_subtasks.due_date IS NOT NULL AND DATE(project_task_subtasks.due_date) < ? THEN 1 ELSE 0 END) as overdue_count", [now()->toDateString()])
            ->selectRaw("SUM(CASE WHEN project_task_subtasks.is_completed = 0 AND project_task_subtasks.due_date IS NOT NULL AND DATE(project_task_subtasks.due_date) >= ? AND DATE(project_task_subtasks.due_date) <= ? THEN 1 ELSE 0 END) as due_soon_count", [now()->toDateString(), now()->addDays(7)->toDateString()])
            ->first();

        $nextDueSubtask = ProjectTaskSubtask::query()
            ->select([
                'project_task_subtasks.id',
                'project_task_subtasks.title',
                'project_task_subtasks.status',
                'project_task_subtasks.due_date',
                'project_tasks.title as task_title',
            ])
            ->join('project_tasks', 'project_tasks.id', '=', 'project_task_subtasks.project_task_id')
            ->where('project_tasks.project_id', $project->id)
            ->where('project_task_subtasks.is_completed', false)
            ->whereNotNull('project_task_subtasks.due_date')
            ->orderBy('project_task_subtasks.due_date')
            ->orderBy('project_task_subtasks.id')
            ->first();

        $taskTimelineFocus = ProjectTask::query()
            ->where('project_id', $project->id)
            ->where(function ($query) {
                $query->whereNotNull('due_date')
                    ->orWhereIn('status', ['blocked', 'in_progress', 'pending', 'todo']);
            })
            ->orderByRaw("CASE WHEN status = 'blocked' THEN 0 WHEN due_date IS NULL THEN 2 ELSE 1 END")
            ->orderBy('due_date')
            ->orderByDesc('created_at')
            ->limit(4)
            ->get(['title', 'status', 'start_date', 'due_date'])
            ->map(fn (ProjectTask $task) => [
                'title' => (string) $task->title,
                'status' => (string) ($task->status ?? 'pending'),
                'start_date' => $task->start_date?->format('Y-m-d'),
                'due_date' => $task->due_date?->format('Y-m-d'),
            ])
            ->values()
            ->all();

        $subtaskTimelineFocus = ProjectTaskSubtask::query()
            ->select([
                'project_task_subtasks.title',
                'project_task_subtasks.status',
                'project_task_subtasks.due_date',
                'project_tasks.title as task_title',
            ])
            ->join('project_tasks', 'project_tasks.id', '=', 'project_task_subtasks.project_task_id')
            ->where('project_tasks.project_id', $project->id)
            ->where(function ($query) {
                $query->whereNotNull('project_task_subtasks.due_date')
                    ->orWhere('project_task_subtasks.is_completed', false);
            })
            ->orderByRaw("CASE WHEN project_task_subtasks.is_completed = 0 AND project_task_subtasks.due_date IS NOT NULL AND DATE(project_task_subtasks.due_date) < ? THEN 0 WHEN project_task_subtasks.is_completed = 0 THEN 1 ELSE 2 END", [now()->toDateString()])
            ->orderBy('project_task_subtasks.due_date')
            ->limit(4)
            ->get()
            ->map(fn ($subtask) => [
                'title' => (string) ($subtask->title ?? 'Subtask'),
                'task_title' => (string) ($subtask->task_title ?? 'Task'),
                'status' => (string) ($subtask->status ?? 'pending'),
                'due_date' => $subtask->due_date?->format('Y-m-d'),
            ])
            ->values()
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

        $profitabilityLabel = $profit >= 0 ? 'profitable' : 'loss';
        $totalSubtasks = (int) ($subtaskCounts->total ?? 0);
        $completedSubtasks = (int) ($subtaskCounts->completed_count ?? 0);
        $openSubtasks = (int) ($subtaskCounts->open_count ?? 0);

        $cachedProjectChat = $summaryCache->getProject($project->id) ?? [];
        $latestProjectMessage = $project->messages()
            ->with(['userAuthor:id,name', 'employeeAuthor:id,name', 'salesRepAuthor:id,name'])
            ->latest('id')
            ->first();

        $taskChats = $project->tasks()
            ->withCount('messages')
            ->whereHas('messages')
            ->orderByDesc('messages_count')
            ->orderBy('due_date')
            ->limit(3)
            ->get(['id', 'title', 'status', 'due_date'])
            ->map(function (ProjectTask $task) use ($summaryCache) {
                $cachedSummary = $summaryCache->getTask($task->id) ?? [];
                $latestMessage = $task->messages()
                    ->with(['userAuthor:id,name', 'employeeAuthor:id,name', 'salesRepAuthor:id,name'])
                    ->latest('id')
                    ->first();

                return [
                    'task_title' => (string) $task->title,
                    'status' => (string) ($task->status ?? 'pending'),
                    'due_date' => $task->due_date?->format('Y-m-d'),
                    'messages_count' => (int) ($task->messages_count ?? 0),
                    'summary' => trim((string) ($cachedSummary['summary'] ?? '')),
                    'priority' => $cachedSummary['priority'] ?? null,
                    'sentiment' => $cachedSummary['sentiment'] ?? null,
                    'latest_activity' => $latestMessage ? $this->chatActivitySnippet($latestMessage) : null,
                ];
            })
            ->values()
            ->all();

        return [
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
            'timeline' => [
                'status' => $this->timelineLabel($project),
                'note' => $this->timelineNote($project),
            ],
            'tasks' => [
                'total' => $totalTasks,
                'open' => $openCount,
                'in_progress' => $inProgressCount,
                'completed' => $completedCount,
                'overdue' => $overdueCount,
                'due_soon_7d' => $dueSoonCount,
                'completion_rate' => $totalTasks > 0 ? (int) round(($completedCount / $totalTasks) * 100) : 0,
                'next_due' => $nextDueTask ? [
                    'title' => (string) $nextDueTask->title,
                    'status' => (string) ($nextDueTask->status ?? 'pending'),
                    'start_date' => $nextDueTask->start_date?->format('Y-m-d'),
                    'due_date' => $nextDueTask->due_date?->format('Y-m-d'),
                ] : null,
                'timeline_focus' => $taskTimelineFocus,
                'recent' => $recentTasks,
                'by_status' => $statusCounts->map(fn ($count) => (int) $count)->toArray(),
            ],
            'subtasks' => [
                'total' => $totalSubtasks,
                'open' => $openSubtasks,
                'completed' => $completedSubtasks,
                'overdue' => (int) ($subtaskCounts->overdue_count ?? 0),
                'due_soon_7d' => (int) ($subtaskCounts->due_soon_count ?? 0),
                'completion_rate' => $totalSubtasks > 0 ? (int) round(($completedSubtasks / $totalSubtasks) * 100) : 0,
                'next_due' => $nextDueSubtask ? [
                    'title' => (string) ($nextDueSubtask->title ?? 'Subtask'),
                    'task_title' => (string) ($nextDueSubtask->task_title ?? 'Task'),
                    'status' => (string) ($nextDueSubtask->status ?? 'pending'),
                    'due_date' => $nextDueSubtask->due_date?->format('Y-m-d'),
                ] : null,
                'timeline_focus' => $subtaskTimelineFocus,
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
                'profitability' => $profitabilityLabel,
            ],
            'people' => [
                'employees' => $project->employees->pluck('name')->filter()->values()->all(),
                'sales_reps' => $project->salesRepresentatives->pluck('name')->filter()->values()->all(),
            ],
            'project_chat' => [
                'summary' => trim((string) ($cachedProjectChat['summary'] ?? '')),
                'priority' => $cachedProjectChat['priority'] ?? null,
                'sentiment' => $cachedProjectChat['sentiment'] ?? null,
                'generated_at' => $cachedProjectChat['generated_at'] ?? null,
                'messages_count' => (int) $project->messages()->count(),
                'latest_activity' => $latestProjectMessage ? $this->chatActivitySnippet($latestProjectMessage) : null,
            ],
            'task_chats' => $taskChats,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function buildPrompt(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return <<<PROMPT
You are a senior project delivery analyst. Write a concise but rich project status summary in Bengali.

Rules:
- Use only the provided data. Do not invent facts.
- Output MUST be strict JSON (no code fences) with keys: summary, health, highlights, risks, next_steps, profitability, timeline, task_focus, subtask_focus, chat_focus.
- summary: 2-3 sentences.
- health: green / yellow / red.
- highlights, risks, next_steps: arrays of 2-4 bullet strings.
- profitability, timeline, task_focus, subtask_focus, chat_focus: arrays of 2-4 short bullet strings.
- Mention task/subtask deadlines, profitability direction, and chat signals when those exist.
- If data is missing, say that clearly instead of guessing.

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
            'profitability' => Arr::get($decoded, 'profitability'),
            'timeline' => Arr::get($decoded, 'timeline'),
            'task_focus' => Arr::get($decoded, 'task_focus'),
            'subtask_focus' => Arr::get($decoded, 'subtask_focus'),
            'chat_focus' => Arr::get($decoded, 'chat_focus'),
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
            'profitability' => $this->normalizeList($payload['profitability']),
            'timeline' => $this->normalizeList($payload['timeline']),
            'task_focus' => $this->normalizeList($payload['task_focus']),
            'subtask_focus' => $this->normalizeList($payload['subtask_focus']),
            'chat_focus' => $this->normalizeList($payload['chat_focus']),
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
            'profitability' => [],
            'timeline' => [],
            'task_focus' => [],
            'subtask_focus' => [],
            'chat_focus' => [],
        ];
    }

    private function timelineLabel(Project $project): string
    {
        $today = now()->startOfDay();
        $isClosed = in_array((string) $project->status, ['complete', 'cancel'], true);
        $dueDate = $project->due_date?->copy()->startOfDay();
        $expectedEndDate = $project->expected_end_date?->copy()->startOfDay();

        if ($isClosed) {
            return 'closed';
        }

        if ($dueDate && $dueDate->lt($today)) {
            return 'past_due';
        }

        if ($dueDate && $dueDate->lte($today->copy()->addDays(7))) {
            return 'due_soon';
        }

        if ($expectedEndDate && $expectedEndDate->lt($today)) {
            return 'past_expected_end';
        }

        if (! $dueDate && ! $expectedEndDate) {
            return 'timeline_missing';
        }

        return 'on_track';
    }

    private function timelineNote(Project $project): string
    {
        $today = now()->startOfDay();
        $label = $this->timelineLabel($project);

        return match ($label) {
            'closed' => 'Project is already closed.',
            'past_due' => $project->due_date ? $project->due_date->diffInDays($today).' days overdue.' : 'Due date already passed.',
            'due_soon' => $project->due_date ? $today->diffInDays($project->due_date->copy()->startOfDay()).' days left to due date.' : 'Due date is approaching.',
            'past_expected_end' => 'Expected end date already passed.',
            'timeline_missing' => 'No expected end date or due date is set.',
            default => $project->due_date
                ? $today->diffInDays($project->due_date->copy()->startOfDay()).' days left to due date.'
                : 'Timeline currently looks stable.',
        };
    }

    private function chatActivitySnippet(object $message): string
    {
        $author = method_exists($message, 'authorName')
            ? (string) $message->authorName()
            : 'User';

        $body = Str::squish((string) ($message->message ?? ''));
        if ($body === '' && ! empty($message->attachment_path)) {
            $body = '[attachment]';
        }

        if ($body === '') {
            $body = 'No text message.';
        }

        return $author.': '.Str::limit($body, 140);
    }
}
