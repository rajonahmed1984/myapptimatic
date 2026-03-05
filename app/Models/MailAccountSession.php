<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailAccountSession extends Model
{
    protected $fillable = [
        'assignee_type',
        'assignee_id',
        'mail_account_id',
        'session_token_hash',
        'auth_secret',
        'remember',
        'last_validated_at',
        'expires_at',
        'invalidated_at',
    ];

    protected $casts = [
        'remember' => 'boolean',
        'last_validated_at' => 'datetime',
        'expires_at' => 'datetime',
        'invalidated_at' => 'datetime',
    ];

    public function mailAccount(): BelongsTo
    {
        return $this->belongsTo(MailAccount::class);
    }
}
