<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = [
        'customer_id',
        'subscription_id',
        'number',
        'status',
        'issue_date',
        'due_date',
        'paid_at',
        'overdue_at',
        'subtotal',
        'late_fee',
        'total',
        'currency',
        'late_fee_applied_at',
        'notes',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'overdue_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'late_fee' => 'decimal:2',
        'total' => 'decimal:2',
        'late_fee_applied_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }
}
