<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class PaymentMethod extends Model
{
    private static ?bool $commissionPayoutMethodIsEnum = null;

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

    /**
     * Allowed payout codes for commission payouts.
     * Supports legacy enum columns and modern string columns.
     */
    public static function allowedCommissionPayoutCodes(): array
    {
        $codes = static::allowedCodes();

        if (! static::commissionPayoutMethodUsesEnum()) {
            return $codes;
        }

        $legacy = ['bank', 'mobile', 'cash'];

        return array_values(array_intersect($codes, $legacy));
    }

    public static function commissionPayoutDropdownOptions(): Collection
    {
        $allowed = static::allowedCommissionPayoutCodes();

        return static::dropdownOptions()
            ->filter(fn (object $method) => in_array((string) $method->code, $allowed, true))
            ->values();
    }

    private static function commissionPayoutMethodUsesEnum(): bool
    {
        if (static::$commissionPayoutMethodIsEnum !== null) {
            return static::$commissionPayoutMethodIsEnum;
        }

        if (! Schema::hasTable('commission_payouts') || ! Schema::hasColumn('commission_payouts', 'payout_method')) {
            return static::$commissionPayoutMethodIsEnum = false;
        }

        try {
            return static::$commissionPayoutMethodIsEnum = Schema::getColumnType('commission_payouts', 'payout_method') === 'enum';
        } catch (\Throwable) {
            return static::$commissionPayoutMethodIsEnum = false;
        }
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
