<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    protected $fillable = [
        'customer_id',
        'plan_id',
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

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function licenses(): HasMany
    {
        return $this->hasMany(License::class);
    }

    public function clientRequests(): HasMany
    {
        return $this->hasMany(ClientRequest::class);
    }
}
