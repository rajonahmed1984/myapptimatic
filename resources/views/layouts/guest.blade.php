<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.head')
</head>
<body class="bg-guest">
    <main class="min-h-screen px-6 py-6" style="padding-top: 0;">
        <div class="mx-auto flex min-h-[70vh] max-w-6xl flex-col gap-10">
            @php
                $wideForm = request()->routeIs('register');
            @endphp
            <div class="w-full">
                <div class="mb-8 flex flex-wrap items-center justify-between gap-4 border-b py-2">
                    <a href="{{ url('/') }}" class="flex items-center gap-3">
                        @if(!empty($portalBranding['logo_url']))
                            <img src="{{ $portalBranding['logo_url'] }}" alt="Company logo" class="h-12 rounded-xl p-1">
                        @else
                            <div class="grid h-12 w-12 place-items-center rounded-xl bg-white/20 text-lg font-semibold text-white">Apptimatic</div>
                        @endif
                    </a>
                    <div class="text-sm text-slate-600">
                        <a href="{{ route('login') }}" class="text-teal-600 hover:text-teal-500">Sign in</a>
                        <span class="mx-2 text-slate-300">|</span>
                        <a href="{{ route('register') }}" class="text-teal-600 hover:text-teal-500">Register</a>
                    </div>
                </div>
                <div class="mx-auto w-full max-w-md {{ $wideForm ? 'md:max-w-[50rem]' : '' }}">
                    <div class="card overflow-hidden p-8">
                    @yield('content')
                    </div>
                    <div class="mt-6 text-center text-xs text-slate-500">
                        Copyright &copy; {{ now()->year }} <a href="https://apptimatic.com" class="font-semibold text-teal-600 hover:text-teal-500">Apptimatic</a>. All Rights Reserved.
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
