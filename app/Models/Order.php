<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'customer_id',
        'user_id',
        'product_id',
        'plan_id',
        'subscription_id',
        'invoice_id',
        'status',
        'notes',
        'approved_by',
        'approved_at',
        'cancelled_by',
        'cancelled_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public static function nextNumber(): string
    {
        $prefix = now()->format('Ym');
        $maxSequence = (int) self::query()
            ->where('order_number', 'like', $prefix.'%')
            ->selectRaw('MAX(CAST(SUBSTRING(order_number, 7) AS UNSIGNED)) as seq')
            ->value('seq');

        $next = $maxSequence + 1;

        return $prefix.$next;
    }
}
