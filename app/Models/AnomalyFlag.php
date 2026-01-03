<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnomalyFlag extends Model
{
    protected $fillable = [
        'model_type',
        'model_id',
        'flag_type',
        'risk_score',
        'summary',
        'state',
        'detected_at',
        'resolved_by',
        'resolved_at',
        'metadata',
    ];

    protected $casts = [
        'detected_at' => 'datetime',
        'resolved_at' => 'datetime',
        'risk_score' => 'decimal:2',
        'metadata' => 'array',
    ];
}
