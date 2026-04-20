<?php

namespace App\Models;

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

    public static function catalog(): Collection
    {
        return collect(static::defaultCatalogRows())
            ->map(function (array $row, int $index) {
                $method = new static();
                $method->forceFill([
                    'id' => (int) ($row['id'] ?? ($index + 1)),
                    'name' => (string) ($row['name'] ?? ''),
                    'code' => (string) ($row['code'] ?? ''),
                    'account_details' => (string) ($row['account_details'] ?? ''),
                    'is_active' => (bool) ($row['is_active'] ?? true),
                    'sort_order' => (int) ($row['sort_order'] ?? (($index + 1) * 10)),
                ]);

                return $method;
            })
            ->values();
    }

    private static function activeCatalog(): Collection
    {
        return static::catalog()
            ->filter(fn (PaymentMethod $method) => (bool) $method->is_active)
            ->values();
    }

    public static function allowedCodes(): array
    {
        return static::activeCatalog()
            ->pluck('code')
            ->map(fn ($code) => (string) $code)
            ->values()
            ->all();
    }

    public static function dropdownOptions(): Collection
    {
        return static::activeCatalog()
            ->map(fn (PaymentMethod $method) => (object) [
                'code' => (string) $method->code,
                'name' => (string) $method->name,
            ])
            ->values();
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
        return collect(static::defaultCatalogRows())
            ->filter(fn (array $row) => (bool) ($row['is_active'] ?? true))
            ->map(fn (array $row) => (object) [
                'code' => (string) ($row['code'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
            ])
            ->values();
    }

    private static function defaultCatalogRows(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'Bank',
                'code' => 'bank',
                'account_details' => '',
                'is_active' => true,
                'sort_order' => 10,
            ],
            [
                'id' => 2,
                'name' => 'Mobile',
                'code' => 'mobile',
                'account_details' => '',
                'is_active' => true,
                'sort_order' => 20,
            ],
            [
                'id' => 3,
                'name' => 'Cash',
                'code' => 'cash',
                'account_details' => '',
                'is_active' => true,
                'sort_order' => 30,
            ],
            [
                'id' => 4,
                'name' => 'Other',
                'code' => 'other',
                'account_details' => '',
                'is_active' => true,
                'sort_order' => 40,
            ],
        ];
    }
}
