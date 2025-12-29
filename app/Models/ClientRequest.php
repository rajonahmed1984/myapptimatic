<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientRequest extends Model
{
    protected $fillable = [
        'customer_id',
        'user_id',
        'invoice_id',
        'subscription_id',
        'license_domain_id',
        'type',
        'status',
        'message',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function licenseDomain(): BelongsTo
    {
        return $this->belongsTo(LicenseDomain::class);
    }
}
