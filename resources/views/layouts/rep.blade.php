<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.head')
</head>
<body class="bg-dashboard">
    <div class="min-h-screen flex flex-col md:flex-row">
        <div id="repSidebarOverlay" class="fixed inset-0 z-20 bg-slate-900/60 opacity-0 pointer-events-none transition-opacity duration-200 md:hidden"></div>
        <aside id="repSidebar" class="sidebar fixed inset-y-0 left-0 z-30 flex w-72 max-w-[90vw] flex-shrink-0 flex-col px-6 py-7 overflow-y-auto max-h-screen transform transition-transform duration-200 ease-in-out -translate-x-full md:w-64 md:max-w-none md:translate-x-0 md:overflow-y-auto md:max-h-screen md:sticky md:top-0">
            <div class="flex items-center gap-3">
                @php
                    $sidebarImage = $portalBranding['favicon_url'] ?? ($portalBranding['logo_url'] ?? null);
                    $canViewTasks = app(\App\Services\TaskQueryService::class)->canViewTasks(auth()->user());
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

            <button type="button" id="repSidebarClose" class="absolute right-4 top-4 rounded-full border border-white/10 bg-white/10 p-2 text-slate-200 transition hover:bg-white/20 md:hidden" aria-label="Close menu">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

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
                    @if($canViewTasks)
                        <x-nav-link
                            :href="route('rep.tasks.index')"
                            routes="rep.tasks.*"
                        >
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            <span>Tasks</span>
                            <span class="ml-auto rounded-full bg-white px-2 py-0.5 text-[10px] text-slate-500">{{ $repHeaderStats['task_badge'] ?? 0 }}</span>
                        </x-nav-link>
                    @endif
                    <x-nav-link
                        :href="route('rep.chats.index')"
                        :routes="['rep.chats.*', 'rep.projects.chat']"
                    >
                        <span class="h-2 w-2 rounded-full bg-current"></span>
                        <span>Chat</span>
                        <span class="ml-auto rounded-full bg-white px-2 py-0.5 text-[10px] text-slate-500">{{ $repHeaderStats['unread_chat'] ?? 0 }}</span>
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
                <div class="space-y-2">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Account</div>
                    <x-nav-link 
                        :href="route('rep.profile.edit')"
                        routes="rep.profile.*"
                    >
                        <span class="h-2 w-2 rounded-full bg-current"></span>
                        Profile & Security
                    </x-nav-link>
                </div>
            </nav>

            @php
                $sidebarUser = auth()->user();
                $sidebarSalesRep = $sidebarUser ? request()->attributes->get('salesRep') : null;
                $sidebarName = $sidebarSalesRep?->name
                    ?? $sidebarUser?->name
                    ?? 'Sales Rep';
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
                $sidebarInitials = $sidebarInitials !== '' ? $sidebarInitials : 'SR';
            @endphp
            <div class="mt-auto space-y-4">
                <div class="rounded-2xl border border-white/10 bg-white/5 p-4 text-slate-200">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 overflow-hidden rounded-full border border-white/10 bg-white/10">
                            <x-avatar :path="$salesRep?->avatar_path ?? $sidebarUser?->avatar_path" :name="$sidebarName" size="h-10 w-10" textSize="text-sm" />
                        </div>
                        <div class="min-w-0">
                            <div class="truncate text-sm font-semibold text-white">{{ $sidebarName }}</div>
                            <div class="text-[11px] text-slate-400">Sales Representative</div>
                        </div>
                    </div>                    
                    <form method="POST" action="{{ route('rep.logout') }}" class="mt-3">
                        @csrf
                        <button type="submit" class="w-full rounded-full border border-white/10 bg-white/10 px-3 py-2 text-xs font-semibold text-slate-200 transition hover:bg-white/20">
                            Sign out
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <div class="flex-1 flex flex-col w-full min-w-0">
            <header class="sticky top-0 z-20 border-b border-slate-200/70 bg-white/80 backdrop-blur">
                <div class="flex w-full items-center justify-between gap-6 px-6 py-4">
                    <div class="flex items-center gap-3">
                        <button type="button" id="repSidebarToggle" class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 text-slate-600 transition hover:border-teal-300 hover:text-teal-600 md:hidden" aria-label="Open menu">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                        <div>
                            <div class="section-label">Sales rep workspace</div>
                            <div class="text-lg font-semibold text-slate-900">@yield('page-title', 'Overview')</div>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 md:gap-4">
                        <form method="POST" action="{{ route('rep.system.cache.clear') }}">
                            @csrf
                            <button
                                type="submit"
                                class="inline-flex items-center gap-2 rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600 transition hover:border-teal-300 hover:text-teal-600"
                                title="Clears Laravel caches and purges browser storage helpers"
                            >
                                Clear caches
                            </button>
                        </form>
                    </div>
                </div>

                @if(session()->has('impersonator_id'))
                <div class="flex w-full px-6 pb-3">
                        <div class="flex w-full flex-wrap items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
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
            </header>

            <main class="w-full px-6 py-10 fade-in">
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
            const sidebar = document.getElementById('repSidebar');
            const overlay = document.getElementById('repSidebarOverlay');
            const openBtn = document.getElementById('repSidebarToggle');
            const closeBtn = document.getElementById('repSidebarClose');

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
    @include('layouts.partials.delete-confirm-modal')
    @include('layouts.partials.table-responsive')
    @if(session('cache_cleared'))
        <script>
            (async function () {
                const safeRun = async (fn) => {
                    try {
                        await fn();
                    } catch (error) {
                        console.warn('Browser purge helper failed', error);
                    }
                };

                await safeRun(async () => {
                    if (window.caches && window.caches.keys) {
                        const keys = await window.caches.keys();
                        await Promise.all(keys.map((key) => window.caches.delete(key)));
                    }
                });

                try {
                    localStorage.clear();
                } catch (error) {
                    console.warn('Failed to clear localStorage', error);
                }

                try {
                    sessionStorage.clear();
                } catch (error) {
                    console.warn('Failed to clear sessionStorage', error);
                }

                await safeRun(async () => {
                    const indexedDBInstance = window.indexedDB;
                    if (indexedDBInstance?.databases) {
                        const databases = await indexedDBInstance.databases();
                        await Promise.all(
                            databases.filter((db) => db?.name).map((db) => indexedDBInstance.deleteDatabase(db.name))
                        );
                    }
                });

                await safeRun(async () => {
                    if (navigator.serviceWorker?.getRegistrations) {
                        const registrations = await navigator.serviceWorker.getRegistrations();
                        await Promise.all(registrations.map((registration) => registration.unregister()));
                    }
                });

                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            })();
        </script>
    @endif
</body>
</html>
