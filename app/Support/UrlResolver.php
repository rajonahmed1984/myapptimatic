<?php

namespace App\Support;

use App\Models\Setting;

class UrlResolver
{
    public static function normalizeRootUrl(mixed $url): ?string
    {
        if (! is_string($url)) {
            return null;
        }

        $url = trim($url);
        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);
        if (! is_array($parts)) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($scheme === '' || $host === '' || ! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : null;

        return $port !== null
            ? sprintf('%s://%s:%d', $scheme, $host, $port)
            : sprintf('%s://%s', $scheme, $host);
    }

    public static function portalUrl(): string
    {
        $url = null;
        $settingUrl = self::normalizeRootUrl(Setting::getValue('app_url'));
        $requestRoot = app()->bound('request')
            ? self::normalizeRootUrl(request()->root())
            : null;

        if (app()->environment('local')) {
            if ($requestRoot !== null) {
                $url = $requestRoot;
            }

            if ($url === null && $settingUrl !== null) {
                $url = $settingUrl;
            }
        } else {
            if ($settingUrl !== null) {
                $url = $settingUrl;
            } elseif ($requestRoot !== null) {
                $url = $requestRoot;
            }
        }

        if ($url === null) {
            $url = self::normalizeRootUrl(config('app.url'));
        }

        if ($url === null) {
            $url = 'http://localhost';
        }

        return $url;
    }
}
