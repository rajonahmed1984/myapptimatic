<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.head')
</head>
<body class="bg-guest">
    <main class="min-h-screen px-6 py-12">
        <div class="mx-auto flex min-h-[70vh] max-w-6xl flex-col gap-10">
            @php
                $wideForm = request()->routeIs('register');
            @endphp
            <div class="w-full">
                <div class="mb-8 flex flex-wrap items-center justify-between gap-4">
                    <a href="{{ url('/') }}" class="flex items-center gap-3">
                        @if(!empty($portalBranding['logo_url']))
                            <img src="{{ $portalBranding['logo_url'] }}" alt="Logo" class="h-10 w-10 rounded-xl bg-white p-1">
                        @endif
                        <div>
                            <div class="section-label">{{ $portalBranding['company_name'] ?? 'Apptimatic' }}</div>
                            <div class="text-sm text-slate-500">Product ordering</div>
                        </div>
                    </a>
                    <div class="text-sm text-slate-600">
                        <a href="{{ route('login') }}" class="text-teal-600 hover:text-teal-500">Sign in</a>
                        <span class="mx-2 text-slate-300">|</span>
                        <a href="{{ route('register') }}" class="text-teal-600 hover:text-teal-500">Register</a>
                    </div>
                </div>
                <div class="mx-auto w-full max-w-md {{ $wideForm ? 'md:max-w-[50rem]' : '' }}">
                    <div class="card p-8">
                    @if ($errors->any())
                        <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                            <ul class="space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if (session('status'))
                        <div class="mb-6 rounded-2xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-700">
                            {{ session('status') }}
                        </div>
                    @endif

                    @yield('content')
                    </div>
                    <div class="mt-6 text-center text-xs text-slate-500">
                        Copyright © {{ now()->year }} <a href="https://apptimatic.com" class="font-semibold text-teal-600 hover:text-teal-500">Apptimatic</a>. All Rights Reserved.
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
