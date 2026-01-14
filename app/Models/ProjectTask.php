<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectTask extends Model
{
    protected $fillable = [
        'project_id',
        'title',
        'description',
        'task_type',
        'status',
        'priority',
        'assignee_id',
        'assigned_type',
        'assigned_id',
        'due_date',
        'start_date',
        'completed_at',
        'notes',
        'customer_visible',
        'progress',
        'created_by',
        'time_estimate_minutes',
        'tags',
        'relationship_ids',
    ];

    protected $casts = [
        'start_date' => 'date',
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'customer_visible' => 'boolean',
        'tags' => 'array',
        'relationship_ids' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(function (ProjectTask $task) {
            if ($task->isDirty('start_date')) {
                throw new \RuntimeException('Start date is locked and cannot be modified after task creation.');
            }
            if ($task->isDirty('due_date')) {
                throw new \RuntimeException('Due date is locked and cannot be modified after task creation.');
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ProjectTaskMessage::class, 'project_task_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ProjectTaskAssignment::class, 'project_task_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ProjectTaskActivity::class, 'project_task_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(ProjectTaskSubtask::class, 'project_task_id');
    }

    public function getProgressAttribute($value): int
    {
        $subtasks = $this->relationLoaded('subtasks')
            ? $this->subtasks
            : $this->subtasks()->get();
        if ($subtasks->isEmpty()) {
            return (int) ($value ?? 0);
        }
        $completed = $subtasks->where('is_completed', true)->count();
        return (int) (($completed / $subtasks->count()) * 100);
    }
}
