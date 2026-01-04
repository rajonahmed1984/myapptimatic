<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionRule extends Model
{
    protected $fillable = [
        'scope_type',
        'scope_id',
        'source_type',
        'commission_type',
        'value',
        'recurring',
        'first_payment_only',
        'cap_amount',
        'active',
    ];

    protected $casts = [
        'recurring' => 'boolean',
        'first_payment_only' => 'boolean',
        'active' => 'boolean',
        'value' => 'decimal:2',
        'cap_amount' => 'decimal:2',
    ];
}
