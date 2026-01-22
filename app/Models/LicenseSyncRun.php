<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicenseSyncRun extends Model
{
    protected $fillable = [
        'run_at',
        'total_checked',
        'updated_count',
        'expired_count',
        'suspended_count',
        'invalid_count',
        'domain_updates_count',
        'domain_mismatch_count',
        'api_failures_count',
        'failed_count',
        'errors_json',
        'notes',
    ];

    protected $casts = [
        'run_at' => 'datetime',
        'total_checked' => 'integer',
        'updated_count' => 'integer',
        'expired_count' => 'integer',
        'suspended_count' => 'integer',
        'invalid_count' => 'integer',
        'domain_updates_count' => 'integer',
        'domain_mismatch_count' => 'integer',
        'api_failures_count' => 'integer',
        'failed_count' => 'integer',
        'errors_json' => 'array',
    ];
}
