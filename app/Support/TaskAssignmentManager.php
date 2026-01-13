<?php

namespace App\Support;

use App\Models\ProjectTask;
use App\Support\TaskAssignees;

class TaskAssignmentManager
{
    public static function sync(ProjectTask $task, array $assignees): array
    {
        $before = $task->assignments()
            ->get()
            ->map(fn ($assignment) => $assignment->assignee_type . ':' . $assignment->assignee_id)
            ->values()
            ->all();

        $task->assignments()->delete();

        foreach ($assignees as $assignee) {
            $task->assignments()->create([
                'assignee_type' => $assignee['type'],
                'assignee_id' => $assignee['id'],
            ]);
        }

        $first = $assignees[0] ?? null;
        $task->assigned_type = $first['type'] ?? null;
        $task->assigned_id = $first['id'] ?? null;
        $task->save();

        return [
            'before' => $before,
            'after' => TaskAssignees::toStrings($assignees),
        ];
    }
}
