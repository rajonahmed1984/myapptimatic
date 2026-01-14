<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.head')
</head>
<body class="bg-dashboard">
    <div class="min-h-screen md:flex">
        <aside class="sidebar hidden w-64 flex-col px-6 py-7 md:flex">
            <div class="flex items-center gap-3">
                @php
                    $sidebarImage = $portalBranding['favicon_url'] ?? ($portalBranding['logo_url'] ?? null);
                @endphp
                @if(!empty($sidebarImage))
                    <img src="{{ $sidebarImage }}" alt="Brand mark" class="h-11 w-11 rounded-2xl bg-white p-1">
                @else
                    <div class="grid h-11 w-11 place-items-center rounded-2xl bg-white/10 text-lg font-semibold text-white">SR</div>
                @endif
                <div>
                    <div class="text-xs uppercase tracking-[0.35em] text-slate-400">Sales Rep</div>
                    <div class="text-lg font-semibold text-white">{{ $portalBranding['company_name'] ?? 'License Portal' }}</div>
                </div>
            </div>

            <nav class="mt-10 space-y-4 text-sm">
                <div>
                    <x-nav-link 
                        :href="route('rep.dashboard')"
                        routes="rep.dashboard"
                    >
                        <span class="h-2 w-2 rounded-full bg-current"></span>
                        Sales Dashboard
                    </x-nav-link>
                </div>
                <div class="space-y-2">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Work & Delivery</div>
                    <x-nav-link 
                        :href="route('rep.projects.index')"
                        routes="rep.projects.*"
                    >
                        <span class="h-2 w-2 rounded-full bg-current"></span>
                        Projects
                    </x-nav-link>
                </div>
                <div class="space-y-2">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Earnings</div>
                    <x-nav-link 
                        :href="route('rep.earnings.index')"
                        routes="rep.earnings.*"
                    >
                        <span class="h-2 w-2 rounded-full bg-current"></span>
                        Commissions
                    </x-nav-link>
                    <x-nav-link 
                        :href="route('rep.payouts.index')"
                        routes="rep.payouts.*"
                    >
                        <span class="h-2 w-2 rounded-full bg-current"></span>
                        Payouts
                    </x-nav-link>
                </div>
            </nav>

            <div class="mt-auto rounded-2xl border border-white/10 bg-white/5 p-4 text-xs text-slate-300">
                Sales view only. Contact admin if access is revoked.
            </div>
        </aside>

        <div class="flex-1">
            <header class="sticky top-0 z-20 border-b border-slate-200/70 bg-white/80 backdrop-blur">
                <div class="mx-auto flex max-w-6xl items-center justify-between gap-6 px-6 py-4">
                    <div>
                        <div class="section-label">Sales rep workspace</div>
                        <div class="text-lg font-semibold text-slate-900">@yield('page-title', 'Overview')</div>
                    </div>
                    <div class="hidden items-center gap-4 md:flex">
                        <div class="text-right text-sm">
                            <div class="font-semibold text-slate-900">{{ auth()->user()->name }}</div>
                            <div class="text-xs text-slate-500">Sales Representative</div>
                        </div>
                        @if(session()->has('impersonator_id'))
                            <form method="POST" action="{{ route('impersonate.stop') }}">
                                @csrf
                                <button type="submit" class="rounded-full border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700 transition hover:border-amber-300" title="Return to Admin">
                                    Return to Admin
                                </button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 transition hover:border-teal-300 hover:text-teal-600">
                                Sign out
                            </button>
                        </form>
                    </div>
                </div>

                @if(session()->has('impersonator_id'))
                    <div class="mx-auto max-w-6xl px-6 pb-3">
                        <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
                            <div class="text-[11px] uppercase tracking-[0.28em] text-amber-600">Impersonation</div>
                            <div class="text-sm text-amber-800">
                                You are logged in as <span class="font-semibold">{{ auth()->user()->name ?? 'Sales Rep' }}</span>.
                            </div>
                            <form method="POST" action="{{ route('impersonate.stop') }}" class="ml-auto">
                                @csrf
                                <button type="submit" class="rounded-full border border-amber-300 bg-white px-3 py-1 text-xs font-semibold text-amber-700 transition hover:border-amber-400 hover:bg-amber-100">
                                    Return to Admin
                                </button>
                            </form>
                        </div>
                    </div>
                @endif

                <div class="mx-auto flex max-w-6xl px-6 pb-4 md:hidden">
                    <details class="w-full rounded-2xl border border-slate-200 bg-white/90 p-4">
                        <summary class="cursor-pointer text-sm font-semibold text-slate-700">Menu</summary>
                        <nav class="mt-3 grid gap-2 text-sm">
                            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Main</div>
                            <a href="{{ route('rep.dashboard') }}" class="text-slate-700 hover:text-teal-600">Sales Dashboard</a>

                            <div class="text-xs uppercase tracking-[0.2em] text-slate-400 pt-2">Earnings</div>
                            <a href="{{ route('rep.earnings.index') }}" class="text-slate-700 hover:text-teal-600">Commissions</a>
                            <a href="{{ route('rep.payouts.index') }}" class="text-slate-700 hover:text-teal-600">Payouts</a>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="text-left text-slate-700 hover:text-teal-600">Sign out</button>
                            </form>
                        </nav>
                    </details>
                </div>
            </header>

            <main class="mx-auto max-w-6xl px-6 py-10 fade-in">
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
            </main>
        </div>
    </div>
</body>
</html>
