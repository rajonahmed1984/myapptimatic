<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.head')
</head>
<body class="bg-guest">
    <main class="min-h-screen px-6 py-12">
        <div class="mx-auto max-w-6xl">
            <div class="mb-8 flex flex-wrap items-center justify-between border-b gap-4">
                <a href="{{ url('/') }}" class="flex items-center gap-3">
                    @if(!empty($portalBranding['logo_url']))
                        <img src="{{ $portalBranding['logo_url'] }}" alt="Company logo" class="h-12 rounded-xl p-1">
                    @else
                        <div class="grid h-12 w-12 place-items-center rounded-xl bg-white/20 text-lg font-semibold text-white">Apptimatic</div>
                    @endif
                </a>
                <div class="text-sm text-slate-600">
                    @auth
                        @php
                            $user = auth()->user();
                            $dashboardUrl = route('client.dashboard');

                            if ($user?->isAdmin()) {
                                $dashboardUrl = route('admin.dashboard');
                            } elseif ($user?->isEmployee()) {
                                $dashboardUrl = route('employee.dashboard');
                            } elseif ($user?->isSales()) {
                                $dashboardUrl = route('rep.dashboard');
                            } elseif ($user?->isSupport()) {
                                $dashboardUrl = route('support.dashboard');
                            }
                        @endphp
                        <a href="{{ $dashboardUrl }}" class="text-teal-600 hover:text-teal-500">Go to dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="text-teal-600 hover:text-teal-500">Sign in</a>
                        <span class="mx-2 text-slate-300">|</span>
                        <a href="{{ route('register') }}" class="text-teal-600 hover:text-teal-500">Register</a>
                    @endauth
                </div>
            </div>

            @if ($errors->any())
                <div data-flash-message data-flash-type="error" class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                    <ul class="space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (session('status'))
                <div data-flash-message data-flash-type="success" class="mb-6 rounded-2xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-700">
                    {{ session('status') }}
                </div>
            @endif

            @yield('content')

            <div class="mt-10 text-center text-xs text-slate-500">
                Copyright © {{ now()->year }} <a href="https://apptimatic.com" class="font-semibold text-teal-600 hover:text-teal-500">Apptimatic</a>. All Rights Reserved.
            </div>
        </div>
    </main>
</body>
</html>
