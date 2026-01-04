<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = [
        'customer_id',
        'order_id',
        'subscription_id',
        'advance_invoice_id',
        'final_invoice_id',
        'name',
        'type',
        'status',
        'due_date',
        'notes',
        'budget_amount',
        'planned_hours',
        'hourly_cost',
        'actual_hours',
    ];

    protected $casts = [
        'due_date' => 'date',
        'budget_amount' => 'decimal:2',
        'planned_hours' => 'decimal:2',
        'hourly_cost' => 'decimal:2',
        'actual_hours' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function advanceInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'advance_invoice_id');
    }

    public function finalInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'final_invoice_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class);
    }
}
