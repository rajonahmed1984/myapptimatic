<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\SalesRepresentative;

class Subscription extends Model
{
    protected $fillable = [
        'customer_id',
        'plan_id',
        'sales_rep_id',
        'subscription_amount',
        'sales_rep_commission_amount',
        'status',
        'start_date',
        'current_period_start',
        'current_period_end',
        'next_invoice_at',
        'auto_renew',
        'cancel_at_period_end',
        'cancelled_at',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'current_period_start' => 'date',
        'current_period_end' => 'date',
        'next_invoice_at' => 'date',
        'subscription_amount' => 'decimal:2',
        'sales_rep_commission_amount' => 'decimal:2',
        'cancelled_at' => 'datetime',
        'auto_renew' => 'boolean',
        'cancel_at_period_end' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(SalesRepresentative::class, 'sales_rep_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function licenses(): HasMany
    {
        return $this->hasMany(License::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function latestOrder(): HasOne
    {
        return $this->hasOne(Order::class)->latestOfMany();
    }
}
