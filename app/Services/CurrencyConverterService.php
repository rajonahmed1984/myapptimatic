<?php

namespace App\Services;

use App\Services\Currency\CurrencyService;
use App\Support\Currency;
use InvalidArgumentException;

class CurrencyConverterService
{
    /**
     * Convert amount from one currency to another.
     *
     * @throws InvalidArgumentException
     */
    public function convert(float $amount, string $fromCurrency, string $toCurrency): float
    {
        return app(CurrencyService::class)->convert($amount, $fromCurrency, $toCurrency);
    }

    /**
     * Get exchange rate between two currencies.
     *
     * @throws InvalidArgumentException
     */
    public function getRate(string $fromCurrency, string $toCurrency): float
    {
        return app(CurrencyService::class)->getRate($fromCurrency, $toCurrency);
    }

    /**
     * Update exchange rates (deprecated placeholder).
     */
    public function updateRates(float $bdtToUsd, float $usdToBdt): void
    {
        // Rates are fetched from the configured provider and cached.
    }

    /**
     * Validate that currencies are allowed.
     */
    public function validateCurrency(string $currency): bool
    {
        return Currency::isAllowed($currency);
    }

    /**
     * Get all allowed currencies.
     */
    public function getAllowedCurrencies(): array
    {
        return Currency::allowed();
    }
}
