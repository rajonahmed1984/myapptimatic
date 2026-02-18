<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectChatEmailDigestState extends Model
{
    protected $fillable = [
        'project_id',
        'recipient_email',
        'last_notified_message_id',
        'notified_at',
    ];

    protected $casts = [
        'last_notified_message_id' => 'integer',
        'notified_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
