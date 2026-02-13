<?php

if (! function_exists('isActive')) {
    function isActive(string|array $routes): bool
    {
        $routes = is_array($routes) ? $routes : [$routes];

        return request()->routeIs(...$routes);
    }
}

if (! function_exists('isActiveClass')) {
    function isActiveClass(
        string|array $routes,
        string $activeClass = 'nav-link nav-link-active',
        string $inactiveClass = 'nav-link'
    ): string {
        return isActive($routes) ? $activeClass : $inactiveClass;
    }
}

if (! function_exists('activeIf')) {
    function activeIf(
        bool $condition,
        string $activeClass = 'text-teal-300',
        string $inactiveClass = 'hover:text-slate-200'
    ): string {
        return $condition ? $activeClass : $inactiveClass;
    }
}

if (! function_exists('isChildActive')) {
    function isChildActive(string|array $parentRoutes): bool
    {
        $parentRoutes = is_array($parentRoutes) ? $parentRoutes : [$parentRoutes];

        return request()->routeIs(...$parentRoutes);
    }
}
