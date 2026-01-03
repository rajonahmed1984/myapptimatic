<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicenseUsageLog extends Model
{
    protected $fillable = [
        'license_id',
        'subscription_id',
        'customer_id',
        'domain',
        'device_id',
        'ip',
        'user_agent',
        'request_id',
        'action',
        'decision',
        'risk_score',
        'reason',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'risk_score' => 'decimal:2',
    ];
}
