@props([
    'href' => '#',
    'routes' => [],
    'label' => '',
    'icon' => true,
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

<div>
    <a 
        href="{{ $href }}"
        {{ $attributes->merge(['class' => $isActive ? $activeClass : $inactiveClass]) }}
    >
        @if($icon)
            <span class="h-2 w-2 rounded-full bg-current"></span>
        @endif
        {{ $label }}
        @if($badge)
            <span class="{{ $badgeClass }}">{{ $badge }}</span>
        @endif
    </a>
    
    @if($isActive && $slot->isNotEmpty())
        <div class="ml-6 space-y-1 text-xs text-slate-400">
            {{ $slot }}
        </div>
    @endif
</div>
