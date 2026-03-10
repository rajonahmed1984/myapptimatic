<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Branding
{
    public static function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $path = ltrim($path, '/');
        $publicPath = public_path('storage/' . $path);

        if (is_file($publicPath)) {
            return self::absoluteUrl(PublicStorageUrl::fromPath($path));
        }

        if (Storage::disk('public')->exists($path)) {
            return self::absoluteUrl(route('branding.asset', ['path' => $path], false));
        }

        return null;
    }

    private static function absoluteUrl(?string $path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return rtrim(UrlResolver::portalUrl(), '/') . '/' . ltrim($path, '/');
    }
}
