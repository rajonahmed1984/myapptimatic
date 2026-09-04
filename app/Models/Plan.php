<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'product_id',
        'name',
        'slug',
        'interval',
        'price',
        'currency',
        'pricing_model',
        'is_active',
        'seat_limit',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'seat_limit' => 'integer',
    ];

    public function isPerFlat(): bool
    {
        return $this->pricing_model === 'per_flat'
            || ($this->relationLoaded('product') && $this->product?->slug === config('mybuilding.product_slug'));
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
