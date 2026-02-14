<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\ProjectMessage;
use App\Models\ProjectTask;
use App\Models\ProjectTaskMessage;
use App\Services\ChatAiService;
use App\Services\ChatAiSummaryCache;
use App\Services\GeminiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateChatAiSummaries extends Command
{
    protected $signature = 'chat:ai-summary {--type=project : project|task|all} {--days=7 : Look back window for recent chats} {--limit=200 : Max items to summarize per run} {--email : Send admin digest email} {--email-limit=30 : Max items to include in email}';

    protected $description = 'Generate and cache AI chat summaries for recent project/task chats.';

    public function handle(
        ChatAiService $aiService,
        GeminiService $geminiService,
        ChatAiSummaryCache $summaryCache
    ): int {
        if (! config('google_ai.enabled')) {
            $this->warn('Google AI is disabled. Skipping chat summary generation.');
            return self::SUCCESS;
        }

        if (! config('google_ai.api_key')) {
            $this->error('Missing GOOGLE_AI_API_KEY.');
            return self::FAILURE;
        }

        $type = strtolower((string) $this->option('type'));
        $days = max(1, (int) $this->option('days'));
        $limit = max(1, (int) $this->option('limit'));
        $emailLimit = max(1, (int) $this->option('email-limit'));
        $sendEmail = (bool) $this->option('email');
        $cutoff = now()->subDays($days);

        if (! in_array($type, ['project', 'task', 'all'], true)) {
            $this->error('Invalid type. Use project, task, or all.');
            return self::FAILURE;
        }

        if (in_array($type, ['project', 'all'], true)) {
            $this->line('Generating project chat summaries...');

            $projectIds = ProjectMessage::query()
                ->where('created_at', '>=', $cutoff)
                ->select('project_id')
                ->groupBy('project_id')
                ->orderByDesc(DB::raw('MAX(created_at)'))
                ->limit($limit)
                ->pluck('project_id');

            $projects = Project::query()
                ->whereIn('id', $projectIds)
                ->with('customer')
                ->get()
                ->sortBy(fn (Project $project) => array_search($project->id, $projectIds->all(), true))
                ->values();

            $emailItems = [];
            foreach ($projects as $project) {
                $result = $aiService->analyzeProjectChat($project, $geminiService);
                if (is_array($result['data'] ?? null)) {
                    $summary = $result['data'];
                    $summaryCache->putProject($project->id, $summary);

                    if ($sendEmail) {
                        $emailItems[] = [
                            'title' => $project->name,
                            'summary' => $summary['summary'] ?? '',
                            'sentiment' => $summary['sentiment'] ?? null,
                            'priority' => $summary['priority'] ?? null,
                            'actions' => $summary['action_items'] ?? [],
                            'url' => $this->safeUrl(fn () => route('admin.projects.chat', $project)),
                        ];
                    }
                }
            }

            if ($sendEmail && $emailItems) {
                app('App\\Services\\AdminNotificationService')->sendChatSummaryDigest('Project chat AI summary', $emailItems, $emailLimit);
            }
        }

        if (in_array($type, ['task', 'all'], true)) {
            $this->line('Generating task chat summaries...');

            $taskIds = ProjectTaskMessage::query()
                ->where('created_at', '>=', $cutoff)
                ->select('project_task_id')
                ->groupBy('project_task_id')
                ->orderByDesc(DB::raw('MAX(created_at)'))
                ->limit($limit)
                ->pluck('project_task_id');

            $tasks = ProjectTask::query()
                ->whereIn('id', $taskIds)
                ->with('project.customer')
                ->get()
                ->sortBy(fn (ProjectTask $task) => array_search($task->id, $taskIds->all(), true))
                ->values();

            $emailItems = [];
            foreach ($tasks as $task) {
                $project = $task->project;
                if (! $project) {
                    continue;
                }

                $result = $aiService->analyzeTaskChat($project, $task, $geminiService);
                if (is_array($result['data'] ?? null)) {
                    $summary = $result['data'];
                    $summaryCache->putTask($task->id, $summary);

                    if ($sendEmail) {
                        $emailItems[] = [
                            'title' => $task->title,
                            'summary' => $summary['summary'] ?? '',
                            'sentiment' => $summary['sentiment'] ?? null,
                            'priority' => $summary['priority'] ?? null,
                            'actions' => $summary['action_items'] ?? [],
                            'url' => $this->safeUrl(fn () => route('admin.projects.tasks.chat', [$project, $task])),
                        ];
                    }
                }
            }

            if ($sendEmail && $emailItems) {
                app('App\\Services\\AdminNotificationService')->sendChatSummaryDigest('Task chat AI summary', $emailItems, $emailLimit);
            }
        }

        $this->info('AI summaries updated.');
        return self::SUCCESS;
    }

    private function safeUrl(callable $callback): ?string
    {
        try {
            return (string) $callback();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
