<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;

class TrustProxies
{
    /**
     * @return array<int, string>|string|null
     */
    public static function proxies(): array|string|null
    {
        $trustedProxies = env('TRUSTED_PROXIES', '*');
        if (! is_string($trustedProxies)) {
            return $trustedProxies;
        }

        if ($trustedProxies === '*') {
            return '*';
        }

        return array_values(array_filter(array_map('trim', explode(',', $trustedProxies))));
    }

    public static function headers(): int
    {
        return Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO
            | Request::HEADER_X_FORWARDED_PREFIX;
    }
}
