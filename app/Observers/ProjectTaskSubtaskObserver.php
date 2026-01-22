<?php

namespace App\Observers;

use App\Models\ProjectTaskSubtask;
use App\Services\TaskStatusNotificationService;

class ProjectTaskSubtaskObserver
{
    public function created(ProjectTaskSubtask $subtask): void
    {
        app(TaskStatusNotificationService::class)
            ->notifySubtaskOpened($subtask);
    }

    public function updated(ProjectTaskSubtask $subtask): void
    {
        if (! $subtask->wasChanged('status')) {
            return;
        }

        $previousStatus = $subtask->getOriginal('status');

        app(TaskStatusNotificationService::class)
            ->notifySubtaskStatusTransition($subtask, $previousStatus);
    }
}
