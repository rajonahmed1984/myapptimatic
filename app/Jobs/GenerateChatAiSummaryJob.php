<?php

namespace App\Jobs;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\SupportTicket;
use App\Services\ChatAiService;
use App\Services\ChatAiSummaryCache;
use App\Services\GeminiService;
use App\Services\SupportTicketAiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GenerateChatAiSummaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $type, // 'project', 'task', or 'ticket'
        public int $id
    ) {
    }

    public function handle(
        ChatAiService $aiService,
        SupportTicketAiService $ticketAiService,
        GeminiService $geminiService,
        ChatAiSummaryCache $summaryCache
    ): void {
        if (! config('google_ai.enabled') || ! config('google_ai.api_key')) {
            return;
        }

        try {
            if ($this->type === 'project') {
                $project = Project::find($this->id);
                if ($project) {
                    $result = $aiService->analyzeProjectChat($project, $geminiService);
                    if (is_array($result['data'] ?? null)) {
                        $result['data']['generated_at'] = now()->toDateTimeString();
                        $summaryCache->putProject($project->id, $result['data']);
                    }
                }
            } elseif ($this->type === 'task') {
                $task = ProjectTask::find($this->id);
                if ($task) {
                    $project = $task->project ?? Project::find($task->project_id);
                    if ($project) {
                        $result = $aiService->analyzeTaskChat($project, $task, $geminiService);
                        if (is_array($result['data'] ?? null)) {
                            $result['data']['generated_at'] = now()->toDateTimeString();
                            $summaryCache->putTask($task->id, $result['data']);
                        }
                    }
                }
            } elseif ($this->type === 'ticket') {
                $ticket = SupportTicket::find($this->id);
                if ($ticket) {
                    $result = $ticketAiService->analyze($ticket, $geminiService);
                    Cache::put('ai:ticket-summary:'.$ticket->id, $result, now()->addMinutes(15));
                }
            }
        } catch (\Throwable $e) {
            Log::error('Failed to generate chat AI summary in background job.', [
                'type' => $this->type,
                'id' => $this->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
