<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CronRun extends Model
{
    protected $fillable = [
        'command',
        'status',
        'started_at',
        'finished_at',
        'duration_ms',
        'exit_code',
        'output_excerpt',
        'error_excerpt',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'duration_ms' => 'integer',
        'exit_code' => 'integer',
    ];
}
