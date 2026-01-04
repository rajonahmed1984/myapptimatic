<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesRepresentative extends Model
{
    protected $fillable = [
        'user_id',
        'employee_id',
        'name',
        'email',
        'phone',
        'status',
        'payout_method_default',
        'payout_details_encrypted',
        'metadata',
    ];

    protected $casts = [
        'payout_details_encrypted' => 'array',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
