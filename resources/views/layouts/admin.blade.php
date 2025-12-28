<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.head')
</head>
<body class="bg-dashboard">
    <div class="min-h-screen md:flex">
        <aside class="sidebar hidden w-64 flex-col px-6 py-7 md:flex">
            <div class="flex items-center gap-3">
                @if(!empty($portalBranding['logo_url']))
                    <img src="{{ $portalBranding['logo_url'] }}" alt="Logo" class="h-11 w-11 rounded-2xl bg-white p-1">
                @else
                    <div class="grid h-11 w-11 place-items-center rounded-2xl bg-white/10 text-lg font-semibold text-white">LM</div>
                @endif
                <div>
                    <div class="text-xs uppercase tracking-[0.35em] text-slate-400">Admin</div>
                    <div class="text-lg font-semibold text-white">{{ $portalBranding['company_name'] ?? 'License Portal' }}</div>
                </div>
            </div>

            <nav class="mt-10 space-y-2 text-sm">
                <a class="{{ request()->routeIs('admin.dashboard') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('admin.dashboard') }}">
                    <span class="h-2 w-2 rounded-full bg-current"></span>
                    Dashboard
                </a>
                <a class="{{ request()->routeIs('admin.customers.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('admin.customers.index') }}">
                    <span class="h-2 w-2 rounded-full bg-current"></span>
                    Customers
                </a>
                <a class="{{ request()->routeIs('admin.products.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('admin.products.index') }}">
                    <span class="h-2 w-2 rounded-full bg-current"></span>
                    Products
                </a>
                <a class="{{ request()->routeIs('admin.plans.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('admin.plans.index') }}">
                    <span class="h-2 w-2 rounded-full bg-current"></span>
                    Plans
                </a>
                <a class="{{ request()->routeIs('admin.subscriptions.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('admin.subscriptions.index') }}">
                    <span class="h-2 w-2 rounded-full bg-current"></span>
                    Subscriptions
                </a>
                <a class="{{ request()->routeIs('admin.licenses.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('admin.licenses.index') }}">
                    <span class="h-2 w-2 rounded-full bg-current"></span>
                    Licenses
                </a>
                <a class="{{ request()->routeIs('admin.invoices.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('admin.invoices.index') }}">
                    <span class="h-2 w-2 rounded-full bg-current"></span>
                    Invoices
                </a>
                <a class="{{ request()->routeIs('admin.support-tickets.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('admin.support-tickets.index') }}">
                    <span class="h-2 w-2 rounded-full bg-current"></span>
                    Support
                </a>
                <a class="{{ request()->routeIs('admin.profile.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('admin.profile.edit') }}">
                    <span class="h-2 w-2 rounded-full bg-current"></span>
                    Profile
                </a>
                <a class="{{ request()->routeIs('admin.settings.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('admin.settings.edit') }}">
                    <span class="h-2 w-2 rounded-full bg-current"></span>
                    Settings
                </a>
            </nav>

            <div class="mt-auto rounded-2xl border border-white/10 bg-white/5 p-4 text-xs text-slate-300">
                Billing cycle runs daily via scheduler. Verify task setup for production.
            </div>
        </aside>

        <div class="flex-1">
            <header class="sticky top-0 z-20 border-b border-slate-200/70 bg-white/80 backdrop-blur">
                <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                    <div>
                        <div class="section-label">Admin workspace</div>
                        <div class="text-lg font-semibold text-slate-900">@yield('page-title', 'Overview')</div>
                    </div>
                    <div class="hidden items-center gap-4 md:flex">
                        <div class="text-right text-sm">
                            <div class="font-semibold text-slate-900">{{ auth()->user()->name }}</div>
                            <div class="text-xs text-slate-500">Administrator</div>
                        </div>
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
                            <a href="{{ route('admin.dashboard') }}" class="text-slate-700 hover:text-teal-600">Dashboard</a>
                            <a href="{{ route('admin.customers.index') }}" class="text-slate-700 hover:text-teal-600">Customers</a>
                            <a href="{{ route('admin.products.index') }}" class="text-slate-700 hover:text-teal-600">Products</a>
                            <a href="{{ route('admin.plans.index') }}" class="text-slate-700 hover:text-teal-600">Plans</a>
                            <a href="{{ route('admin.subscriptions.index') }}" class="text-slate-700 hover:text-teal-600">Subscriptions</a>
                            <a href="{{ route('admin.licenses.index') }}" class="text-slate-700 hover:text-teal-600">Licenses</a>
                            <a href="{{ route('admin.invoices.index') }}" class="text-slate-700 hover:text-teal-600">Invoices</a>
                            <a href="{{ route('admin.support-tickets.index') }}" class="text-slate-700 hover:text-teal-600">Support</a>
                            <a href="{{ route('admin.profile.edit') }}" class="text-slate-700 hover:text-teal-600">Profile</a>
                            <a href="{{ route('admin.settings.edit') }}" class="text-slate-700 hover:text-teal-600">Settings</a>
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
