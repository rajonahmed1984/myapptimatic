<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class EmployeeActivityDaily extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'date',
        'sessions_count',
        'active_seconds',
        'first_login_at',
        'last_seen_at',
    ];

    protected $casts = [
        'date' => 'date',
        'first_login_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'active_seconds' => 'integer',
    ];

    public function setDateAttribute($value): void
    {
        $date = $value instanceof \DateTimeInterface ? $value : Carbon::parse($value);
        $this->attributes['date'] = $date->toDateString();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
