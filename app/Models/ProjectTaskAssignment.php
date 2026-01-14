<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTaskAssignment extends Model
{
    protected $fillable = [
        'project_task_id',
        'assignee_type',
        'assignee_id',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'project_task_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assignee_id');
    }

    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(SalesRepresentative::class, 'assignee_id');
    }

    public function assigneeName(): string
    {
        return match ($this->assignee_type) {
            'employee' => $this->employee?->name ?? 'Employee',
            'sales_rep', 'salesrep' => $this->salesRep?->name ?? 'Sales Rep',
            default => 'User',
        };
    }

    public function assigneeLabel(): string
    {
        return match ($this->assignee_type) {
            'employee' => 'Employee',
            'sales_rep', 'salesrep' => 'Sales Rep',
            default => 'User',
        };
    }
}
