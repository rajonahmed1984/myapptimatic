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
        'status',
        'completed_at',
        'completed_by',
        'created_by',
        'attachment_path',
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
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
}
