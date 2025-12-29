<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentGateway extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'driver',
        'is_active',
        'sort_order',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    public function accountingEntries(): HasMany
    {
        return $this->hasMany(AccountingEntry::class, 'payment_gateway_id');
    }

    public function paymentAttempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class, 'payment_gateway_id');
    }

    public function paymentProofs(): HasMany
    {
        return $this->hasMany(PaymentProof::class, 'payment_gateway_id');
    }
}
