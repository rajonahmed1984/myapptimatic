@php
    $hasViteAssets = file_exists(public_path('hot')) || file_exists(public_path('build/manifest.json'));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.head')
    @if ($hasViteAssets)
        @viteReactRefresh
        @vite(['resources/js/app.jsx'])
    @endif
    @inertiaHead
</head>
<body class="bg-guest">
    @if ($hasViteAssets)
        @inertia
    @else
        <div style="padding:16px;border:1px solid #fca5a5;background:#fef2f2;color:#991b1b;border-radius:8px;">
            Frontend assets are missing. Run <code>npm run dev</code> (development) or <code>npm run build</code> (production) and reload.
        </div>
    @endif
</body>
</html>
