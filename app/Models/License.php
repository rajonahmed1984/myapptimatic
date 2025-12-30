<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class License extends Model
{
    protected $fillable = [
        'subscription_id',
        'product_id',
        'license_key',
        'status',
        'starts_at',
        'expires_at',
        'max_domains',
        'last_check_at',
        'last_check_ip',
        'notes',
        'expiry_first_notice_sent_at',
        'expiry_second_notice_sent_at',
        'expiry_expired_notice_sent_at',
    ];

    protected $casts = [
        'starts_at' => 'date',
        'expires_at' => 'date',
        'max_domains' => 'integer',
        'last_check_at' => 'datetime',
        'expiry_first_notice_sent_at' => 'datetime',
        'expiry_second_notice_sent_at' => 'datetime',
        'expiry_expired_notice_sent_at' => 'datetime',
    ];

    public static function generateKey(): string
    {
        return strtoupper(Str::random(32));
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(LicenseDomain::class);
    }
}
