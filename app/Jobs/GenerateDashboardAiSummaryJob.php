<?php

namespace App\Jobs;

use App\Services\BusinessStatusSummaryService;
use App\Services\ExpenseEntryService;
use App\Services\GeminiService;
use App\Services\IncomeEntryService;
use App\Services\TaskQueryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GenerateDashboardAiSummaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $type, // 'main', 'income', 'expense', 'project'
        public string $cacheKey,
        public array $params,
        public int $cacheMinutes = 10
    ) {
    }

    public function handle(
        GeminiService $geminiService,
        BusinessStatusSummaryService $businessStatusSummaryService
    ): void {
        if (! config('google_ai.enabled') || ! config('google_ai.api_key')) {
            return;
        }

        try {
            if ($this->type === 'main') {
                $user = \App\Models\User::find($this->params['user_id']);
                $startDate = \Carbon\Carbon::parse($this->params['start_date']);
                $endDate = \Carbon\Carbon::parse($this->params['end_date']);
                $projectionDays = $this->params['projection_days'];

                $metrics = $businessStatusSummaryService->buildMetricsCached(
                    $startDate,
                    $endDate,
                    $projectionDays,
                    $user,
                    app(IncomeEntryService::class),
                    app(ExpenseEntryService::class),
                    app(TaskQueryService::class),
                    false
                );

                $summary = $businessStatusSummaryService->summarizeDashboard($metrics, $geminiService);
                Cache::put($this->cacheKey, $summary, now()->addMinutes($this->cacheMinutes));
            } elseif ($this->type === 'income') {
                $filters = $this->params['filters'];
                $count = $this->params['count'];
                $currencyCode = $this->params['currencyCode'];
                $totalAmount = $this->params['totalAmount'];
                $manualTotal = $this->params['manualTotal'];
                $systemTotal = $this->params['systemTotal'];
                $creditSettlementTotal = $this->params['creditSettlementTotal'];
                $carrotHostTotal = $this->params['carrotHostTotal'];
                $categoryTotals = $this->params['categoryTotals'];
                $topCustomers = $this->params['topCustomers'];

                $startDate = $filters['start_date'] ?: 'all time';
                $endDate = $filters['end_date'] ?: 'today';

                $topCategories = collect($categoryTotals)->take(3)->map(function ($item) use ($currencyCode) {
                    $amount = number_format((float) ($item['total'] ?? 0), 2);
                    return "{$item['name']}: {$currencyCode} {$amount}";
                })->implode(', ');

                $topCustomerText = collect($topCustomers)->take(3)->map(function ($item) use ($currencyCode) {
                    $amount = number_format((float) ($item['total'] ?? 0), 2);
                    return "{$item['name']}: {$currencyCode} {$amount}";
                })->implode(', ');

                $selectedSources = collect($filters['sources'] ?? [])
                    ->map(fn ($source) => match ((string) $source) {
                        'manual' => 'Manual',
                        'system' => 'System',
                        'credit_settlement' => 'Credit Settlement',
                        'carrothost' => 'CarrotHost',
                        default => ucfirst((string) $source),
                    })
                    ->implode(', ');

                $prompt = <<<PROMPT
You are a senior finance analyst. Write a richer income dashboard summary in Bengali for an admin user.

Period: {$startDate} to {$endDate}
Selected sources: {$selectedSources}
Totals:
- Total income: {$currencyCode} {$totalAmount}
- Manual income: {$currencyCode} {$manualTotal}
- System income: {$currencyCode} {$systemTotal}
- Credit settlement: {$currencyCode} {$creditSettlementTotal}
- CarrotHost income (net): {$currencyCode} {$carrotHostTotal}
- Entries: {$count}

Top categories: {$topCategories}
Top customers: {$topCustomerText}

Instructions:
- Start with a short 1-2 sentence executive overview.
- Then provide 5-7 concise bullet points.
- Must mention total income, source mix, strongest category, strongest customer, and filtered scope.
- If one source is unusually dominant, mention that clearly.
- Keep it practical and management-friendly, not generic.
PROMPT;

                $summary = $geminiService->generateText($prompt);
                if ($summary) {
                    Cache::put($this->cacheKey, $summary, now()->addMinutes($this->cacheMinutes));
                }
            } elseif ($this->type === 'expense') {
                $startDate = $this->params['startDate'];
                $endDate = $this->params['endDate'];
                $currencyCode = $this->params['currencyCode'];
                $expenseTotal = $this->params['expenseTotal'];
                $expenseBySource = $this->params['expenseBySource'];
                $categoryTotals = $this->params['categoryTotals'];
                $employeeTotals = $this->params['employeeTotals'];
                $filters = $this->params['filters'];

                $topCategories = collect($categoryTotals)->take(3)->map(function ($item) use ($currencyCode) {
                    $amount = number_format((float) ($item['total'] ?? 0), 2);
                    return "{$item['name']}: {$currencyCode} {$amount}";
                })->implode(', ');

                $topEmployees = collect($employeeTotals)->take(3)->map(function ($item) use ($currencyCode) {
                    $amount = number_format((float) ($item['total'] ?? 0), 2);
                    return "{$item['label']}: {$currencyCode} {$amount}";
                })->implode(', ');

                $manualTotal = number_format((float) ($expenseBySource['manual'] ?? 0), 2);
                $salaryTotal = number_format((float) ($expenseBySource['salary'] ?? 0), 2);
                $contractTotal = number_format((float) ($expenseBySource['contract_payout'] ?? 0), 2);
                $salesTotal = number_format((float) ($expenseBySource['sales_payout'] ?? 0), 2);
                $periodStart = $startDate ?: 'all time';
                $periodEnd = $endDate ?: 'today';
                $selectedSources = collect($filters['sources'] ?? [])
                    ->map(fn ($source) => match ((string) $source) {
                        'manual' => 'Manual',
                        'salary' => 'Salary',
                        'contract_payout' => 'Contract Payout',
                        'sales_payout' => 'Sales Rep Payout',
                        default => ucfirst((string) $source),
                    })
                    ->implode(', ');

                $prompt = <<<PROMPT
You are a senior finance analyst. Write a richer expense dashboard summary in Bengali for an admin user.

Period: {$periodStart} to {$periodEnd}
Selected sources: {$selectedSources}
Totals:
- Total expenses: {$currencyCode} {$expenseTotal}
- Manual expenses: {$currencyCode} {$manualTotal}
- Salary expenses: {$currencyCode} {$salaryTotal}
- Contract payouts: {$currencyCode} {$contractTotal}
- Sales rep payouts: {$currencyCode} {$salesTotal}

Top categories: {$topCategories}
Top employees: {$topEmployees}

Instructions:
- Start with a short 1-2 sentence executive overview.
- Then provide 5-7 concise bullet points.
- Must mention total expense, source mix, strongest category, biggest payee, and filtered scope.
- If one source dominates spending, mention it clearly.
- Keep it practical and management-friendly, not generic.
PROMPT;

                $summary = $geminiService->generateText($prompt);
                if ($summary) {
                    Cache::put($this->cacheKey, $summary, now()->addMinutes($this->cacheMinutes));
                }
            } elseif ($this->type === 'project') {
                $snapshot = $this->params['snapshot'];
                $riskProjects = $this->params['riskProjects'];
                $recentProjects = $this->params['recentProjects'];
                $focusProjects = $this->params['focusProjects'];

                $budgetTotals = collect($snapshot['budget_totals'] ?? [])
                    ->take(3)
                    ->pluck('display')
                    ->implode(', ');

                $paidTotals = collect($snapshot['paid_totals'] ?? [])
                    ->take(3)
                    ->pluck('display')
                    ->implode(', ');

                $riskNames = collect($riskProjects)
                    ->take(5)
                    ->map(fn ($project) => sprintf(
                        '%s (overdue: %d, due soon: %d)',
                        $project['name'] ?? 'Project',
                        (int) ($project['overdue_tasks'] ?? 0),
                        (int) ($project['due_soon_tasks'] ?? 0)
                    ))
                    ->implode(', ');

                $recentNames = collect($recentProjects)
                    ->take(5)
                    ->map(fn ($project) => sprintf(
                        '%s [%s]',
                        $project['name'] ?? 'Project',
                        $project['status_label'] ?? 'Unknown'
                    ))
                    ->implode(', ');

                $focusProjectsJson = json_encode(
                    collect($focusProjects)
                        ->map(fn (array $project) => [
                            'project' => [
                                'name' => $project['name'] ?? 'Project',
                                'customer' => $project['customer_name'] ?? '--',
                                'status' => $project['status_label'] ?? '--',
                                'timeline' => [
                                    'label' => data_get($project, 'timeline.label'),
                                    'note' => data_get($project, 'timeline.note'),
                                    'start' => data_get($project, 'timeline.start'),
                                    'expected_end' => data_get($project, 'timeline.expected_end'),
                                    'due' => data_get($project, 'timeline.due'),
                                ],
                            ],
                            'profitability' => [
                                'label' => data_get($project, 'profitability.label'),
                                'profit' => data_get($project, 'profitability.profit_display'),
                                'budget_with_overhead' => data_get($project, 'financials.budget_with_overhead_display'),
                                'payouts_total' => data_get($project, 'financials.payouts_total_display'),
                            ],
                            'tasks' => [
                                'total' => data_get($project, 'tasks.total'),
                                'open' => data_get($project, 'tasks.open'),
                                'in_progress' => data_get($project, 'tasks.in_progress'),
                                'blocked' => data_get($project, 'tasks.blocked'),
                                'completed' => data_get($project, 'tasks.completed'),
                                'overdue' => data_get($project, 'tasks.overdue'),
                                'due_soon' => data_get($project, 'tasks.due_soon'),
                                'completion_rate' => data_get($project, 'tasks.completion_rate'),
                                'next_due' => data_get($project, 'tasks.next_due'),
                            ],
                            'subtasks' => [
                                'total' => data_get($project, 'subtasks.total'),
                                'open' => data_get($project, 'subtasks.open'),
                                'completed' => data_get($project, 'subtasks.completed'),
                                'overdue' => data_get($project, 'subtasks.overdue'),
                                'due_soon' => data_get($project, 'subtasks.due_soon'),
                                'completion_rate' => data_get($project, 'subtasks.completion_rate'),
                                'next_due' => data_get($project, 'subtasks.next_due'),
                            ],
                            'chat' => [
                                'project_summary' => data_get($project, 'project_chat.summary'),
                                'project_latest_activity' => data_get($project, 'project_chat.latest_activity'),
                                'task_chat_summaries' => data_get($project, 'task_chats'),
                            ],
                        ])
                        ->values()
                        ->all(),
                    JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
                );

                $prompt = <<<PROMPT
You are a PMO analyst. Summarize the projects dashboard in Bengali.

Rules:
- Use only the provided data. Do not invent facts.
- Focus on project profitability, task and subtask delivery pressure, timeline risk, and chat context.
- If any chat summary is missing, say the chat insight is unavailable instead of guessing.
- Return 6-10 concise bullet points in Bengali.
- Mention immediate priorities where needed.

Portfolio snapshot:
- Total projects: {$snapshot['total_projects']}
- Ongoing: {$snapshot['ongoing_projects']}
- On hold: {$snapshot['hold_projects']}
- Completed: {$snapshot['completed_projects']}
- Cancelled: {$snapshot['cancelled_projects']}
- Total tasks: {$snapshot['total_tasks']}
- Open tasks: {$snapshot['open_tasks']}
- In progress tasks: {$snapshot['in_progress_tasks']}
- Completed tasks: {$snapshot['completed_tasks']}
- Completion rate: {$snapshot['completion_rate']}%
- Overdue tasks: {$snapshot['overdue_tasks']}
- Due soon (7d): {$snapshot['due_soon_tasks']}
- Active maintenances: {$snapshot['active_maintenances']}
- Budget totals: {$budgetTotals}
- Paid totals: {$paidTotals}

Risk projects: {$riskNames}
Recent projects: {$recentNames}
Focus projects (JSON):
{$focusProjectsJson}

PROMPT;

                $summary = $geminiService->generateText($prompt);
                if ($summary) {
                    Cache::put($this->cacheKey, $summary, now()->addMinutes($this->cacheMinutes));
                }
            }
        } catch (\Throwable $e) {
            Log::error('Failed to generate dashboard AI summary in background job.', [
                'type' => $this->type,
                'cache_key' => $this->cacheKey,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
