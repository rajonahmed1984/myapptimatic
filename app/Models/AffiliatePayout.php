<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AffiliatePayout extends Model
{
    protected $fillable = [
        'affiliate_id',
        'payout_number',
        'amount',
        'status',
        'payment_method',
        'payment_details',
        'notes',
        'processed_at',
        'completed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'processed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(AffiliateCommission::class, 'payout_id');
    }

    public function markAsCompleted(): void
    {
        $this->status = 'completed';
        $this->completed_at = now();
        $this->save();
    }

    public static function generatePayoutNumber(): string
    {
        $year = now()->year;
        $lastPayout = static::where('payout_number', 'like', "PO-{$year}-%")->latest('id')->first();
        
        if ($lastPayout) {
            $lastNumber = (int) substr($lastPayout->payout_number, -6);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return sprintf('PO-%d-%06d', $year, $newNumber);
    }
}
