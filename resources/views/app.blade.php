@php
    $routeName = (string) (request()->route()?->getName() ?? '');
    $guestRoutes = [
        'login',
        'admin.login',
        'employee.login',
        'sales.login',
        'support.login',
        'register',
        'password.request',
        'password.reset',
        'admin.password.request',
        'employee.password.request',
        'employee.password.reset',
        'sales.password.request',
        'sales.password.reset',
        'support.password.request',
        'support.password.reset',
        'project-client.login',
    ];

    $rootView = 'inertia.sandbox';
    $layout = null;

    if (in_array($routeName, $guestRoutes, true)) {
        $rootView = 'inertia.guest';
    } elseif (str_starts_with($routeName, 'products.public.')) {
        $rootView = 'inertia.layout';
        $layout = 'layouts.public';
    } elseif (str_starts_with($routeName, 'admin.')) {
        $rootView = 'inertia.layout';
        $layout = 'layouts.admin';
    } elseif (str_starts_with($routeName, 'employee.')) {
        $rootView = 'inertia.layout';
        $layout = 'layouts.admin';
    } elseif (str_starts_with($routeName, 'client.')) {
        $rootView = 'inertia.layout';
        $layout = 'layouts.client';
    } elseif (str_starts_with($routeName, 'rep.')) {
        $rootView = 'inertia.layout';
        $layout = 'layouts.rep';
    } elseif (str_starts_with($routeName, 'support.')) {
        $rootView = 'inertia.layout';
        $layout = 'layouts.support';
    }
@endphp

@include($rootView, ['layout' => $layout])
