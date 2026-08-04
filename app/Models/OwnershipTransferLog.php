<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OwnershipTransferLog extends Model
{
    protected $fillable = [
        'ownership_transfer_id',
        'action',
        'actor_user_id',
        'ip_address',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function ownershipTransfer(): BelongsTo
    {
        return $this->belongsTo(OwnershipTransfer::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
