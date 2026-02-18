<?php

namespace App\Services;

use App\Models\ProjectTask;
use App\Notifications\TaskStatusCompletedNotification;
use App\Notifications\TaskStatusOpenedNotification;
use App\Support\TaskNotificationRecipients;
use Illuminate\Support\Facades\Notification;

class TaskStatusNotificationService
{
    public function notifyTaskOpened(ProjectTask $task): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        if (! $this->isOpenTaskStatus($task->status)) {
            return;
        }

        $this->sendTaskNotification($task, 'open');
    }

    public function notifyTaskStatusTransition(ProjectTask $task, ?string $previousStatus): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        if ($this->isOpenTaskStatus($task->status) && ! $this->isOpenTaskStatus($previousStatus)) {
            $this->sendTaskNotification($task, 'open');
        }

        if ($this->isCompletedTaskStatus($task->status) && ! $this->isCompletedTaskStatus($previousStatus)) {
            $this->sendTaskNotification($task, 'completed');
        }
    }

    private function sendTaskNotification(ProjectTask $task, string $type): void
    {
        $recipients = TaskNotificationRecipients::forTask($task);

        if (empty($recipients)) {
            return;
        }

        foreach ($recipients as $recipient) {
            $notification = $type === 'completed'
                ? new TaskStatusCompletedNotification(
                    $task,
                    null,
                    $recipient['view_url'],
                    $recipient['project_url'],
                    $recipient['portal_login_url'],
                    $recipient['portal_login_label']
                )
                : new TaskStatusOpenedNotification(
                    $task,
                    null,
                    $recipient['view_url'],
                    $recipient['project_url'],
                    $recipient['portal_login_url'],
                    $recipient['portal_login_label']
                );

            Notification::route('mail', $recipient['email'])->notify($notification);
        }
    }

    private function isEnabled(): bool
    {
        return (bool) config('task-notifications.enabled', true);
    }

    private function isOpenTaskStatus(?string $status): bool
    {
        $status = $status ?? 'pending';
        return in_array($status, ['pending', 'todo', 'blocked', 'open'], true);
    }

    private function isCompletedTaskStatus(?string $status): bool
    {
        return in_array($status, ['completed', 'done'], true);
    }

}
