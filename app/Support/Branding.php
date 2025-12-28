<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

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
            return asset('storage/' . $path);
        }

        if (Storage::disk('public')->exists($path)) {
            return route('branding.asset', ['path' => $path]);
        }

        return null;
    }
}
