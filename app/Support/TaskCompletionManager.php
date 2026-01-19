<?php

namespace App\Support;

use App\Models\ProjectTask;

class TaskCompletionManager
{
    public static function hasSubtasks(ProjectTask $task): bool
    {
        return $task->subtasks()->exists();
    }

    public static function allSubtasksCompleted(ProjectTask $task): bool
    {
        $total = $task->subtasks()->count();
        if ($total === 0) {
            return true;
        }

        $completed = $task->subtasks()->where('is_completed', 1)->count();
        return $completed === $total;
    }

    public static function syncFromSubtasks(ProjectTask $task): bool
    {
        $subtasks = $task->subtasks()->get(['id', 'is_completed']);
        if ($subtasks->isEmpty()) {
            return false;
        }

        $allCompleted = $subtasks->every(fn ($subtask) => (bool) $subtask->is_completed);
        $updates = [];

        if ($allCompleted) {
            if (! in_array($task->status, ['completed', 'done'], true)) {
                $updates['status'] = 'completed';
            }
            if (! $task->completed_at) {
                $updates['completed_at'] = now();
            }
        } else {
            if (in_array($task->status, ['completed', 'done'], true)) {
                $updates['status'] = 'in_progress';
                $updates['completed_at'] = null;
            }
        }

        if (empty($updates)) {
            return false;
        }

        $task->update($updates);
        return true;
    }
}
