<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.head')
    @viteReactRefresh
    @vite(['resources/js/app.jsx'])
    @inertiaHead
</head>
<body class="bg-guest">
    @inertia
</body>
</html>
