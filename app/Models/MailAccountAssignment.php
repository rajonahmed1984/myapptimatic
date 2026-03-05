<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailAccountAssignment extends Model
{
    protected $fillable = [
        'mail_account_id',
        'assignee_type',
        'assignee_id',
        'can_read',
        'can_manage',
    ];

    protected $casts = [
        'can_read' => 'boolean',
        'can_manage' => 'boolean',
    ];

    public function mailAccount(): BelongsTo
    {
        return $this->belongsTo(MailAccount::class);
    }
}
