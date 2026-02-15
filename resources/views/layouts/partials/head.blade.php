<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'MyApptimatic')</title>
@if(!empty($portalBranding['favicon_url']))
    <link rel="icon" href="{{ $portalBranding['favicon_url'] }}">
@endif
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="{{ asset('css/custom.css') }}"><script src="https://unpkg.com/htmx.org@1.9.11"></script>
@if (request()->routeIs('register'))
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/12.1.13/css/intlTelInput.css">
@endif
@if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
    @vite(['resources/js/app.js'])
@elseif (file_exists(resource_path('js/ajax-nav.js')))
    <script>{!! file_get_contents(resource_path('js/ajax-nav.js')) !!}</script>
@endif
