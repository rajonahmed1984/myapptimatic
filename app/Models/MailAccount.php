<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MailAccount extends Model
{
    protected $fillable = [
        'email',
        'display_name',
        'imap_host',
        'imap_port',
        'imap_encryption',
        'imap_validate_cert',
        'status',
        'last_auth_failed_at',
    ];

    protected $casts = [
        'imap_validate_cert' => 'boolean',
        'last_auth_failed_at' => 'datetime',
    ];

    public function assignments(): HasMany
    {
        return $this->hasMany(MailAccountAssignment::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(MailAccountSession::class);
    }
}
