<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateCommission extends Model
{
    protected $fillable = [
        'affiliate_id',
        'referral_id',
        'invoice_id',
        'order_id',
        'description',
        'amount',
        'commission_rate',
        'status',
        'approved_at',
        'paid_at',
        'payout_id',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(AffiliateReferral::class, 'referral_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(AffiliatePayout::class, 'payout_id');
    }

    public function approve(): void
    {
        $this->status = 'approved';
        $this->approved_at = now();
        $this->save();

        $this->affiliate->increment('total_earned', $this->amount);
        $this->affiliate->updateBalance();
    }

    public function markAsPaid(?int $payoutId = null): void
    {
        $this->status = 'paid';
        $this->paid_at = now();
        
        if ($payoutId) {
            $this->payout_id = $payoutId;
        }
        
        $this->save();

        $this->affiliate->increment('total_paid', $this->amount);
        $this->affiliate->updateBalance();
    }
}
