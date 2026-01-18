<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.head')
</head>
<body class="bg-dashboard">
    <div class="min-h-screen flex flex-col md:flex-row">
        <div id="clientSidebarOverlay" class="fixed inset-0 z-20 bg-slate-900/60 opacity-0 pointer-events-none transition-opacity duration-200 md:hidden"></div>
        <aside id="clientSidebar" class="sidebar fixed inset-y-0 left-0 z-30 flex w-72 max-w-[90vw] flex-shrink-0 flex-col px-6 py-7 overflow-y-auto max-h-screen transform transition-transform duration-200 ease-in-out -translate-x-full md:w-64 md:max-w-none md:translate-x-0 md:overflow-y-auto md:max-h-screen md:sticky md:top-0">
            <div class="flex items-center gap-3">
                @php
                    $sidebarImage = $portalBranding['favicon_url'] ?? ($portalBranding['logo_url'] ?? null);
                @endphp
                @if(!empty($sidebarImage))
                    <img src="{{ $sidebarImage }}" alt="Brand mark" class="h-11 w-11 rounded-2xl bg-white p-1">
                @else
                    <div class="grid h-11 w-11 place-items-center rounded-2xl bg-white/10 text-lg font-semibold text-white">CL</div>
                @endif
                <div>
                    <div class="text-xs uppercase tracking-[0.35em] text-slate-400">Client</div>
                    <div class="text-lg font-semibold text-white">{{ $portalBranding['company_name'] ?? 'License Portal' }}</div>
                </div>
            </div>
            <button type="button" id="clientSidebarClose" class="absolute right-4 top-4 rounded-full border border-white/10 bg-white/10 p-2 text-slate-200 transition hover:bg-white/20 md:hidden" aria-label="Close menu">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

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
                        Support
                        @if(($clientHeaderStats['pending_admin_replies'] ?? 0) > 0)
                            <span class="ml-auto rounded-full bg-teal-100 px-2 py-0.5 text-xs font-semibold text-teal-700">{{ $clientHeaderStats['pending_admin_replies'] }}</span>
                        @endif
                    </a>
                    <a class="{{ request()->routeIs('client.affiliates.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('client.affiliates.index') }}">
                        <span class="h-2 w-2 rounded-full bg-current"></span>
                        Affiliates
                    </a>
                </div>
            </nav>

            <div class="mt-auto">
                <div class="mt-5 rounded-2xl border border-white/10 bg-white/5 p-4 text-xs text-slate-300">
                    Client view only.
                </div>
            </div>
        </aside>

        <div class="flex-1 flex flex-col w-full min-w-0">
            <header class="sticky top-0 z-20 border-b border-slate-200/70 bg-white/80 backdrop-blur">
                <div class="flex w-full items-center justify-between gap-6 px-6 py-4">
                    <div class="flex items-center gap-3">
                        <button type="button" id="clientSidebarToggle" class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 text-slate-600 transition hover:border-teal-300 hover:text-teal-600 md:hidden" aria-label="Open menu">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                        <div>
                            <div class="section-label">Client workspace</div>
                            <div class="text-lg font-semibold text-slate-900">@yield('page-title', 'Overview')</div>
                        </div>
                    </div>
                    <div class="hidden items-center gap-4 md:flex">
                        <div class="text-right text-sm">
                            <div class="font-semibold text-slate-900">{{ auth()->user()->name }}</div>
                            <div class="text-xs text-slate-500">Client</div>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 transition hover:border-teal-300 hover:text-teal-600">
                                Sign out
                            </button>
                        </form>
                    </div>
                </div>

                @if(session()->has('impersonator_id'))
                    <div class="w-full px-6 pb-3">
                        <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
                            <div class="text-[11px] uppercase tracking-[0.28em] text-amber-600">Impersonation</div>
                            <div class="text-sm text-amber-800">
                                You are logged in as <span class="font-semibold">{{ auth()->user()->name ?? 'client' }}</span>. Actions are on behalf of this client.
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
            </header>

            <main class="w-full px-6 py-10 fade-in">
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

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('clientSidebar');
            const overlay = document.getElementById('clientSidebarOverlay');
            const openBtn = document.getElementById('clientSidebarToggle');
            const closeBtn = document.getElementById('clientSidebarClose');

            const openSidebar = () => {
                sidebar?.classList.remove('-translate-x-full');
                overlay?.classList.remove('opacity-0', 'pointer-events-none');
            };

            const closeSidebar = () => {
                sidebar?.classList.add('-translate-x-full');
                overlay?.classList.add('opacity-0', 'pointer-events-none');
            };

            openBtn?.addEventListener('click', openSidebar);
            closeBtn?.addEventListener('click', closeSidebar);
            overlay?.addEventListener('click', closeSidebar);
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeSidebar();
                }
            });
        });
    </script>
    @include('layouts.partials.table-responsive')
</body>
</html>
