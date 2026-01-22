<?php

namespace App\Services;

use App\Models\ProjectTask;
use App\Models\ProjectTaskSubtask;
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

        $this->sendTaskNotification($task, null, 'open');
    }

    public function notifyTaskStatusTransition(ProjectTask $task, ?string $previousStatus): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        if ($this->isOpenTaskStatus($task->status) && ! $this->isOpenTaskStatus($previousStatus)) {
            $this->sendTaskNotification($task, null, 'open');
        }

        if ($this->isCompletedTaskStatus($task->status) && ! $this->isCompletedTaskStatus($previousStatus)) {
            $this->sendTaskNotification($task, null, 'completed');
        }
    }

    public function notifySubtaskOpened(ProjectTaskSubtask $subtask): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        if (! $this->isOpenSubtaskStatus($subtask->status)) {
            return;
        }

        $task = $subtask->task;
        if (! $task) {
            return;
        }

        $this->sendTaskNotification($task, $subtask, 'open');
    }

    public function notifySubtaskStatusTransition(ProjectTaskSubtask $subtask, ?string $previousStatus): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $task = $subtask->task;
        if (! $task) {
            return;
        }

        if ($this->isOpenSubtaskStatus($subtask->status) && ! $this->isOpenSubtaskStatus($previousStatus)) {
            $this->sendTaskNotification($task, $subtask, 'open');
        }

        if ($this->isCompletedSubtaskStatus($subtask->status) && ! $this->isCompletedSubtaskStatus($previousStatus)) {
            $this->sendTaskNotification($task, $subtask, 'completed');
        }
    }

    private function sendTaskNotification(ProjectTask $task, ?ProjectTaskSubtask $subtask, string $type): void
    {
        $recipients = $subtask
            ? TaskNotificationRecipients::forSubtask($subtask, $task)
            : TaskNotificationRecipients::forTask($task);

        if (empty($recipients)) {
            return;
        }

        foreach ($recipients as $recipient) {
            $notification = $type === 'completed'
                ? new TaskStatusCompletedNotification(
                    $task,
                    $subtask,
                    $recipient['view_url'],
                    $recipient['project_url'],
                    $recipient['portal_login_url'],
                    $recipient['portal_login_label']
                )
                : new TaskStatusOpenedNotification(
                    $task,
                    $subtask,
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

    private function isOpenSubtaskStatus(?string $status): bool
    {
        $status = $status ?? 'open';
        return $status === 'open';
    }

    private function isCompletedSubtaskStatus(?string $status): bool
    {
        return $status === 'completed';
    }
}
