<?php

namespace App\Support;

class Currency
{
    // Allowed currencies in the system
    public const ALLOWED = ['BDT', 'USD'];
    public const DEFAULT = 'BDT';

    // Currency symbols
    public const SYMBOLS = [
        'BDT' => '৳',
        'USD' => '$',
    ];

    /**
     * Get all allowed currencies.
     */
    public static function allowed(): array
    {
        return self::ALLOWED;
    }

    /**
     * Check if currency is allowed.
     */
    public static function isAllowed(string $currency): bool
    {
        return in_array(strtoupper($currency), self::ALLOWED);
    }

    /**
     * Get currency symbol.
     */
    public static function symbol(string $currency): string
    {
        $currency = strtoupper($currency);
        return self::SYMBOLS[$currency] ?? $currency;
    }

    /**
     * Format amount with currency symbol.
     */
    public static function format(float $amount, string $currency = self::DEFAULT): string
    {
        $currency = strtoupper($currency);
        if (!self::isAllowed($currency)) {
            $currency = self::DEFAULT;
        }
        $symbol = self::symbol($currency);
        return $symbol . number_format($amount, 2);
    }

    /**
     * Get default currency.
     */
    public static function default(): string
    {
        return self::DEFAULT;
    }
}
