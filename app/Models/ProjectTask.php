<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTask extends Model
{
    protected $fillable = [
        'project_id',
        'title',
        'description',
        'status',
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
    ];

    protected $casts = [
        'start_date' => 'date',
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'customer_visible' => 'boolean',
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
}
