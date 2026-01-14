<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'session_id',
        'login_at',
        'logout_at',
        'last_seen_at',
        'active_seconds',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'login_at' => 'datetime',
        'logout_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'active_seconds' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
