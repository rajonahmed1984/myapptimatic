<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class PaymentMethod extends Model
{
    protected $fillable = [
        'name',
        'code',
        'account_details',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public static function allowedCodes(): array
    {
        return static::dropdownOptions()->pluck('code')->values()->all();
    }

    public static function dropdownOptions(): Collection
    {
        if (! Schema::hasTable('payment_methods')) {
            return static::defaultOptions();
        }

        $options = static::query()
            ->active()
            ->ordered()
            ->get(['code', 'name']);

        if ($options->isEmpty()) {
            return static::defaultOptions();
        }

        return $options->map(fn (PaymentMethod $method) => [
            'code' => (string) $method->code,
            'name' => (string) $method->name,
        ])->map(fn (array $row) => (object) $row);
    }

    private static function defaultOptions(): Collection
    {
        return collect([
            (object) ['code' => 'bank', 'name' => 'Bank'],
            (object) ['code' => 'mobile', 'name' => 'Mobile'],
            (object) ['code' => 'cash', 'name' => 'Cash'],
            (object) ['code' => 'other', 'name' => 'Other'],
        ]);
    }
}
