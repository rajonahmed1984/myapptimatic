@extends('layouts.admin')

@section('title', 'Admin Dashboard')
@section('page-title', 'Admin Overview')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Admin overview</div>
            <div class="text-2xl font-semibold text-slate-900">Snapshot</div>
        </div>
    </div>

    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4 stagger">
        <div class="card p-6">
            <div class="section-label">Customers</div>
            <div class="mt-3 text-3xl font-semibold text-slate-900">{{ $customerCount }}</div>
        </div>
        <div class="card p-6">
            <div class="section-label">Subscriptions</div>
            <div class="mt-3 text-3xl font-semibold text-slate-900">{{ $subscriptionCount }}</div>
        </div>
        <div class="card p-6">
            <div class="section-label">Licenses</div>
            <div class="mt-3 text-3xl font-semibold text-slate-900">{{ $licenseCount }}</div>
        </div>
        <div class="card p-6">
            <div class="section-label">Unpaid Invoices</div>
            <div class="mt-3 text-3xl font-semibold text-blue-600">{{ $pendingInvoiceCount }}</div>
        </div>
    </div>

    @php
        $projectMaintenance = $projectMaintenance ?? ['projects_active' => 0, 'projects_on_hold' => 0, 'subscriptions_blocked' => 0, 'renewals_30d' => 0, 'projects_profitable' => 0, 'projects_loss' => 0];
        $hrStats = $hrStats ?? [
            'active_employees' => 0,
            'pending_timesheets' => 0,
            'approved_timesheets' => 0,
            'draft_payroll_periods' => 0,
            'finalized_payroll_periods' => 0,
            'payroll_items_to_pay' => 0,
        ];
        $systemOverview = $systemOverview ?? [
            'period_activity' => 'No recent data',
            'billing_status' => 'Healthy',
            'revenue_snapshot' => 0,
            'automation_last_run' => 'N/A',
            'sessions' => [],
        ];
        $systemOverview['sessions'] = $systemOverview['sessions'] ?? [];
        $systemOverview['revenue_cards'] = $systemOverview['revenue_cards'] ?? [
            'today' => 0,
            'month' => 0,
            'year' => 0,
            'all_time' => 0,
        ];
        $systemOverview['automation_cards'] = $systemOverview['automation_cards'] ?? [
            'invoices_created' => 0,
            'overdue_suspensions' => 0,
            'inactive_tickets_closed' => 0,
            'overdue_reminders' => 0,
            'status_badge' => 'Ok',
            'status_badge_color' => 'emerald',
        ];
    @endphp

    <div class="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <div class="card p-4">
            <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Active projects</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $projectMaintenance['projects_active'] }}</div>
            <div class="mt-1 text-xs text-slate-500">On hold: {{ $projectMaintenance['projects_on_hold'] }}</div>
        </div>
        <div class="card p-4">
            <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Blocked services</div>
            <div class="mt-2 text-2xl font-semibold text-rose-600">{{ $projectMaintenance['subscriptions_blocked'] }}</div>
            <div class="mt-1 text-xs text-slate-500">Suspended subscriptions</div>
        </div>
        <div class="card p-4">
            <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Renewals (30d)</div>
            <div class="mt-2 text-2xl font-semibold text-emerald-600">{{ $projectMaintenance['renewals_30d'] }}</div>
            <div class="mt-1 text-xs text-slate-500">Upcoming maintenance invoices</div>
        </div>
        <div class="card p-4">
            <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Profitability</div>
            <div class="mt-2 text-2xl font-semibold text-emerald-600">{{ $projectMaintenance['projects_profitable'] }}</div>
            <div class="mt-1 text-xs text-rose-600">Loss risk: {{ $projectMaintenance['projects_loss'] }}</div>
        </div>
    </div>

    <div class="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <div class="card p-4">
            <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Active employees</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $hrStats['active_employees'] }}</div>
            <div class="mt-1 text-xs text-slate-500">Currently enabled profiles</div>
        </div>
        <div class="card p-4">
            <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Timesheets</div>
            <div class="mt-2 text-2xl font-semibold text-amber-600">{{ $hrStats['pending_timesheets'] }}</div>
            <div class="mt-1 text-xs text-slate-500">Awaiting approval</div>
            <div class="mt-1 text-xs text-emerald-600">Approved: {{ $hrStats['approved_timesheets'] }}</div>
        </div>
        <div class="card p-4">
            <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Payroll periods</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $hrStats['draft_payroll_periods'] }}</div>
            <div class="mt-1 text-xs text-slate-500">Draft</div>
            <div class="mt-1 text-xs text-emerald-600">Finalized: {{ $hrStats['finalized_payroll_periods'] }}</div>
        </div>
        <div class="card p-4">
            <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Payroll to pay</div>
            <div class="mt-2 text-2xl font-semibold text-rose-600">{{ $hrStats['payroll_items_to_pay'] }}</div>
            <div class="mt-1 text-xs text-slate-500">Pending disbursements</div>
        </div>
    </div>


    <div class="mt-6 card p-6">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Client Activity</div>
                <div class="text-xl font-semibold text-slate-900">Sessions from the last logins</div>
            </div>
        </div>
        <div class="mt-4 text-sm text-slate-700">
            @if(!empty($systemOverview['sessions']))
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-slate-200 text-xs uppercase tracking-[0.2em] text-slate-500">
                            <tr>
                                <th class="py-2 pr-4">User</th>
                                <th class="py-2 pr-4">Role</th>
                                <th class="py-2 pr-4">Last login</th>
                                <th class="py-2 pr-4">IP</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($systemOverview['sessions'] as $session)
                                <tr>
                                    <td class="py-2 pr-4">{{ $session['user'] ?? '--' }}</td>
                                    <td class="py-2 pr-4">{{ $session['role'] ?? '--' }}</td>
                                    <td class="py-2 pr-4">{{ $session['last_login'] ?? '--' }}</td>
                                    <td class="py-2 pr-4">{{ $session['ip'] ?? '--' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-slate-500">No recent login sessions to show.</div>
            @endif
        </div>
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <div class="card p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="section-label">Billing status</div>
                    <div class="mt-1 text-sm text-slate-500">Revenue snapshots</div>
                </div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">Live</span>
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-2">
                <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                    <div class="text-xs uppercase tracking-[0.2em] text-emerald-600">Today</div>
                    <div class="mt-2 text-2xl font-semibold text-emerald-600">BDT{{ number_format($systemOverview['revenue_cards']['today'], 2) }}</div>
                </div>
                <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                    <div class="text-xs uppercase tracking-[0.2em] text-amber-600">This Month</div>
                    <div class="mt-2 text-2xl font-semibold text-amber-500">BDT{{ number_format($systemOverview['revenue_cards']['month'], 2) }}</div>
                </div>
                <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                    <div class="text-xs uppercase tracking-[0.2em] text-rose-600">This Year</div>
                    <div class="mt-2 text-2xl font-semibold text-rose-500">BDT{{ number_format($systemOverview['revenue_cards']['year'], 2) }}</div>
                </div>
                <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-600">All Time</div>
                    <div class="mt-2 text-2xl font-semibold text-slate-900">BDT{{ number_format($systemOverview['revenue_cards']['all_time'], 2) }}</div>
                </div>
            </div>
        </div>

        <div class="card p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="section-label">Automation Overview</div>
                    <div class="mt-1 text-sm text-slate-500">Last Automation Run: {{ $systemOverview['automation_last_run'] }}</div>
                    <a href="{{ route('admin.automation-status') }}" class="mt-1 inline-flex items-center text-xs font-semibold text-teal-600 hover:text-teal-500">
                        View automation status
                    </a>
                </div>
                <span class="rounded-full px-3 py-1 text-xs font-semibold bg-emerald-100 text-emerald-700">{{ $systemOverview['automation_cards']['status_badge'] }}</span>
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-2">
                <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Invoices Created</div>
                    <div class="mt-2 flex items-center justify-between">
                        <div class="text-2xl font-semibold text-slate-900">{{ $systemOverview['automation_cards']['invoices_created'] }}</div>
                        <svg viewBox="0 0 120 32" class="h-8 w-28"><polygon fill="#10b98122" points="0 31 120 31 120 31"></polygon><polyline fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="square" points="0,1 120,1 "></polyline></svg>
                    </div>
                </div>
                <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Overdue Suspensions</div>
                    <div class="mt-2 flex items-center justify-between">
                        <div class="text-2xl font-semibold text-slate-900">{{ $systemOverview['automation_cards']['overdue_suspensions'] }}</div>
                        <svg viewBox="0 0 120 32" class="h-8 w-28"><polygon fill="#f59e0b22" points="0 31 120 31 120 31"></polygon><polyline fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="square" points="0,31 120,31 "></polyline></svg>
                    </div>
                </div>
                <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Inactive Tickets Closed</div>
                    <div class="mt-2 flex items-center justify-between">
                        <div class="text-2xl font-semibold text-slate-900">{{ $systemOverview['automation_cards']['inactive_tickets_closed'] }}</div>
                        <svg viewBox="0 0 120 32" class="h-8 w-28"><polygon fill="#0ea5e922" points="0 31 120 31 120 31"></polygon><polyline fill="none" stroke="#0ea5e9" stroke-width="2" stroke-linecap="square" points="0,31 120,31 "></polyline></svg>
                    </div>
                </div>
                <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Overdue Reminders</div>
                    <div class="mt-2 flex items-center justify-between">
                        <div class="text-2xl font-semibold text-slate-900">{{ $systemOverview['automation_cards']['overdue_reminders'] }}</div>
                        <svg viewBox="0 0 120 32" class="h-8 w-28"><polygon fill="#f43f5e22" points="0 31 120 31 120 31"></polygon><polyline fill="none" stroke="#f43f5e" stroke-width="2" stroke-linecap="square" points="0,1 120,1 "></polyline></svg>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-8">
        <div class="card p-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <div class="section-label">System Overview</div>
                    <div class="mt-2 text-sm text-slate-500">Period activity snapshot</div>
                </div>
                <div class="rounded-full bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">Live</div>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-12">
                <div class="lg:col-span-2 flex flex-col justify-center">
                    <div class="rounded-2xl border border-slate-200 bg-gradient-to-br from-slate-50 to-slate-100 p-4">
                        <div class="text-xs font-semibold uppercase tracking-widest text-slate-600">Orders Amount</div>
                        <div class="mt-4 space-y-3">
                            <div class="rounded-xl bg-white p-3 shadow-sm">
                                <div class="text-xs text-slate-500">New Orders</div>
                                <div class="mt-1 text-xl font-bold text-slate-900" id="left-sidebar-new-orders">4</div>
                            </div>
                            <div class="rounded-xl bg-white p-3 shadow-sm">
                                <div class="text-xs text-slate-500">Active Orders</div>
                                <div class="mt-1 text-xl font-bold text-blue-600" id="left-sidebar-active-orders">4</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-8">
                    <div class="mt-2">
                        <svg viewBox="0 0 400 200" class="h-64 w-full" id="system-overview-graph">
                            <g id="system-overview-grid" stroke="#e2e8f0" stroke-width="0.6">
                                <line x1="0" y1="20" x2="400" y2="20"></line>
                                <line x1="0" y1="50" x2="400" y2="50"></line>
                                <line x1="0" y1="80" x2="400" y2="80"></line>
                                <line x1="0" y1="110" x2="400" y2="110"></line>
                                <line x1="0" y1="140" x2="400" y2="140"></line>
                                <line x1="0" y1="170" x2="400" y2="170"></line>
                            </g>
                            <g id="system-overview-areas">
                                <path d="M 0.00 200 L 0.00 200.00 L 13.79 200.00 L 27.59 200.00 L 41.38 200.00 L 55.17 200.00 L 68.97 200.00 L 82.76 200.00 L 96.55 200.00 L 110.34 200.00 L 124.14 200.00 L 137.93 200.00 L 151.72 200.00 L 165.52 200.00 L 179.31 200.00 L 193.10 200.00 L 206.90 200.00 L 220.69 200.00 L 234.48 200.00 L 248.28 200.00 L 262.07 200.00 L 275.86 200.00 L 289.66 200.00 L 303.45 200.00 L 317.24 200.00 L 331.03 120.00 L 344.83 200.00 L 358.62 40.00 L 372.41 120.00 L 386.21 200.00 L 400.00 200.00 L 400.00 200 Z" fill="url(#ordersGradient2)" fill-opacity="0.35"></path>
                                <path d="M 0.00 200 L 0.00 200.00 L 13.79 200.00 L 27.59 200.00 L 41.38 200.00 L 55.17 200.00 L 68.97 200.00 L 82.76 200.00 L 96.55 200.00 L 110.34 200.00 L 124.14 200.00 L 137.93 200.00 L 151.72 200.00 L 165.52 200.00 L 179.31 200.00 L 193.10 200.00 L 206.90 200.00 L 220.69 200.00 L 234.48 200.00 L 248.28 200.00 L 262.07 200.00 L 275.86 200.00 L 289.66 200.00 L 303.45 200.00 L 317.24 200.00 L 331.03 200.00 L 344.83 194.84 L 358.62 40.00 L 372.41 197.42 L 386.21 200.00 L 400.00 200.00 L 400.00 200 Z" fill="url(#incomeGradient2)" fill-opacity="0.35"></path>
                            </g>
                            <g id="system-overview-lines">
                                <polyline points="0.00,200.00 13.79,200.00 27.59,200.00 41.38,200.00 55.17,200.00 68.97,200.00 82.76,200.00 96.55,200.00 110.34,200.00 124.14,200.00 137.93,200.00 151.72,200.00 165.52,200.00 179.31,200.00 193.10,200.00 206.90,200.00 220.69,200.00 234.48,200.00 248.28,200.00 262.07,200.00 275.86,200.00 289.66,200.00 303.45,200.00 317.24,200.00 331.03,120.00 344.83,200.00 358.62,40.00 372.41,120.00 386.21,200.00 400.00,200.00" fill="none" stroke="url(#ordersGradient2)" stroke-width="2"></polyline>
                                <polyline points="0.00,200.00 13.79,200.00 27.59,200.00 41.38,200.00 55.17,200.00 68.97,200.00 82.76,200.00 96.55,200.00 110.34,200.00 124.14,200.00 137.93,200.00 151.72,200.00 165.52,200.00 179.31,200.00 193.10,200.00 206.90,200.00 220.69,200.00 234.48,200.00 248.28,200.00 262.07,200.00 275.86,200.00 289.66,200.00 303.45,200.00 317.24,200.00 331.03,200.00 344.83,194.84 358.62,40.00 372.41,197.42 386.21,200.00 400.00,200.00" fill="none" stroke="url(#incomeGradient2)" stroke-width="2"></polyline>
                            </g>
                            <g id="system-overview-bars">
                                <rect x="3.67" y="200.00" width="6.00" height="0.00" rx="2" fill="url(#activeGradient2)"></rect><rect x="17.00" y="200.00" width="6.00" height="0.00" rx="2" fill="url(#activeGradient2)"></rect><rect x="30.33" y="200.00" width="6.00" height="0.00" rx="2" fill="url(#activeGradient2)"></rect><rect x="43.67" y="200.00" width="6.00" height="0.00" rx="2" fill="url(#activeGradient2)"></rect><rect x="57.00" y="200.00" width="6.00" height="0.00" rx="2" fill="url(#activeGradient2)"></rect><rect x="70.33" y="200.00" width="6.00" height="0.00" rx="2" fill="url(#activeGradient2)"></rect><rect x="83.67" y="200.00" width="6.00" height="0.00" rx="2" fill="url(#activeGradient2)"></rect><rect x="97.00" y="200.00" width="6.00" height="0.00" rx="2" fill="url(#activeGradient2)"></rect><rect x="110.33" y="200.00" width="6.00" height="0.00" rx="2" fill="url(#activeGradient2)"></rect><rect x="123.67" y="200.00" width="6.00" height="0.00" rx="2" fill="url(#activeGradient2)"></rect><rect x="137.00" y="200.00" width="6.00" height="0.00" rx="2" fill="url(#activeGradient2)"></rect><rect x="150.33" y="200.00" width="6.00" height="0.00" rx="2" fill="url(#activeGradient2)"></rect><rect x="163.67" y="200.00" width="6.00" height="0.00" rx="2" fill="url(#activeGradient2)"></rect><rect x="177.00" y="200.00" width="6.00" height="0.00" rx="2" fill="url(#activeGradient2)"></rect><rect x="190.33" y="200.00" width="6.00" height="0.00" rx="2" fill="url(#activeGradient2)"></rect><rect x="203.67" y="200.00" width="6.00" height="0.00" rx="2" fill="url(#activeGradient2)"></rect><rect x="217.00" y="200.00" width="6.00" height="0.00" rx="2" fill="url(#activeGradient2)"></rect><rect x="230.33" y="200.00" width="6.00" height="0.00" rx="2" fill="url(#activeGradient2)"></rect><rect x="243.67" y="200.00" width="6.00" height="0.00" rx="2" fill="url(#activeGradient2)"></rect><rect x="257.00" y="200.00" width="6.00" height="0.00" rx="2" fill="url(#activeGradient2)"></rect><rect x="270.33" y="200.00" width="6.00" height="0.00" rx="2" fill="url(#activeGradient2)"></rect><rect x="283.67" y="200.00" width="6.00" height="0.00" rx="2" fill="url(#activeGradient2)"></rect><rect x="297.00" y="200.00" width="6.00" height="0.00" rx="2" fill="url(#activeGradient2)"></rect><rect x="310.33" y="200.00" width="6.00" height="0.00" rx="2" fill="url(#activeGradient2)"></rect><rect x="323.67" y="120.00" width="6.00" height="80.00" rx="2" fill="url(#activeGradient2)"></rect><rect x="337.00" y="200.00" width="6.00" height="0.00" rx="2" fill="url(#activeGradient2)"></rect><rect x="350.33" y="40.00" width="6.00" height="160.00" rx="2" fill="url(#activeGradient2)"></rect><rect x="363.67" y="120.00" width="6.00" height="80.00" rx="2" fill="url(#activeGradient2)"></rect><rect x="377.00" y="200.00" width="6.00" height="0.00" rx="2" fill="url(#activeGradient2)"></rect><rect x="390.33" y="200.00" width="6.00" height="0.00" rx="2" fill="url(#activeGradient2)"></rect>
                            </g>
                            <defs>
                                <linearGradient id="ordersGradient2" x1="0" y1="0" x2="1" y2="0">
                                    <stop offset="0%" stop-color="#cbd5e1"></stop>
                                    <stop offset="100%" stop-color="#94a3b8"></stop>
                                </linearGradient>
                                <linearGradient id="activeGradient2" x1="0" y1="0" x2="1" y2="0">
                                    <stop offset="0%" stop-color="#2563eb"></stop>
                                    <stop offset="100%" stop-color="#60a5fa"></stop>
                                </linearGradient>
                                <linearGradient id="incomeGradient2" x1="0" y1="0" x2="1" y2="0">
                                    <stop offset="0%" stop-color="#22c55e"></stop>
                                    <stop offset="100%" stop-color="#86efac"></stop>
                                </linearGradient>
                            </defs>
                        </svg>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-x-4 gap-y-2 text-[11px] text-slate-500" id="system-overview-axis">
                        <span>07 Dec</span><span>08 Dec</span><span>09 Dec</span><span>10 Dec</span><span>11 Dec</span><span>12 Dec</span><span>13 Dec</span><span>14 Dec</span><span>15 Dec</span><span>16 Dec</span><span>17 Dec</span><span>18 Dec</span><span>19 Dec</span><span>20 Dec</span><span>21 Dec</span><span>22 Dec</span><span>23 Dec</span><span>24 Dec</span><span>25 Dec</span><span>26 Dec</span><span>27 Dec</span><span>28 Dec</span><span>29 Dec</span><span>30 Dec</span><span>31 Dec</span><span>01 Jan</span><span>02 Jan</span><span>03 Jan</span><span>04 Jan</span><span>05 Jan</span>
                    </div>
                    <div class="mt-4 flex flex-wrap items-center gap-6 text-xs text-slate-600">
                        <span class="flex items-center gap-2"><span class="h-3 w-3 rounded-full bg-slate-400"></span><span class="font-medium">New Orders</span></span>
                        <span class="flex items-center gap-2"><span class="h-3 w-3 rounded-full bg-blue-500"></span><span class="font-medium">Active Orders</span></span>
                        <span class="flex items-center gap-2"><span class="h-3 w-3 rounded-full bg-emerald-500"></span><span class="font-medium">Income</span></span>
                    </div>
                </div>

                <div class="lg:col-span-2 flex flex-col justify-center">
                    <div class="rounded-2xl border border-slate-200 bg-gradient-to-br from-emerald-50 to-emerald-100 p-4">
                        <div class="text-xs font-semibold uppercase tracking-widest text-emerald-700">Income Breakdown</div>
                        <div class="mt-4 space-y-3">
                            <div class="rounded-xl bg-white p-3 shadow-sm">
                                <div class="text-xs text-slate-500">Total Income</div>
                                <div class="mt-1 text-lg font-bold text-emerald-600" id="right-sidebar-income">BDT3,145.16</div>
                            </div>
                            <div class="rounded-xl bg-white p-3 shadow-sm">
                                <div class="text-xs text-slate-500">Avg Per Order</div>
                                <div class="mt-1 text-lg font-bold text-emerald-600" id="right-sidebar-avg-income">BDT786.29</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-4 border-t border-slate-200 pt-6">
                <div class="section-label">Period Controls</div>
                <div class="btn-group btn-group-sm btn-period-chooser" role="group" aria-label="Period chooser">
                    <button type="button" class="btn btn-default" data-period="today">Today</button>
                    <button type="button" class="btn btn-default active" data-period="month">Last 30 Days</button>
                    <button type="button" class="btn btn-default" data-period="year">Last 1 Year</button>
                </div>
            </div>

            <div id="system-period-metrics" data-period-default="month"
                 data-period-metrics='{"today":{"new_orders":0,"active_orders":0,"income":0},"month":{"new_orders":4,"active_orders":4,"income":3145.16},"year":{"new_orders":4,"active_orders":4,"income":3145.16}}'
                 data-period-series='{"today":{"labels":["00","01","02","03","04","05","06","07","08","09","10","11","12","13","14","15","16","17","18","19","20","21","22","23"],"new_orders":[0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],"active_orders":[0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],"income":[0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0]},"month":{"labels":["07 Dec","08 Dec","09 Dec","10 Dec","11 Dec","12 Dec","13 Dec","14 Dec","15 Dec","16 Dec","17 Dec","18 Dec","19 Dec","20 Dec","21 Dec","22 Dec","23 Dec","24 Dec","25 Dec","26 Dec","27 Dec","28 Dec","29 Dec","30 Dec","31 Dec","01 Jan","02 Jan","03 Jan","04 Jan","05 Jan"],"new_orders":[0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1,0,2,1,0,0],"active_orders":[0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1,0,2,1,0,0],"income":[0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,96.77,3000,48.39,0,0]},"year":{"labels":["Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec","Jan"],"new_orders":[0,0,0,0,0,0,0,0,0,0,1,3],"active_orders":[0,0,0,0,0,0,0,0,0,0,1,3],"income":[0,0,0,0,0,0,0,0,0,0,0,3145.16]}}'
                 data-currency="BDT" style="display:none"></div>
        </div>
    </div>
@endsection
