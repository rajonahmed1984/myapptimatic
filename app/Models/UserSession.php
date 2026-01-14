<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class UserSession extends Model
{
    protected $fillable = [
        'user_type',
        'user_id',
        'guard',
        'session_id',
        'login_at',
        'logout_at',
        'last_seen_at',
        'active_seconds',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'login_at' => 'datetime',
            'logout_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'active_seconds' => 'integer',
        ];
    }

    public function user(): MorphTo
    {
        return $this->morphTo();
    }
}
