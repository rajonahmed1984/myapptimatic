<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class BrandingAssetController extends Controller
{
    public function show(string $path): Response
    {
        $path = ltrim($path, '/');

        if (! str_starts_with($path, 'branding/') || str_contains($path, '..')) {
            abort(404);
        }

        if (! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return response()->file(Storage::disk('public')->path($path));
    }
}
