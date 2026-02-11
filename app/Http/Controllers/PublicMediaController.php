<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PublicMediaController extends Controller
{
    public function avatar(Request $request, string $path)
    {
        if (str_contains($path, '..')) {
            abort(404);
        }

        $path = ltrim($path, '/');
        if (str_starts_with($path, 'avatars/')) {
            $path = substr($path, strlen('avatars/'));
        }
        $path = 'avatars/' . $path;

        if (! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        $fullPath = Storage::disk('public')->path($path);
        $mime = Storage::disk('public')->mimeType($path) ?: 'application/octet-stream';

        return response()->file($fullPath, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
        ]);
    }
}
