<?php

namespace App\Support;

use Illuminate\Support\Str;

class PublicStorageUrl
{
    public static function fromPath(?string $path): ?string
    {
        $normalized = self::normalizePath($path);
        if ($normalized === '') {
            return null;
        }

        return asset('storage/'.$normalized);
    }

    public static function normalizePath(?string $path): string
    {
        if (! is_string($path)) {
            return '';
        }

        $value = trim($path);
        if ($value === '') {
            return '';
        }

        if (Str::startsWith($value, ['data:', 'blob:'])) {
            return '';
        }

        if (Str::startsWith($value, ['http://', 'https://'])) {
            $value = (string) (parse_url($value, PHP_URL_PATH) ?? '');
        }

        $value = str_replace('\\', '/', $value);
        $value = ltrim($value, '/');

        if (Str::startsWith($value, 'storage/')) {
            $value = substr($value, strlen('storage/'));
        }

        return ltrim($value, '/');
    }
}
