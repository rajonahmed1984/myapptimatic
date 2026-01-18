<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.head')
</head>
<body class="bg-dashboard">
    <div class="min-h-screen flex flex-col md:flex-row">
        <div id="sidebarOverlay" class="fixed inset-0 z-20 bg-slate-900/60 opacity-0 pointer-events-none transition-opacity duration-200 md:hidden"></div>
        <aside id="adminSidebar" class="sidebar fixed inset-y-0 left-0 z-30 flex w-72 max-w-[90vw] flex-shrink-0 flex-col px-6 py-7 overflow-y-auto max-h-screen transform transition-transform duration-200 ease-in-out -translate-x-full md:w-64 md:max-w-none md:translate-x-0 md:overflow-y-auto md:max-h-screen md:sticky md:top-0">
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
                    <div class="text-xs uppercase tracking-[0.35em] text-slate-400">Admin</div>
                    <div class="text-lg font-semibold text-white">{{ $portalBranding['company_name'] ?? 'License Portal' }}</div>
                </div>
            </div>
            <button type="button" id="sidebarClose" class="absolute right-4 top-4 rounded-full border border-white/10 bg-white/10 p-2 text-slate-200 transition hover:bg-white/20 md:hidden" aria-label="Close menu">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            @php
                $isAdminNav = request()->routeIs('admin.*');
                $isEmployeeNav = request()->routeIs('employee.*');
                $isSalesRepNav = request()->routeIs('rep.*');
                
                // Nested menu: Projects/Maintenance
                $projectsMenuActive = isActive(['admin.projects.*', 'admin.project-maintenances.*']);
                
                // Nested menu: Invoices
                $invoiceMenuActive = isActive('admin.invoices.*');
                
                // Nested menu: Logs
                $logMenuActive = isActive('admin.logs.*');
            @endphp
            <nav class="mt-10 space-y-4 text-sm">
                @if($isAdminNav)
                    <div>
                        <x-nav-link 
                            :href="route('admin.dashboard')"
                            routes="admin.dashboard"
                        >
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Dashboard
                        </x-nav-link>
                    </div>

                    <div class="space-y-2">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Sales & Customers</div>
                        <x-nav-link 
                            :href="route('admin.customers.index')"
                            routes="admin.customers.*"
                        >
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Customers
                        </x-nav-link>
                        <x-nav-link 
                            :href="route('admin.orders.index')"
                            routes="admin.orders.*"
                        >
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Orders
                        </x-nav-link>
                        <x-nav-link 
                            :href="route('admin.sales-reps.index')"
                            routes="admin.sales-reps.*"
                        >
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Sales Representatives
                        </x-nav-link>
                        <x-nav-link 
                            :href="route('admin.affiliates.index')"
                            routes="admin.affiliates.*"
                        >
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Affiliates
                        </x-nav-link>
                        <x-nav-link 
                            :href="route('admin.requests.index')"
                            routes="admin.requests.*"
                        >
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Requests
                        </x-nav-link>
                    </div>

                    <div class="space-y-2">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Delivery & Services</div>
                        
                        {{-- Nested menu: Projects --}}
                        <x-nav-menu
                            :href="route('admin.projects.index')"
                            :routes="['admin.projects.*', 'admin.project-maintenances.*']"
                            label="Projects"
                        >
                            <a href="{{ route('admin.projects.index') }}" class="block {{ activeIf(request()->routeIs('admin.projects.index')) }}">All Projects</a>
                            <a href="{{ route('admin.projects.create') }}" class="block {{ activeIf(request()->routeIs('admin.projects.create')) }}">Create Project</a>
                            <a href="{{ route('admin.project-maintenances.index') }}" class="block {{ activeIf(request()->routeIs('admin.project-maintenances.*')) }}">Maintenance</a>
                        </x-nav-menu>
                        
                        <x-nav-link 
                            :href="route('admin.subscriptions.index')"
                            routes="admin.subscriptions.*"
                        >
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Subscriptions
                        </x-nav-link>
                        <x-nav-link 
                            :href="route('admin.licenses.index')"
                            routes="admin.licenses.*"
                        >
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Licenses
                        </x-nav-link>
                    </div>

                    <div class="space-y-2">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Billing & Finance</div>
                        
                        {{-- Nested menu: Invoices --}}
                        <x-nav-menu
                            :href="route('admin.invoices.index')"
                            routes="admin.invoices.*"
                            label="Invoices"
                        >
                            <a href="{{ route('admin.invoices.index') }}" class="block {{ activeIf(request()->routeIs('admin.invoices.index')) }}">All invoices</a>
                            <a href="{{ route('admin.invoices.paid') }}" class="block {{ activeIf(request()->routeIs('admin.invoices.paid')) }}">Paid</a>
                            <a href="{{ route('admin.invoices.unpaid') }}" class="block {{ activeIf(request()->routeIs('admin.invoices.unpaid')) }}">Unpaid</a>
                            <a href="{{ route('admin.invoices.overdue') }}" class="block {{ activeIf(request()->routeIs('admin.invoices.overdue')) }}">Overdue</a>
                            <a href="{{ route('admin.invoices.cancelled') }}" class="block {{ activeIf(request()->routeIs('admin.invoices.cancelled')) }}">Cancelled</a>
                            <a href="{{ route('admin.invoices.refunded') }}" class="block {{ activeIf(request()->routeIs('admin.invoices.refunded')) }}">Refunded</a>
                        </x-nav-menu>
                        
                        <x-nav-link 
                            :href="route('admin.payment-proofs.index')"
                            routes="admin.payment-proofs.*"
                            :badge="($adminHeaderStats['pending_manual_payments'] ?? 0) > 0 ? $adminHeaderStats['pending_manual_payments'] : null"
                        >
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            <span>Manual Payments</span>
                        </x-nav-link>
                        <x-nav-link 
                            :href="route('admin.accounting.index')"
                            routes="admin.accounting.*"
                        >
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Accounting
                        </x-nav-link>
                        <x-nav-link 
                            :href="route('admin.payment-gateways.index')"
                            routes="admin.payment-gateways.*"
                        >
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Payment Gateways
                        </x-nav-link>
                        <x-nav-link 
                            :href="route('admin.commission-payouts.index')"
                            routes="admin.commission-payouts.*"
                        >
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Commission Payouts
                        </x-nav-link>
                    </div>

                    <div class="space-y-2">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Products & Plans</div>
                        <x-nav-link 
                            :href="route('admin.products.index')"
                            routes="admin.products.*"
                        >
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Products
                        </x-nav-link>
                        <x-nav-link 
                            :href="route('admin.plans.index')"
                            routes="admin.plans.*"
                        >
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Plans
                        </x-nav-link>
                    </div>

                    <div class="space-y-2">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">People (HR)</div>
                        <x-nav-link 
                            :href="route('admin.hr.dashboard')"
                            routes="admin.hr.dashboard"
                        >
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            HR Dashboard
                        </x-nav-link>
                        <x-nav-link 
                            :href="route('admin.hr.employees.index')"
                            routes="admin.hr.employees.*"
                        >
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Employees
                        </x-nav-link>
                        <x-nav-link 
                            :href="route('admin.employees.summary')"
                            routes="admin.employees.summary"
                        >
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Employee Summary
                        </x-nav-link>
                        <x-nav-link 
                            :href="route('admin.users.activity-summary')"
                            routes="admin.users.activity-summary"
                        >
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Activity Summary
                        </x-nav-link>
                        <x-nav-link 
                            :href="route('admin.hr.timesheets.index')"
                            routes="admin.hr.timesheets.*"
                        >
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Timesheets
                        </x-nav-link>
                        <x-nav-link 
                            :href="route('admin.hr.leave-types.index')"
                            routes="admin.hr.leave-types.*"
                        >
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Leave Types
                        </x-nav-link>
                        <x-nav-link 
                            :href="route('admin.hr.leave-requests.index')"
                            routes="admin.hr.leave-requests.*"
                        >
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Leave Requests
                        </x-nav-link>
                        <x-nav-link 
                            :href="route('admin.hr.payroll.index')"
                            routes="admin.hr.payroll.*"
                        >
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Payroll
                        </x-nav-link>
                    </div>

                    <div class="space-y-2">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Support & Communication</div>
                        <x-nav-link 
                            :href="route('admin.support-tickets.index')"
                            routes="admin.support-tickets.*"
                            :badge="($adminHeaderStats['tickets_waiting'] ?? 0) > 0 ? $adminHeaderStats['tickets_waiting'] : null"
                        >
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            <span>Support</span>
                        </x-nav-link>
                    </div>

                    <div class="space-y-2">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Administration</div>
                        @php
                            $usersNavRole = request()->route('role') ?? optional(request()->route('user'))->role;
                            $isUsersRoute = isActive(['admin.users.*', 'admin.admins.*']);
                        @endphp
                        @if(auth()->user()?->isMasterAdmin())
                            <div class="space-y-1">
                                <x-nav-link 
                                    :href="route('admin.users.index', 'master_admin')"
                                    :inactiveClass="$isUsersRoute && $usersNavRole === 'master_admin' ? 'nav-link nav-link-active' : 'nav-link'"
                                    :activeClass="$isUsersRoute && $usersNavRole === 'master_admin' ? 'nav-link nav-link-active' : 'nav-link'"
                                >
                                    <span class="h-2 w-2 rounded-full bg-current"></span>
                                    Master Admins
                                </x-nav-link>
                                <x-nav-link 
                                    :href="route('admin.users.index', 'sub_admin')"
                                    :inactiveClass="$isUsersRoute && $usersNavRole === 'sub_admin' ? 'nav-link nav-link-active' : 'nav-link'"
                                    :activeClass="$isUsersRoute && $usersNavRole === 'sub_admin' ? 'nav-link nav-link-active' : 'nav-link'"
                                >
                                    <span class="h-2 w-2 rounded-full bg-current"></span>
                                    Sub Admins
                                </x-nav-link>
                                <x-nav-link 
                                    :href="route('admin.users.index', 'support')"
                                    :inactiveClass="$isUsersRoute && $usersNavRole === 'support' ? 'nav-link nav-link-active' : 'nav-link'"
                                    :activeClass="$isUsersRoute && $usersNavRole === 'support' ? 'nav-link nav-link-active' : 'nav-link'"
                                >
                                    <span class="h-2 w-2 rounded-full bg-current"></span>
                                    Support Users
                                </x-nav-link>
                            </div>
                        @endif
                        <x-nav-link 
                            :href="route('admin.profile.edit')"
                            routes="admin.profile.*"
                        >
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Profile
                        </x-nav-link>
                    </div>

                    <div class="space-y-2">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">System & Monitoring</div>
                        <x-nav-link 
                            :href="route('admin.automation-status')"
                            routes="admin.automation-status"
                        >
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Automation Status
                        </x-nav-link>
                        
                        {{-- Nested menu: Logs --}}
                        <x-nav-menu
                            :href="route('admin.logs.activity')"
                            routes="admin.logs.*"
                            label="Logs"
                        >
                            <a href="{{ route('admin.logs.activity') }}" class="block {{ activeIf(request()->routeIs('admin.logs.activity')) }}">Activity</a>
                            <a href="{{ route('admin.logs.admin') }}" class="block {{ activeIf(request()->routeIs('admin.logs.admin')) }}">Admin</a>
                            <a href="{{ route('admin.logs.module') }}" class="block {{ activeIf(request()->routeIs('admin.logs.module')) }}">Module</a>
                            <a href="{{ route('admin.logs.email') }}" class="block {{ activeIf(request()->routeIs('admin.logs.email')) }}">Email</a>
                            <a href="{{ route('admin.logs.ticket-mail-import') }}" class="block {{ activeIf(request()->routeIs('admin.logs.ticket-mail-import')) }}">Ticket Mail Import</a>
                        </x-nav-menu>
                        
                        <x-nav-link 
                            :href="route('admin.settings.edit')"
                            routes="admin.settings.*"
                        >
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Settings
                        </x-nav-link>
                    </div>
                @elseif($isEmployeeNav)
                    <div>
                        <x-nav-link 
                            :href="route('employee.dashboard')"
                            routes="employee.dashboard"
                        >
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Dashboard
                        </x-nav-link>
                    </div>
                    <div class="space-y-2">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">My Work</div>
                        <x-nav-link 
                            :href="route('employee.projects.index')"
                            routes="employee.projects.*"
                        >
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Projects
                        </x-nav-link>
                        <x-nav-link 
                            :href="route('employee.timesheets.index')"
                            routes="employee.timesheets.*"
                        >
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Timesheets
                        </x-nav-link>
                        <x-nav-link 
                            :href="route('employee.leave-requests.index')"
                            routes="employee.leave-requests.*"
                        >
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Leave Requests
                        </x-nav-link>
                    </div>
                    <div class="space-y-2">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Payroll</div>
                        <x-nav-link 
                            :href="route('employee.payroll.index')"
                            routes="employee.payroll.*"
                        >
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Payroll
                        </x-nav-link>
                    </div>
                @elseif($isSalesRepNav)
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
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Earnings</div>
                        <x-nav-link 
                            :href="route('rep.earnings.index')"
                            routes="rep.earnings.*"
                        >
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Earnings
                        </x-nav-link>
                        <x-nav-link 
                            :href="route('rep.payouts.index')"
                            routes="rep.payouts.*"
                        >
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Payouts
                        </x-nav-link>
                    </div>
                @endif
            </nav>

            @php
                $sidebarUser = auth()->user();
                $sidebarName = $sidebarUser?->name ?? 'User';
                $sidebarRole = 'Administrator';
                if ($sidebarUser?->isEmployee()) {
                    $sidebarRole = 'Employee';
                } elseif ($sidebarUser?->isMasterAdmin()) {
                    $sidebarRole = 'Master Administrator';
                } elseif ($sidebarUser?->isSubAdmin()) {
                    $sidebarRole = 'Sub Administrator';
                } elseif ($sidebarUser?->isSales()) {
                    $sidebarRole = 'Sales Representative';
                } elseif ($sidebarUser?->isSupport()) {
                    $sidebarRole = 'Support';
                } elseif ($sidebarUser?->isClient()) {
                    $sidebarRole = 'Client';
                }
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
                $sidebarInitials = $sidebarInitials !== '' ? $sidebarInitials : 'U';
            @endphp
            <div class="mt-auto">
                <div class="mt-5 rounded-2xl border border-white/10 bg-white/5 p-4 text-slate-200">
                    <div class="flex items-center gap-3">
                        <div class="grid h-10 w-10 place-items-center rounded-full bg-white/10 text-sm font-semibold text-white">
                            {{ $sidebarInitials }}
                        </div>
                        <div class="min-w-0">
                            <div class="truncate text-sm font-semibold text-white">{{ $sidebarName }}</div>
                            <div class="text-[11px] text-slate-400">{{ $sidebarRole }}</div>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('admin.logout') }}" class="mt-3">
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
                        <button type="button" id="sidebarToggle" class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 text-slate-600 transition hover:border-teal-300 hover:text-teal-600 md:hidden" aria-label="Open menu">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                        <div>
                            <div class="section-label">Admin workspace</div>
                            <div class="text-lg font-semibold text-slate-900">@yield('page-title', 'Overview')</div>
                        </div>
                    </div>
                    @php($adminUser = auth()->user())
                    @if(!empty($adminHeaderStats) && $adminUser && ! $adminUser->isEmployee())
                        <div class="stats hidden flex-wrap items-center gap-3 text-xs text-slate-500 lg:flex">
                            @if($adminUser->isAdmin())
                                {{-- Master Admin and Admin see all stats --}}
                                <a href="{{ route('admin.orders.index', ['status' => 'pending']) }}" class="flex items-center gap-2">
                                    <span class="stat">{{ $adminHeaderStats['pending_orders'] ?? 0 }}</span>
                                    Pending Orders
                                </a>
                                <span class="text-slate-300">|</span>
                                <a href="{{ route('admin.invoices.overdue') }}" class="flex items-center gap-2">
                                    <span class="stat">{{ $adminHeaderStats['overdue_invoices'] ?? 0 }}</span>
                                    Overdue Invoices
                                </a>
                                <span class="text-slate-300">|</span>
                            @endif
                            @if($adminUser->isSupport() || $adminUser->isMasterAdmin())
                                {{-- Support sees only tickets --}}
                                <a href="{{ route('admin.support-tickets.index', ['status' => 'customer_reply']) }}" class="flex items-center gap-2">
                                    <span class="stat">{{ $adminHeaderStats['tickets_waiting'] ?? 0 }}</span>
                                    Ticket(s) Awaiting Reply
                                </a>
                            @endif
                        </div>
                    @endif
                    <div class="hidden items-center gap-4 md:flex">
                        <form method="POST" action="{{ route('admin.system.cache.clear') }}">
                            @csrf
                            <button
                                type="submit"
                                class="inline-flex items-center gap-2 rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600 transition hover:border-teal-300 hover:text-teal-600"
                                title="Clears Laravel system caches and purges browser storage helpers"
                            >
                                Clear caches
                            </button>
                        </form>
                    </div>
                </div>

                @if(session()->has('impersonator_id') && !auth()->user()?->isAdmin())
                    <div class="w-full px-6 pb-3">
                        <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
                            <div class="text-[11px] uppercase tracking-[0.28em] text-amber-600">Impersonation</div>
                            <div class="text-sm text-amber-800">
                                You are logged in as <span class="font-semibold">{{ auth()->user()->name ?? 'client' }}</span>. Access is limited to their workspace.
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

            <main id="main-content" class="w-full px-6 py-10 fade-in" hx-boost="true">
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
    {{-- Admin layout standard (from /admin/customers):
         - Content wrapper: main.mx-auto.max-w-6xl.px-6.py-10
         - Page header: .mb-6 .flex .items-center .justify-between .gap-4 with title on the left and actions on the right
         - Primary sections: cards with consistent padding (p-6/8), tables using w-full text-left text-sm, and overflow-x-auto if a table needs extra width --}}
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('adminSidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const openBtn = document.getElementById('sidebarToggle');
            const closeBtn = document.getElementById('sidebarClose');

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

            // HTMX configuration: Update active sidebar state after content loads
            document.addEventListener('htmx:afterSwap', function(event) {
                // Reload page to ensure sidebar active states are updated
                // This is a safeguard; the server-side route detection should already handle it
                // For pure HTMX without reload, you could update active classes here
            });
        });
    </script>
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
    @stack('scripts')
</body>
</html>
