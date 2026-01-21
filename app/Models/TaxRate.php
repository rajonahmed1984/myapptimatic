<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class TaxRate extends Model
{
    protected $fillable = [
        'name',
        'rate_percent',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected $casts = [
        'rate_percent' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];

    public function scopeActiveForDate(Builder $query, Carbon $date): Builder
    {
        return $query->where('is_active', true)
            ->whereDate('effective_from', '<=', $date->toDateString())
            ->where(function ($inner) use ($date) {
                $inner->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $date->toDateString());
            });
    }
}
