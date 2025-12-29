<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LicenseDomain extends Model
{
    protected $fillable = [
        'license_id',
        'domain',
        'status',
        'verified_at',
        'last_seen_at',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function clientRequests(): HasMany
    {
        return $this->hasMany(ClientRequest::class);
    }
}
