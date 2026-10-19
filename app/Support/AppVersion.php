<?php

namespace App\Support;

/**
 * Identifies the deployed frontend build. Every `npm run build` rewrites
 * public/build/manifest.json, so its hash changes on each release — the same
 * signal Inertia uses for its asset version. The browser compares this value
 * against the one its page was rendered with to drop old caches after a deploy.
 */
class AppVersion
{
    private static ?string $resolved = null;

    public static function current(): ?string
    {
        if (self::$resolved !== null) {
            return self::$resolved !== '' ? self::$resolved : null;
        }

        $manifest = public_path('build/manifest.json');
        $hash = is_file($manifest) ? hash_file('xxh128', $manifest) : false;

        self::$resolved = is_string($hash) ? substr($hash, 0, 16) : '';

        return self::$resolved !== '' ? self::$resolved : null;
    }
}
