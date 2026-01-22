<?php

namespace App\Observers;

use App\Models\ProjectTask;
use App\Services\TaskStatusNotificationService;

class ProjectTaskObserver
{
    public function updated(ProjectTask $task): void
    {
        if (! $task->wasChanged('status')) {
            return;
        }

        $previousStatus = $task->getOriginal('status');

        app(TaskStatusNotificationService::class)
            ->notifyTaskStatusTransition($task, $previousStatus);
    }
}
