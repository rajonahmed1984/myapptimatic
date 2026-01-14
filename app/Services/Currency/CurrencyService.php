<?php

namespace App\Services\Currency;

use App\Support\Currency;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class CurrencyService
{
    /**
     * Get allowed currency codes.
     */
    public function allowedCurrencies(): array
    {
        return Currency::allowed();
    }

    /**
     * Normalize currency codes to BDT or USD, falling back to the default.
     */
    public function normalize(?string $code): string
    {
        $currency = strtoupper(trim((string) $code));
        if ($currency === '') {
            return Currency::DEFAULT;
        }

        return Currency::isAllowed($currency) ? $currency : Currency::DEFAULT;
    }

    /**
     * Convert between BDT and USD using cached rates.
     */
    public function convert(float $amount, string $fromCurrency, string $toCurrency): float
    {
        $rate = $this->getRate($fromCurrency, $toCurrency);
        return round($amount * $rate, 2);
    }

    /**
     * Get exchange rate between two currencies.
     *
     * @throws InvalidArgumentException
     */
    public function getRate(string $fromCurrency, string $toCurrency): float
    {
        $from = strtoupper($fromCurrency);
        $to = strtoupper($toCurrency);

        if (! Currency::isAllowed($from) || ! Currency::isAllowed($to)) {
            throw new InvalidArgumentException('Only BDT and USD are supported currencies.');
        }

        if ($from === $to) {
            return 1.0;
        }

        $usdBdt = $this->getUsdBdtRate();

        if ($from === 'USD' && $to === 'BDT') {
            return $usdBdt;
        }

        if ($from === 'BDT' && $to === 'USD') {
            return 1 / $usdBdt;
        }

        throw new InvalidArgumentException("Unable to get rate from {$from} to {$to}");
    }

    private function getUsdBdtRate(): float
    {
        $cacheKey = (string) config('currency.cache_key', 'currency.usd_bdt_rate');
        $ttlMinutes = (int) config('currency.cache_ttl_minutes', 60);

        return Cache::remember($cacheKey, now()->addMinutes($ttlMinutes), function () {
            $fallback = (float) config('currency.fallback.usd_bdt', 105.50);
            $url = (string) config('currency.provider_url', 'https://open.er-api.com/v6/latest/USD');
            $timeout = (int) config('currency.timeout_seconds', 5);
            $retry = (int) config('currency.retry', 1);
            $retryDelay = (int) config('currency.retry_delay_ms', 200);

            try {
                $response = Http::timeout($timeout)
                    ->retry($retry, $retryDelay)
                    ->acceptJson()
                    ->get($url);

                if ($response->successful()) {
                    $rate = data_get($response->json(), 'rates.BDT');
                    if (is_numeric($rate) && (float) $rate > 0) {
                        return (float) $rate;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Currency rate fetch failed.', [
                    'error' => $e->getMessage(),
                ]);
            }

            return $fallback;
        });
    }
}
