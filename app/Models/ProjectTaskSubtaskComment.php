<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectTaskSubtaskComment extends Model
{
    protected $fillable = [
        'project_task_id',
        'project_task_subtask_id',
        'parent_id',
        'actor_type',
        'actor_id',
        'message',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'project_task_id');
    }

    public function subtask(): BelongsTo
    {
        return $this->belongsTo(ProjectTaskSubtask::class, 'project_task_subtask_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('created_at');
    }

    public function userActor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function employeeActor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'actor_id');
    }

    public function salesRepActor(): BelongsTo
    {
        return $this->belongsTo(SalesRepresentative::class, 'actor_id');
    }

    public function actorName(): string
    {
        return match ($this->actor_type) {
            'employee' => $this->employeeActor?->name ?? 'Employee',
            'sales_rep', 'salesrep' => $this->salesRepActor?->name ?? 'Sales Rep',
            default => $this->userActor?->name ?? 'User',
        };
    }

    public function actorTypeLabel(): string
    {
        return match ($this->actor_type) {
            'employee' => 'Employee',
            'sales_rep', 'salesrep' => 'Sales Rep',
            'admin' => 'Admin',
            'client' => 'Client',
            default => 'User',
        };
    }
}
