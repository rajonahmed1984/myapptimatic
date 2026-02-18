@props([
    'href' => '#',
    'routes' => [],
    'activeClass' => 'nav-link nav-link-active',
    'inactiveClass' => 'nav-link',
    'badge' => null,
    'badgeClass' => 'ml-auto rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700'
])

@php
    // Ensure routes is an array
    $routePatterns = is_array($routes) ? $routes : [$routes];
    $isActive = isActive($routePatterns);
@endphp

<a 
    href="{{ $href }}"
    {{ $attributes->merge(['class' => $isActive ? $activeClass : $inactiveClass]) }}
>
    {{ $slot }}
    @if(! is_null($badge))
        <span class="{{ $badgeClass }}">{{ $badge }}</span>
    @endif
</a>
