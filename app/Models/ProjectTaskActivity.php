<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTaskActivity extends Model
{
    protected $fillable = [
        'project_task_id',
        'actor_type',
        'actor_id',
        'type',
        'message',
        'attachment_path',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'project_task_id');
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

    public function attachmentName(): ?string
    {
        if (! $this->attachment_path) {
            return null;
        }

        return pathinfo($this->attachment_path, PATHINFO_BASENAME);
    }

    public function isImageAttachment(): bool
    {
        if (! $this->attachment_path) {
            return false;
        }

        $extension = strtolower((string) pathinfo($this->attachment_path, PATHINFO_EXTENSION));
        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
    }

    public function linkUrl(): ?string
    {
        if ($this->type !== 'link') {
            return null;
        }

        return $this->metadata['url'] ?? null;
    }
}
