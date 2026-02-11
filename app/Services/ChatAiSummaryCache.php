<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class ChatAiSummaryCache
{
    private const PROJECT_KEY = 'chat_ai:project:%d';
    private const TASK_KEY = 'chat_ai:task:%d';

    public function getProject(int $projectId): ?array
    {
        return Cache::get(sprintf(self::PROJECT_KEY, $projectId));
    }

    public function getTask(int $taskId): ?array
    {
        return Cache::get(sprintf(self::TASK_KEY, $taskId));
    }

    public function putProject(int $projectId, array $data, int $ttlDays = 7): void
    {
        $payload = $this->buildPayload($data);
        Cache::put(sprintf(self::PROJECT_KEY, $projectId), $payload, now()->addDays($ttlDays));
    }

    public function putTask(int $taskId, array $data, int $ttlDays = 7): void
    {
        $payload = $this->buildPayload($data);
        Cache::put(sprintf(self::TASK_KEY, $taskId), $payload, now()->addDays($ttlDays));
    }

    private function buildPayload(array $data): array
    {
        return array_merge($data, [
            'generated_at' => now()->toDateTimeString(),
        ]);
    }
}
