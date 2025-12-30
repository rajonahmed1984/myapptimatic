<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AffiliateReferral extends Model
{
    protected $fillable = [
        'affiliate_id',
        'customer_id',
        'ip_address',
        'user_agent',
        'referrer_url',
        'landing_page',
        'status',
        'converted_at',
    ];

    protected $casts = [
        'converted_at' => 'datetime',
    ];

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(AffiliateCommission::class, 'referral_id');
    }

    public function markAsConverted(): void
    {
        $this->status = 'converted';
        $this->converted_at = now();
        $this->save();

        $this->affiliate->increment('total_conversions');
    }
}
