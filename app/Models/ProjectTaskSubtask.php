<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTaskSubtask extends Model
{
    protected $table = 'project_task_subtasks';

    protected $fillable = [
        'project_task_id',
        'title',
        'due_date',
        'due_time',
        'is_completed',
        'completed_at',
        'created_by',
    ];

    protected $casts = [
        'due_date' => 'date',
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'project_task_id');
    }

    public function creatorEditWindowExpired(?int $userId): bool
    {
        if (! $userId || ! $this->created_by || $this->created_by !== $userId) {
            return false;
        }

        if (! $this->created_at) {
            return true;
        }

        return $this->created_at->copy()->addHours(24)->isPast();
    }
}
