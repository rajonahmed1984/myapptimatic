<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Affiliate extends Model
{
    protected $fillable = [
        'customer_id',
        'affiliate_code',
        'status',
        'commission_rate',
        'commission_type',
        'fixed_commission_amount',
        'total_earned',
        'total_paid',
        'balance',
        'total_referrals',
        'total_conversions',
        'payment_details',
        'notes',
        'approved_at',
    ];

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'fixed_commission_amount' => 'decimal:2',
        'total_earned' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'balance' => 'decimal:2',
        'total_referrals' => 'integer',
        'total_conversions' => 'integer',
        'approved_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(AffiliateReferral::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(AffiliateCommission::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(AffiliatePayout::class);
    }

    public function referredCustomers(): HasMany
    {
        return $this->hasMany(Customer::class, 'referred_by_affiliate_id');
    }

    public function getReferralLink(): string
    {
        return url('/?ref=' . $this->affiliate_code);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function updateBalance(): void
    {
        $this->balance = $this->total_earned - $this->total_paid;
        $this->save();
    }

    public static function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 10));
        } while (static::where('affiliate_code', $code)->exists());

        return $code;
    }
}
