<?php

if (!function_exists('isActive')) {
    /**
     * Check if the current route matches the given route pattern.
     * Supports wildcard patterns like 'admin.projects.*'
     * 
     * @param string|array $routes Route name(s) or pattern(s)
     * @return bool
     */
    function isActive($routes): bool
    {
        $routes = is_array($routes) ? $routes : [$routes];
        return request()->routeIs(...$routes);
    }
}

if (!function_exists('isActiveClass')) {
    /**
     * Return active CSS class if the current route matches.
     * Supports wildcard patterns like 'admin.projects.*'
     * 
     * @param string|array $routes Route name(s) or pattern(s)
     * @param string $activeClass CSS class to apply when active
     * @param string $inactiveClass CSS class to apply when inactive
     * @return string
     */
    function isActiveClass($routes, $activeClass = 'nav-link nav-link-active', $inactiveClass = 'nav-link'): string
    {
        return isActive($routes) ? $activeClass : $inactiveClass;
    }
}

if (!function_exists('activeIf')) {
    /**
     * Return the active class if condition is true.
     * 
     * @param bool $condition
     * @param string $activeClass CSS class to apply when active
     * @param string $inactiveClass CSS class to apply when inactive
     * @return string
     */
    function activeIf($condition, $activeClass = 'text-teal-300', $inactiveClass = 'hover:text-slate-200'): string
    {
        return $condition ? $activeClass : $inactiveClass;
    }
}

if (!function_exists('isChildActive')) {
    /**
     * Check if any child route is active (used for nested menu expansion).
     * Useful for parent items that should expand when a child route is active.
     * 
     * @param string|array $parentRoutes Parent route pattern(s)
     * @return bool
     */
    function isChildActive($parentRoutes): bool
    {
        $parentRoutes = is_array($parentRoutes) ? $parentRoutes : [$parentRoutes];
        return request()->routeIs(...$parentRoutes);
    }
}
