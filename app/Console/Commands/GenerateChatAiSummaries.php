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

        if ($type === 'project' || $type === 'all') {
            $this->line('Generating project chat summaries...');
            $emailItems = [];
            $projectIds = ProjectMessage::query()
                ->where('created_at', '>=', $cutoff)
                ->select('project_id', DB::raw('MAX(created_at) as last_at'))
                ->groupBy('project_id')
                ->orderByDesc('last_at')
                ->limit($limit)
                ->pluck('project_id');

            $projects = Project::query()->whereIn('id', $projectIds)->get();
            foreach ($projects as $project) {
                try {
                    $chatUrl = $this->safeUrl(fn () => route('admin.projects.chat', $project));
                    $result = $aiService->analyzeProjectChat($project, $geminiService);
                    if (is_array($result['data'] ?? null)) {
                        $summaryCache->putProject($project->id, $result['data']);
                        if ($sendEmail && count($emailItems) < $emailLimit) {
                            $emailItems[] = [
                                'label' => "Project #{$project->id} - {$project->name}",
                                'summary' => $result['data']['summary'] ?? '',
                                'sentiment' => $result['data']['sentiment'] ?? '--',
                                'priority' => $result['data']['priority'] ?? '--',
                                'action_items' => $result['data']['action_items'] ?? [],
                                'generated_at' => $result['data']['generated_at'] ?? now()->toDateTimeString(),
                                'url' => $chatUrl,
                            ];
                        }
                    }
                    $this->line("- Project {$project->id} summarized");
                } catch (\Throwable $e) {
                    $this->warn("- Project {$project->id} failed: {$e->getMessage()}");
                }
            }

            if ($sendEmail) {
                app(\App\Services\AdminNotificationService::class)
                    ->sendChatSummaryDigest('Project chat AI summary', $emailItems);
            }
        }

        if ($type === 'task' || $type === 'all') {
            $this->line('Generating task chat summaries...');
            $emailItems = [];
            $taskIds = ProjectTaskMessage::query()
                ->where('created_at', '>=', $cutoff)
                ->select('project_task_id', DB::raw('MAX(created_at) as last_at'))
                ->groupBy('project_task_id')
                ->orderByDesc('last_at')
                ->limit($limit)
                ->pluck('project_task_id');

            $tasks = ProjectTask::query()->with('project')->whereIn('id', $taskIds)->get();
            foreach ($tasks as $task) {
                if (! $task->project) {
                    continue;
                }
                try {
                    $chatUrl = $this->safeUrl(fn () => route('admin.projects.tasks.chat', [$task->project, $task]));
                    $result = $aiService->analyzeTaskChat($task->project, $task, $geminiService);
                    if (is_array($result['data'] ?? null)) {
                        $summaryCache->putTask($task->id, $result['data']);
                        if ($sendEmail && count($emailItems) < $emailLimit) {
                            $emailItems[] = [
                                'label' => "Task #{$task->id} - {$task->title} (Project: {$task->project->name})",
                                'summary' => $result['data']['summary'] ?? '',
                                'sentiment' => $result['data']['sentiment'] ?? '--',
                                'priority' => $result['data']['priority'] ?? '--',
                                'action_items' => $result['data']['action_items'] ?? [],
                                'generated_at' => $result['data']['generated_at'] ?? now()->toDateTimeString(),
                                'url' => $chatUrl,
                            ];
                        }
                    }
                    $this->line("- Task {$task->id} summarized");
                } catch (\Throwable $e) {
                    $this->warn("- Task {$task->id} failed: {$e->getMessage()}");
                }
            }

            if ($sendEmail) {
                app(\App\Services\AdminNotificationService::class)
                    ->sendChatSummaryDigest('Task chat AI summary', $emailItems);
            }
        }

        return self::SUCCESS;
    }

    private function safeUrl(callable $resolver): ?string
    {
        try {
            return $resolver();
        } catch (\Throwable) {
            $base = config('app.url');
            return $base ?: null;
        }
    }
}
