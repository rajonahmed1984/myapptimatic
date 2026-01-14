<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTaskMessageRead extends Model
{
    protected $fillable = [
        'project_task_id',
        'reader_type',
        'reader_id',
        'last_read_message_id',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'last_read_message_id' => 'integer',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'project_task_id');
    }
}
