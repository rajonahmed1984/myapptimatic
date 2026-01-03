<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncHealthLog extends Model
{
    protected $fillable = [
        'license_id',
        'license_domain_id',
        'status',
        'latency_ms',
        'http_status',
        'retries',
        'source',
        'message',
    ];

    protected $casts = [
        'latency_ms' => 'integer',
        'http_status' => 'integer',
        'retries' => 'integer',
    ];
}
