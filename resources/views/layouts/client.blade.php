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
                    <div class="grid h-11 w-11 place-items-center rounded-2xl bg-white/10 text-lg font-semibold text-white">Apptimatic</div>
                @endif
                <div>
                    <div class="text-xs uppercase tracking-[0.35em] text-slate-400">Client</div>
                    <div class="text-lg font-semibold text-white">{{ $portalBranding['company_name'] ?? 'License Portal' }}</div>
                </div>
            </div>

            <nav class="mt-10 space-y-4 text-sm">
                <div>
                    <a class="{{ request()->routeIs('client.dashboard') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('client.dashboard') }}">
                        <span class="h-2 w-2 rounded-full bg-current"></span>
                        Overview
                    </a>
                </div>

                <div class="space-y-2">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Projects & Services</div>
                    <a class="{{ request()->routeIs('client.projects.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('client.projects.index') }}">
                        <span class="h-2 w-2 rounded-full bg-current"></span>
                        Projects
                    </a>
                    {{-- Client-facing: services list mixes projects and recurring maintenance/subscriptions --}}
                    <a class="{{ request()->routeIs('client.services.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('client.services.index') }}">
                        <span class="h-2 w-2 rounded-full bg-current"></span>
                        Services
                    </a>
                    <a class="{{ request()->routeIs('client.domains.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('client.domains.index') }}">
                        <span class="h-2 w-2 rounded-full bg-current"></span>
                        Domains
                    </a>
                    <a class="{{ request()->routeIs('client.licenses.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('client.licenses.index') }}">
                        <span class="h-2 w-2 rounded-full bg-current"></span>
                        Licenses
                    </a>
                </div>

                <div class="space-y-2">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Orders & Requests</div>
                    <a class="{{ request()->routeIs('client.orders.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('client.orders.index') }}">
                        <span class="h-2 w-2 rounded-full bg-current"></span>
                        My Orders
                    </a>
                </div>

                <div class="space-y-2">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Billing & Payments</div>
                    <a class="{{ request()->routeIs('client.invoices.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('client.invoices.index') }}">
                        <span class="h-2 w-2 rounded-full bg-current"></span>
                        Invoices
                    </a>
                </div>

                <div class="space-y-2">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Support & Growth</div>
                    <a class="{{ request()->routeIs('client.support-tickets.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('client.support-tickets.index') }}">
                        <span class="h-2 w-2 rounded-full bg-current"></span>
                        <span>Support</span>
                        @if(($clientHeaderStats['pending_admin_replies'] ?? 0) > 0)
                            <span class="ml-auto rounded-full bg-teal-100 px-2 py-0.5 text-xs font-semibold text-teal-700">{{ $clientHeaderStats['pending_admin_replies'] }}</span>
                        @endif
                    </a>
                    <a class="{{ request()->routeIs('client.affiliates.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('client.affiliates.index') }}">
                        <span class="h-2 w-2 rounded-full bg-current"></span>
                        Affiliates
                    </a>
                </div>

                <div class="space-y-2">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Account</div>
                    <a class="{{ request()->routeIs('client.profile.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('client.profile.edit') }}">
                        <span class="h-2 w-2 rounded-full bg-current"></span>
                        Profile
                    </a>
                </div>
            </nav>

            @php
                $sidebarUser = auth()->user();
                $sidebarName = $sidebarUser?->name ?? 'Client';
                $nameParts = preg_split('/\s+/', trim($sidebarName));
                $sidebarInitials = '';
                foreach ($nameParts as $part) {
                    if ($part !== '') {
                        $sidebarInitials .= strtoupper(substr($part, 0, 1));
                        if (strlen($sidebarInitials) >= 2) {
                            break;
                        }
                    }
                }
                $sidebarInitials = $sidebarInitials !== '' ? $sidebarInitials : 'CL';
            @endphp
            <div class="mt-auto space-y-4">
                <div class="rounded-2xl border border-white/10 bg-white/5 p-4 text-slate-200">
                    <div class="flex items-center gap-3">
                        <div class="grid h-10 w-10 place-items-center rounded-full bg-white/10 text-sm font-semibold text-white">
                            {{ $sidebarInitials }}
                        </div>
                        <div class="min-w-0">
                            <div class="truncate text-sm font-semibold text-white">{{ $sidebarName }}</div>
                            <div class="text-[11px] text-slate-400">Client account</div>
                        </div>
                    </div>
                    @if(session()->has('impersonator_id'))
                        <form method="POST" action="{{ route('impersonate.stop') }}" class="mt-3">
                            @csrf
                            <button type="submit" class="w-full rounded-full border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700 transition hover:border-amber-300">
                                Return to Admin
                            </button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('logout') }}" class="mt-3">
                        @csrf
                        <button type="submit" class="w-full rounded-full border border-white/10 bg-white/10 px-3 py-2 text-xs font-semibold text-slate-200 transition hover:bg-white/20">
                            Sign out
                        </button>
                    </form>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/5 p-4 text-xs text-slate-300">
                    Need help? Contact support to keep access active.
                </div>
            </div>
        </aside>

        <div class="flex-1">
            <header class="sticky top-0 z-20 border-b border-slate-200/70 bg-white/80 backdrop-blur">
                <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                    <div>
                        <div class="section-label">Client workspace</div>
                        <div class="text-lg font-semibold text-slate-900">@yield('page-title', 'Overview')</div>
                    </div>
                    <div class="hidden items-center gap-4 md:flex"></div>
                </div>

                @if(session()->has('impersonator_id'))
                    <div class="mx-auto max-w-6xl px-6 pb-3">
                        <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
                            <div class="text-[11px] uppercase tracking-[0.28em] text-amber-600">Impersonation</div>
                            <div class="text-sm text-amber-800">
                                You are logged in as <span class="font-semibold">{{ auth()->user()->name ?? 'Client' }}</span>. Actions are on behalf of this client.
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
                            <a href="{{ route('client.dashboard') }}" class="text-slate-700 hover:text-teal-600">Overview</a>

                            <div class="text-xs uppercase tracking-[0.2em] text-slate-400 pt-2">Projects & Services</div>
                            {{-- Client-facing: services list mixes projects and recurring maintenance/subscriptions --}}
                            <a href="{{ route('client.services.index') }}" class="text-slate-700 hover:text-teal-600">Projects & Services</a>
                            <a href="{{ route('client.domains.index') }}" class="text-slate-700 hover:text-teal-600">Domains</a>
                            <a href="{{ route('client.licenses.index') }}" class="text-slate-700 hover:text-teal-600">Licenses</a>

                            <div class="text-xs uppercase tracking-[0.2em] text-slate-400 pt-2">Orders & Requests</div>
                            <a href="{{ route('client.orders.index') }}" class="text-slate-700 hover:text-teal-600">My Orders</a>

                            <div class="text-xs uppercase tracking-[0.2em] text-slate-400 pt-2">Billing & Payments</div>
                            <a href="{{ route('client.invoices.index') }}" class="text-slate-700 hover:text-teal-600">Invoices</a>

                            <div class="text-xs uppercase tracking-[0.2em] text-slate-400 pt-2">Support & Growth</div>
                            <a href="{{ route('client.support-tickets.index') }}" class="text-slate-700 hover:text-teal-600">Support</a>
                            <a href="{{ route('client.affiliates.index') }}" class="text-slate-700 hover:text-teal-600">Affiliates</a>

                            <div class="text-xs uppercase tracking-[0.2em] text-slate-400 pt-2">Account</div>
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

                @if(!empty($clientAccessBlock['blocked']))
                    @php
                        $graceEnds = $clientAccessBlock['grace_ends_at']
                            ? \Illuminate\Support\Carbon::parse($clientAccessBlock['grace_ends_at'])->format($globalDateFormat . ' H:i')
                            : null;
                        $invoiceLabel = $clientAccessBlock['invoice_number'] ? "Invoice #{$clientAccessBlock['invoice_number']}" : 'your outstanding invoice';
                        $accessMessage = "Please pay {$invoiceLabel} to restore access" . ($graceEnds ? " before {$graceEnds}" : '') . '.';
                    @endphp
                    <div class="mb-6 rounded-3xl border border-rose-200 bg-rose-50 px-6 py-4 text-rose-800">
                        <div class="flex flex-wrap items-center gap-3">
                            <div class="text-xs uppercase tracking-[0.35em] text-rose-500">Account blocked</div>
                            <div class="flex-1 text-sm text-rose-700">
                                <span class="font-semibold">Access to most areas is restricted.</span>
                                <span class="ml-1">{{ $accessMessage }}</span>
                            </div>
                            @if(!empty($clientAccessBlock['payment_url']))
                                <a href="{{ $clientAccessBlock['payment_url'] }}" class="inline-flex items-center rounded-full border border-rose-300 px-4 py-2 text-xs font-semibold text-rose-700 transition hover:bg-rose-100">
                                    Pay invoice
                                </a>
                            @endif
                        </div>
                    </div>
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
