<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTaskMessage extends Model
{
    protected $fillable = [
        'project_task_id',
        'author_type',
        'author_id',
        'message',
        'attachment_path',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'project_task_id');
    }

    public function userAuthor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function employeeAuthor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'author_id');
    }

    public function salesRepAuthor(): BelongsTo
    {
        return $this->belongsTo(SalesRepresentative::class, 'author_id');
    }

    public function authorName(): string
    {
        return match ($this->author_type) {
            'employee' => $this->employeeAuthor?->name ?? 'Employee',
            'salesrep' => $this->salesRepAuthor?->name ?? 'Sales Rep',
            default => $this->userAuthor?->name ?? 'User',
        };
    }

    public function authorTypeLabel(): string
    {
        return match ($this->author_type) {
            'employee' => 'Employee',
            'salesrep' => 'Sales Rep',
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
}
