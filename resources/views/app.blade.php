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

    $rootView = 'react-sandbox';

    if (in_array($routeName, $guestRoutes, true)) {
        $rootView = 'react-guest';
    } elseif (str_starts_with($routeName, 'products.public.')) {
        $rootView = 'react-public';
    } elseif (str_starts_with($routeName, 'admin.')) {
        $rootView = 'react-admin';
    } elseif (str_starts_with($routeName, 'employee.')) {
        $rootView = 'react-employee';
    } elseif (str_starts_with($routeName, 'client.')) {
        $rootView = 'react-client';
    } elseif (str_starts_with($routeName, 'rep.')) {
        $rootView = 'react-rep';
    } elseif (str_starts_with($routeName, 'support.')) {
        $rootView = 'react-support';
    }
@endphp

@include($rootView)
