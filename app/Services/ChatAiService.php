<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectTask;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ChatAiService
{
    public function analyzeProjectChat(Project $project, GeminiService $geminiService): array
    {
        $project->loadMissing('customer');

        $messages = $project->messages()
            ->with(['userAuthor', 'employeeAuthor', 'salesRepAuthor'])
            ->latest('id')
            ->limit(30)
            ->get()
            ->reverse()
            ->values();

        return $this->analyze([
            'type' => 'project_chat',
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'status' => $project->status,
                'customer' => $project->customer?->name,
            ],
        ], $messages, $geminiService);
    }

    public function analyzeTaskChat(Project $project, ProjectTask $task, GeminiService $geminiService): array
    {
        $project->loadMissing('customer');
        $task->loadMissing('project');

        $messages = $task->messages()
            ->with(['userAuthor', 'employeeAuthor', 'salesRepAuthor'])
            ->latest('id')
            ->limit(30)
            ->get()
            ->reverse()
            ->values();

        return $this->analyze([
            'type' => 'task_chat',
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'status' => $project->status,
                'customer' => $project->customer?->name,
            ],
            'task' => [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status,
                'due_date' => $task->due_date?->format('Y-m-d'),
            ],
        ], $messages, $geminiService);
    }

    private function analyze(array $context, $messages, GeminiService $geminiService): array
    {
        $prompt = $this->buildPrompt($context, $messages);
        $raw = $geminiService->generateText($prompt);
        $data = $this->extractJson($raw);

        return [
            'raw' => $raw,
            'data' => $data,
        ];
    }

    private function buildPrompt(array $context, $messages): string
    {
        if ($messages->count() > 20) {
            $messages = $messages->slice($messages->count() - 20)->values();
        }

        $conversation = $messages->map(function ($message) {
            $text = (string) ($message->message ?? '');
            $text = Str::squish($text);
            if (strlen($text) > 900) {
                $text = Str::limit($text, 900, '...');
            }

            $attachment = $message->attachment_path ? '[attachment]' : null;

            return [
                'author' => $message->authorName(),
                'role' => $message->authorTypeLabel(),
                'at' => $message->created_at?->format('Y-m-d H:i'),
                'message' => $text,
                'attachment' => $attachment,
            ];
        })->all();

        $payload = [
            'context' => $context,
            'conversation' => $conversation,
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return <<<PROMPT
You are a helpful operations assistant reviewing a team chat conversation.

Rules:
- Use only the provided data. Do not invent facts.
- Output MUST be strict JSON (no code fences) with keys:
  summary, sentiment, priority, reply_draft, action_items
- summary: 2-3 sentences in Bengali.
- sentiment: calm / neutral / frustrated / urgent
- priority: low / medium / high
- reply_draft: a short helpful reply in Bengali. If no reply is needed, use an empty string.
- action_items: array of 2-4 bullet strings.

Chat data (JSON):
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
            'sentiment' => Arr::get($decoded, 'sentiment'),
            'priority' => Arr::get($decoded, 'priority'),
            'reply_draft' => Arr::get($decoded, 'reply_draft'),
            'action_items' => Arr::get($decoded, 'action_items'),
        ];
    }
}
