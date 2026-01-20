<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeWorkSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'work_date',
        'started_at',
        'ended_at',
        'last_activity_at',
        'active_seconds',
    ];

    protected $casts = [
        'work_date' => 'date',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'active_seconds' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
