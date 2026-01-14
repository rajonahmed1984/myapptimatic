<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

class UserActivityDaily extends Model
{
    protected $table = 'user_activity_dailies';

    protected $fillable = [
        'user_type',
        'user_id',
        'guard',
        'date',
        'sessions_count',
        'active_seconds',
        'first_login_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'first_login_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'sessions_count' => 'integer',
            'active_seconds' => 'integer',
        ];
    }

    public function setDateAttribute($value): void
    {
        $date = $value instanceof \DateTimeInterface ? $value : Carbon::parse($value);
        $this->attributes['date'] = $date->toDateString();
    }

    public function user(): MorphTo
    {
        return $this->morphTo();
    }
}
