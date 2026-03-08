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

        if (Str::startsWith($normalized, 'avatars/')) {
            return self::mediaAvatarPath($normalized);
        }

        return self::storagePath($normalized);
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

    private static function storagePath(string $normalized): string
    {
        return self::basePath() . '/storage/' . ltrim($normalized, '/');
    }

    private static function mediaAvatarPath(string $normalized): string
    {
        $relative = ltrim($normalized, '/');

        if (Str::startsWith($relative, 'avatars/')) {
            $relative = substr($relative, strlen('avatars/'));
        }

        return self::basePath() . '/media/avatars/' . ltrim($relative, '/');
    }

    private static function basePath(): string
    {
        $basePath = '';

        try {
            if (app()->bound('request')) {
                $basePath = (string) request()->getBaseUrl();
            }
        } catch (\Throwable) {
            $basePath = '';
        }

        $basePath = trim(str_replace('\\', '/', $basePath));
        return $basePath !== '' ? '/' . trim($basePath, '/') : '';
    }
}
