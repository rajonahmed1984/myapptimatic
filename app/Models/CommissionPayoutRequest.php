<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionPayoutRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'sales_representative_id',
        'amount',
        'currency',
        'note',
        'status',
        'commission_payout_id',
        'paid_amount',
        'payout_method',
        'reference',
        'admin_note',
        'processed_by',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(SalesRepresentative::class, 'sales_representative_id');
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(CommissionPayout::class, 'commission_payout_id');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
