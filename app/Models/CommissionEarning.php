<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionEarning extends Model
{
    protected $fillable = [
        'sales_representative_id',
        'source_type',
        'source_id',
        'invoice_id',
        'subscription_id',
        'project_id',
        'customer_id',
        'currency',
        'paid_amount',
        'commission_amount',
        'status',
        'earned_at',
        'payable_at',
        'paid_at',
        'reversed_at',
        'commission_payout_id',
        'idempotency_key',
        'metadata',
    ];

    protected $casts = [
        'paid_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'earned_at' => 'datetime',
        'payable_at' => 'datetime',
        'paid_at' => 'datetime',
        'reversed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(SalesRepresentative::class, 'sales_representative_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(CommissionPayout::class, 'commission_payout_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
