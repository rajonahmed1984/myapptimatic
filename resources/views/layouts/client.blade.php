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
                    <div class="grid h-11 w-11 place-items-center rounded-2xl bg-white/10 text-lg font-semibold text-white">LM</div>
                @endif
                <div>
                    <div class="text-xs uppercase tracking-[0.35em] text-slate-400">Client</div>
                    <div class="text-lg font-semibold text-white">{{ $portalBranding['company_name'] ?? 'License Portal' }}</div>
                </div>
            </div>

            <nav class="mt-10 space-y-2 text-sm">
                <a class="{{ request()->routeIs('client.dashboard') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('client.dashboard') }}">
                    <span class="h-2 w-2 rounded-full bg-current"></span>
                    Overview
                </a>
                <a class="{{ request()->routeIs('client.orders.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('client.orders.index') }}">
                    <span class="h-2 w-2 rounded-full bg-current"></span>
                    Order
                </a>
                <a class="{{ request()->routeIs('client.services.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('client.services.index') }}">
                    <span class="h-2 w-2 rounded-full bg-current"></span>
                    Services
                </a>
                <a class="{{ request()->routeIs('client.domains.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('client.domains.index') }}">
                    <span class="h-2 w-2 rounded-full bg-current"></span>
                    Domains
                </a>
                <a class="{{ request()->routeIs('client.invoices.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('client.invoices.index') }}">
                    <span class="h-2 w-2 rounded-full bg-current"></span>
                    Invoices
                </a>
                <a class="{{ request()->routeIs('client.licenses.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('client.licenses.index') }}">
                    <span class="h-2 w-2 rounded-full bg-current"></span>
                    Licenses
                </a>
                <a class="{{ request()->routeIs('client.support-tickets.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('client.support-tickets.index') }}">
                    <span class="h-2 w-2 rounded-full bg-current"></span>
                    Support
                </a>
                <a class="{{ request()->routeIs('client.affiliates.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('client.affiliates.index') }}">
                    <span class="h-2 w-2 rounded-full bg-current"></span>
                    Affiliates
                </a>
                <a class="{{ request()->routeIs('client.profile.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('client.profile.edit') }}">
                    <span class="h-2 w-2 rounded-full bg-current"></span>
                    Profile
                </a>
            </nav>

            <div class="mt-auto rounded-2xl border border-white/10 bg-white/5 p-4 text-xs text-slate-300">
                Need help? Contact support to keep access active.
            </div>
        </aside>

        <div class="flex-1">
            <header class="sticky top-0 z-20 border-b border-slate-200/70 bg-white/80 backdrop-blur">
                <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                    <div>
                        <div class="section-label">Client workspace</div>
                        <div class="text-lg font-semibold text-slate-900">@yield('page-title', 'Overview')</div>
                    </div>
                    <div class="hidden items-center gap-4 md:flex">
                        <div class="text-right text-sm">
                            <div class="font-semibold text-slate-900">{{ auth()->user()->name }}</div>
                            <div class="text-xs text-slate-500">Client account</div>
                        </div>
                        @if(session()->has('impersonator_id'))
                            <form method="POST" action="{{ route('impersonate.stop') }}">
                                @csrf
                                <button
                                    type="submit"
                                    class="rounded-full border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700 transition hover:border-amber-300"
                                    title="You are currently masquerading as a client. Click to return to Admin Area"
                                >
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                                        <path d="M10 17l5-5-5-5"></path>
                                        <path d="M4 12h11"></path>
                                        <path d="M20 4v16"></path>
                                    </svg>
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

                <div class="mx-auto flex max-w-6xl px-6 pb-4 md:hidden">
                    <details class="w-full rounded-2xl border border-slate-200 bg-white/90 p-4">
                        <summary class="cursor-pointer text-sm font-semibold text-slate-700">Menu</summary>
                        <nav class="mt-3 grid gap-2 text-sm">
                            <a href="{{ route('client.dashboard') }}" class="text-slate-700 hover:text-teal-600">Overview</a>
                            <a href="{{ route('client.orders.index') }}" class="text-slate-700 hover:text-teal-600">Order</a>
                            <a href="{{ route('client.services.index') }}" class="text-slate-700 hover:text-teal-600">Services</a>
                            <a href="{{ route('client.domains.index') }}" class="text-slate-700 hover:text-teal-600">Domains</a>
                            <a href="{{ route('client.invoices.index') }}" class="text-slate-700 hover:text-teal-600">Invoices</a>
                            <a href="{{ route('client.licenses.index') }}" class="text-slate-700 hover:text-teal-600">Licenses</a>
                            <a href="{{ route('client.support-tickets.index') }}" class="text-slate-700 hover:text-teal-600">Support</a>
                            <a href="{{ route('client.profile.edit') }}" class="text-slate-700 hover:text-teal-600">Profile</a>
                            @if(session()->has('impersonator_id'))
                                <form method="POST" action="{{ route('impersonate.stop') }}">
                                    @csrf
                                    <button type="submit" class="text-left text-amber-700 hover:text-amber-600">Return to Admin</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="text-left text-slate-700 hover:text-teal-600">Sign out</button>
                            </form>
                        </nav>
                    </details>
                </div>
            </header>

            <main class="mx-auto max-w-6xl px-6 py-10 fade-in">
                @if(!empty($clientInvoiceNotice) && $clientInvoiceNotice['has_due'])
                    @include('partials.overdue-banner', ['notice' => $clientInvoiceNotice])
                @endif

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
