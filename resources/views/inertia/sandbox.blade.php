@php
    $hasViteAssets = file_exists(public_path('hot')) || file_exists(public_path('build/manifest.json'));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="app-base-url" content="{{ request()->getBaseUrl() }}">
    <title inertia>{{ config('app.name', 'Laravel') }}</title>
    @if ($hasViteAssets)
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    @endif
    @inertiaHead
</head>
<body class="antialiased bg-slate-50 text-slate-900">
    @if ($hasViteAssets)
        @inertia
    @else
        <div style="padding:16px;border:1px solid #fca5a5;background:#fef2f2;color:#991b1b;border-radius:8px;">
            Frontend assets are missing. Run <code>npm run dev</code> (development) or <code>npm run build</code> (production) and reload.
        </div>
    @endif
</body>
</html>
