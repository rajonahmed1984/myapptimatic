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
                    <div class="text-xs uppercase tracking-[0.35em] text-slate-400">Admin</div>
                    <div class="text-lg font-semibold text-white">{{ $portalBranding['company_name'] ?? 'License Portal' }}</div>
                </div>
            </div>

            @php
                $isAdminNav = request()->routeIs('admin.*');
                $isEmployeeNav = request()->routeIs('employee.*');
                $isSalesRepNav = request()->routeIs('rep.*');
                $invoiceMenuActive = $isAdminNav && request()->routeIs('admin.invoices.*');
                $logMenuActive = $isAdminNav && request()->routeIs('admin.logs.*');
            @endphp
            <nav class="mt-10 space-y-4 text-sm">
                @if($isAdminNav)
                    <div>
                        <a class="{{ request()->routeIs('admin.dashboard') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('admin.dashboard') }}">
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Dashboard
                        </a>
                    </div>

                    <div class="space-y-2">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Sales & Customers</div>
                        <a class="{{ request()->routeIs('admin.customers.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('admin.customers.index') }}">
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Customers
                        </a>
                        <a class="{{ request()->routeIs('admin.orders.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('admin.orders.index') }}">
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Orders
                        </a>
                        <a class="{{ request()->routeIs('admin.affiliates.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('admin.affiliates.index') }}">
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Affiliates
                        </a>
                        <a class="{{ request()->routeIs('admin.requests.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('admin.requests.index') }}">
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Requests
                        </a>
                    </div>

                    <div class="space-y-2">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Delivery & Services</div>
                        <a class="{{ request()->routeIs('admin.projects.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('admin.projects.index') }}">
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Projects <!-- Delivery: project-based execution, tasks, milestones -->
                        </a>
                        <a class="{{ request()->routeIs('admin.subscriptions.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('admin.subscriptions.index') }}">
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Subscriptions <!-- Recurring services/maintenance; avoid mixing with one-off projects -->
                        </a>
                        <a class="{{ request()->routeIs('admin.licenses.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('admin.licenses.index') }}">
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Licenses <!-- Delivery artifacts tied to subs/orders -->
                        </a>
                    </div>

                    <div class="space-y-2">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Billing & Finance</div>
                        <a class="{{ request()->routeIs('admin.invoices.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('admin.invoices.index') }}">
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Invoices
                        </a>
                        @if($invoiceMenuActive)
                            <div class="ml-6 space-y-1 text-xs text-slate-400">
                                <a href="{{ route('admin.invoices.index') }}" class="block {{ request()->routeIs('admin.invoices.index') ? 'text-teal-300' : 'hover:text-slate-200' }}">All invoices</a>
                                <a href="{{ route('admin.invoices.paid') }}" class="block {{ request()->routeIs('admin.invoices.paid') ? 'text-teal-300' : 'hover:text-slate-200' }}">Paid</a>
                                <a href="{{ route('admin.invoices.unpaid') }}" class="block {{ request()->routeIs('admin.invoices.unpaid') ? 'text-teal-300' : 'hover:text-slate-200' }}">Unpaid</a>
                                <a href="{{ route('admin.invoices.overdue') }}" class="block {{ request()->routeIs('admin.invoices.overdue') ? 'text-teal-300' : 'hover:text-slate-200' }}">Overdue</a>
                                <a href="{{ route('admin.invoices.cancelled') }}" class="block {{ request()->routeIs('admin.invoices.cancelled') ? 'text-teal-300' : 'hover:text-slate-200' }}">Cancelled</a>
                                <a href="{{ route('admin.invoices.refunded') }}" class="block {{ request()->routeIs('admin.invoices.refunded') ? 'text-teal-300' : 'hover:text-slate-200' }}">Refunded</a>
                            </div>
                        @endif
                        <a class="{{ request()->routeIs('admin.payment-proofs.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('admin.payment-proofs.index') }}">
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            <span>Manual Payments</span>
                            @if(($adminHeaderStats['pending_manual_payments'] ?? 0) > 0)
                                <span class="ml-auto rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700">{{ $adminHeaderStats['pending_manual_payments'] }}</span>
                            @endif
                        </a>
                        <a class="{{ request()->routeIs('admin.accounting.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('admin.accounting.index') }}">
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Accounting
                        </a>
                        <a class="{{ request()->routeIs('admin.payment-gateways.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('admin.payment-gateways.index') }}">
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Payment Gateways
                        </a>
                        <a class="{{ request()->routeIs('admin.commission-payouts.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('admin.commission-payouts.index') }}">
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Commission Payouts
                        </a>
                    </div>

                    <div class="space-y-2">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Products & Plans</div>
                        <a class="{{ request()->routeIs('admin.products.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('admin.products.index') }}">
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Products
                        </a>
                        <a class="{{ request()->routeIs('admin.plans.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('admin.plans.index') }}">
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Plans
                        </a>
                    </div>

                    <div class="space-y-2">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">People (HR)</div>
                        <a class="{{ request()->routeIs('admin.hr.dashboard') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('admin.hr.dashboard') }}">
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            HR Dashboard
                        </a>
                        <a class="{{ request()->routeIs('admin.hr.employees.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('admin.hr.employees.index') }}">
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Employees
                        </a>
                        <a class="{{ request()->routeIs('admin.hr.timesheets.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('admin.hr.timesheets.index') }}">
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Timesheets
                        </a>
                        <a class="{{ request()->routeIs('admin.hr.leave-types.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('admin.hr.leave-types.index') }}">
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Leave Types
                        </a>
                        <a class="{{ request()->routeIs('admin.hr.leave-requests.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('admin.hr.leave-requests.index') }}">
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Leave Requests
                        </a>
                        <a class="{{ request()->routeIs('admin.hr.payroll.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('admin.hr.payroll.index') }}">
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Payroll
                        </a>
                    </div>

                    <div class="space-y-2">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Support & Communication</div>
                        <a class="{{ request()->routeIs('admin.support-tickets.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('admin.support-tickets.index') }}">
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            <span>Support</span>
                            @if(($adminHeaderStats['tickets_waiting'] ?? 0) > 0)
                                <span class="ml-auto rounded-full bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-700">{{ $adminHeaderStats['tickets_waiting'] }}</span>
                            @endif
                        </a>
                    </div>

                    <div class="space-y-2">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Administration</div>
                        <a class="{{ request()->routeIs('admin.admins.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('admin.admins.index') }}">
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Admin Users
                        </a>
                        <a class="{{ request()->routeIs('admin.profile.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('admin.profile.edit') }}">
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Profile
                        </a>
                    </div>

                    <div class="space-y-2">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">System & Monitoring</div>
                        <a class="{{ request()->routeIs('admin.automation-status') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('admin.automation-status') }}">
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Automation Status
                        </a>
                        <a class="{{ $logMenuActive ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('admin.logs.activity') }}">
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Logs
                        </a>
                        @if($logMenuActive)
                            <div class="ml-6 space-y-1 text-xs text-slate-400">
                                <a href="{{ route('admin.logs.activity') }}" class="block {{ request()->routeIs('admin.logs.activity') ? 'text-teal-300' : 'hover:text-slate-200' }}">Activity</a>
                                <a href="{{ route('admin.logs.admin') }}" class="block {{ request()->routeIs('admin.logs.admin') ? 'text-teal-300' : 'hover:text-slate-200' }}">Admin</a>
                                <a href="{{ route('admin.logs.module') }}" class="block {{ request()->routeIs('admin.logs.module') ? 'text-teal-300' : 'hover:text-slate-200' }}">Module</a>
                                <a href="{{ route('admin.logs.email') }}" class="block {{ request()->routeIs('admin.logs.email') ? 'text-teal-300' : 'hover:text-slate-200' }}">Email</a>
                                <a href="{{ route('admin.logs.ticket-mail-import') }}" class="block {{ request()->routeIs('admin.logs.ticket-mail-import') ? 'text-teal-300' : 'hover:text-slate-200' }}">Ticket Mail Import</a>
                            </div>
                        @endif
                        <a class="{{ request()->routeIs('admin.settings.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('admin.settings.edit') }}">
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Settings
                        </a>
                    </div>
                @elseif($isEmployeeNav)
                    <div>
                        <a class="{{ request()->routeIs('employee.dashboard') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('employee.dashboard') }}">
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Dashboard
                        </a>
                    </div>
                    <div class="space-y-2">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">My Work</div>
                        <a class="{{ request()->routeIs('employee.timesheets.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('employee.timesheets.index') }}">
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Timesheets
                        </a>
                        <a class="{{ request()->routeIs('employee.leave-requests.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('employee.leave-requests.index') }}">
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Leave Requests
                        </a>
                    </div>
                    <div class="space-y-2">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Payroll</div>
                        <a class="{{ request()->routeIs('employee.payroll.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('employee.payroll.index') }}">
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Payroll
                        </a>
                    </div>
                @elseif($isSalesRepNav)
                    <div>
                        <a class="{{ request()->routeIs('rep.dashboard') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('rep.dashboard') }}">
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Sales Dashboard
                        </a>
                    </div>
                    <div class="space-y-2">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Earnings</div>
                        <a class="{{ request()->routeIs('rep.earnings.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('rep.earnings.index') }}">
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Earnings
                        </a>
                        <a class="{{ request()->routeIs('rep.payouts.*') ? 'nav-link nav-link-active' : 'nav-link' }}" href="{{ route('rep.payouts.index') }}">
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            Payouts
                        </a>
                    </div>
                @endif
            </nav>

            <div class="mt-auto rounded-2xl border border-white/10 bg-white/5 p-4 text-xs text-slate-300">
                Billing cycle runs daily via scheduler. Verify task setup for production.
            </div>
        </aside>

        <div class="flex-1">
            <header class="sticky top-0 z-20 border-b border-slate-200/70 bg-white/80 backdrop-blur">
                <div class="mx-auto flex max-w-6xl items-center justify-between gap-6 px-6 py-4">
                    <div>
                        <div class="section-label">Admin workspace</div>
                        <div class="text-lg font-semibold text-slate-900">@yield('page-title', 'Overview')</div>
                    </div>
                    @if(!empty($adminHeaderStats))
                        <div class="stats hidden flex-wrap items-center gap-3 text-xs text-slate-500 lg:flex">
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
                            <a href="{{ route('admin.support-tickets.index', ['status' => 'customer_reply']) }}" class="flex items-center gap-2">
                                <span class="stat">{{ $adminHeaderStats['tickets_waiting'] ?? 0 }}</span>
                                Ticket(s) Awaiting Reply
                            </a>
                        </div>
                    @endif
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
                            @if($isAdminNav)
                                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Main</div>
                                <a href="{{ route('admin.dashboard') }}" class="text-slate-700 hover:text-teal-600">Dashboard</a>

                                <div class="text-xs uppercase tracking-[0.2em] text-slate-400 pt-2">Sales & Customers</div>
                                <a href="{{ route('admin.customers.index') }}" class="text-slate-700 hover:text-teal-600">Customers</a>
                                <a href="{{ route('admin.orders.index') }}" class="text-slate-700 hover:text-teal-600">Orders</a>
                                <a href="{{ route('admin.affiliates.index') }}" class="text-slate-700 hover:text-teal-600">Affiliates</a>
                                <a href="{{ route('admin.requests.index') }}" class="text-slate-700 hover:text-teal-600">Requests</a>

                                <div class="text-xs uppercase tracking-[0.2em] text-slate-400 pt-2">Delivery & Services</div>
                                <a href="{{ route('admin.projects.index') }}" class="text-slate-700 hover:text-teal-600">Projects</a>
                                <a href="{{ route('admin.subscriptions.index') }}" class="text-slate-700 hover:text-teal-600">Subscriptions</a>
                                <a href="{{ route('admin.licenses.index') }}" class="text-slate-700 hover:text-teal-600">Licenses</a>

                                <div class="text-xs uppercase tracking-[0.2em] text-slate-400 pt-2">Billing & Finance</div>
                                <a href="{{ route('admin.invoices.index') }}" class="text-slate-700 hover:text-teal-600">Invoices</a>
                                <div class="grid gap-1 pl-3 text-xs text-slate-500">
                                    <a href="{{ route('admin.invoices.index') }}" class="hover:text-teal-600">All invoices</a>
                                    <a href="{{ route('admin.invoices.paid') }}" class="hover:text-teal-600">Paid</a>
                                    <a href="{{ route('admin.invoices.unpaid') }}" class="hover:text-teal-600">Unpaid</a>
                                    <a href="{{ route('admin.invoices.overdue') }}" class="hover:text-teal-600">Overdue</a>
                                    <a href="{{ route('admin.invoices.cancelled') }}" class="hover:text-teal-600">Cancelled</a>
                                    <a href="{{ route('admin.invoices.refunded') }}" class="hover:text-teal-600">Refunded</a>
                                </div>
                                <a href="{{ route('admin.payment-proofs.index') }}" class="flex items-center justify-between text-slate-700 hover:text-teal-600">
                                    <span>Manual Payments</span>
                                    @if(($adminHeaderStats['pending_manual_payments'] ?? 0) > 0)
                                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700">{{ $adminHeaderStats['pending_manual_payments'] }}</span>
                                    @endif
                                </a>
                                <a href="{{ route('admin.accounting.index') }}" class="text-slate-700 hover:text-teal-600">Accounting</a>
                                <a href="{{ route('admin.payment-gateways.index') }}" class="text-slate-700 hover:text-teal-600">Payment Gateways</a>
                                <a href="{{ route('admin.commission-payouts.index') }}" class="text-slate-700 hover:text-teal-600">Commission Payouts</a>

                                <div class="text-xs uppercase tracking-[0.2em] text-slate-400 pt-2">Products & Plans</div>
                                <a href="{{ route('admin.products.index') }}" class="text-slate-700 hover:text-teal-600">Products</a>
                                <a href="{{ route('admin.plans.index') }}" class="text-slate-700 hover:text-teal-600">Plans</a>

                                <div class="text-xs uppercase tracking-[0.2em] text-slate-400 pt-2">People (HR)</div>
                                <a href="{{ route('admin.hr.dashboard') }}" class="text-slate-700 hover:text-teal-600">HR Dashboard</a>
                                <a href="{{ route('admin.hr.employees.index') }}" class="text-slate-700 hover:text-teal-600">Employees</a>
                                <a href="{{ route('admin.hr.timesheets.index') }}" class="text-slate-700 hover:text-teal-600">Timesheets</a>
                                <a href="{{ route('admin.hr.leave-types.index') }}" class="text-slate-700 hover:text-teal-600">Leave Types</a>
                                <a href="{{ route('admin.hr.leave-requests.index') }}" class="text-slate-700 hover:text-teal-600">Leave Requests</a>
                                <a href="{{ route('admin.hr.payroll.index') }}" class="text-slate-700 hover:text-teal-600">Payroll</a>

                                <div class="text-xs uppercase tracking-[0.2em] text-slate-400 pt-2">Support & Communication</div>
                                <a href="{{ route('admin.support-tickets.index') }}" class="text-slate-700 hover:text-teal-600">Support</a>

                                <div class="text-xs uppercase tracking-[0.2em] text-slate-400 pt-2">Administration</div>
                                <a href="{{ route('admin.admins.index') }}" class="text-slate-700 hover:text-teal-600">Admin Users</a>
                                <a href="{{ route('admin.profile.edit') }}" class="text-slate-700 hover:text-teal-600">Profile</a>

                                <div class="text-xs uppercase tracking-[0.2em] text-slate-400 pt-2">System & Monitoring</div>
                                <a href="{{ route('admin.automation-status') }}" class="text-slate-700 hover:text-teal-600">Automation Status</a>
                                <a href="{{ route('admin.logs.activity') }}" class="text-slate-700 hover:text-teal-600">Activity Log</a>
                                <a href="{{ route('admin.logs.admin') }}" class="text-slate-700 hover:text-teal-600">Admin Log</a>
                                <a href="{{ route('admin.logs.module') }}" class="text-slate-700 hover:text-teal-600">Module Log</a>
                                <a href="{{ route('admin.logs.email') }}" class="text-slate-700 hover:text-teal-600">Email Message Log</a>
                                <a href="{{ route('admin.logs.ticket-mail-import') }}" class="text-slate-700 hover:text-teal-600">Ticket Mail Import Log</a>
                                <a href="{{ route('admin.settings.edit') }}" class="text-slate-700 hover:text-teal-600">Settings</a>
                            @elseif($isEmployeeNav)
                                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Main</div>
                                <a href="{{ route('employee.dashboard') }}" class="text-slate-700 hover:text-teal-600">Dashboard</a>

                                <div class="text-xs uppercase tracking-[0.2em] text-slate-400 pt-2">My Work</div>
                                <a href="{{ route('employee.timesheets.index') }}" class="text-slate-700 hover:text-teal-600">Timesheets</a>
                                <a href="{{ route('employee.leave-requests.index') }}" class="text-slate-700 hover:text-teal-600">Leave Requests</a>

                                <div class="text-xs uppercase tracking-[0.2em] text-slate-400 pt-2">Payroll</div>
                                <a href="{{ route('employee.payroll.index') }}" class="text-slate-700 hover:text-teal-600">Payroll</a>
                            @elseif($isSalesRepNav)
                                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Main</div>
                                <a href="{{ route('rep.dashboard') }}" class="text-slate-700 hover:text-teal-600">Sales Dashboard</a>

                                <div class="text-xs uppercase tracking-[0.2em] text-slate-400 pt-2">Earnings</div>
                                <a href="{{ route('rep.earnings.index') }}" class="text-slate-700 hover:text-teal-600">Earnings</a>
                                <a href="{{ route('rep.payouts.index') }}" class="text-slate-700 hover:text-teal-600">Payouts</a>
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
