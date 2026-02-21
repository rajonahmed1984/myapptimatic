@extends('layouts.admin')

@section('title', 'Admin Dashboard')
@section('page-title', 'Admin Overview')

@push('styles')
    <style>
        #system-overview-card .btn-period-chooser {
            border-radius: 0.5rem;
            border: 1px solid #d1d5db;
            background: #f8fafc;
            padding: 0.15rem;
            gap: 0.15rem;
        }

        #system-overview-card .btn-period-chooser .btn {
            border-radius: 0.35rem;
            padding: 0.3rem 0.75rem;
            font-size: 0.72rem;
            font-weight: 600;
            color: #64748b;
        }

        #system-overview-card .btn-period-chooser .btn.active {
            background: #ffffff;
            color: #0f172a;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
        }

        #system-overview-chart-wrap {
            background-image: linear-gradient(180deg, rgba(248, 250, 252, 0.95), rgba(241, 245, 249, 0.92));
        }

        #system-overview-tooltip {
            pointer-events: none;
            opacity: 0;
            transform: translateY(8px);
            transition: opacity 0.14s ease, transform 0.14s ease;
        }

        #system-overview-tooltip[data-visible="true"] {
            opacity: 1;
            transform: translateY(0);
        }

        #system-overview-card .legend-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            border-radius: 0.35rem;
            border: 1px solid #d1d5db;
            background: rgba(255, 255, 255, 0.75);
            padding: 0.2rem 0.55rem;
            color: #334155;
            font-weight: 600;
        }

        #system-overview-card .legend-swatch {
            height: 0.75rem;
            width: 1.4rem;
            border: 1px solid #cbd5e1;
            border-radius: 0.2rem;
            background: rgba(203, 213, 225, 0.35);
        }

        #system-overview-card .legend-swatch-new {
            border-color: #d1d5db;
            background: rgba(226, 232, 240, 0.7);
        }

        #system-overview-card .legend-swatch-active {
            border-color: #1d4ed8;
            background: rgba(59, 130, 246, 0.45);
        }

        #system-overview-card .legend-swatch-income {
            border-color: #16a34a;
            background: rgba(74, 222, 128, 0.45);
        }
    </style>
@endpush

@section('content')

    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4 stagger">
        <a href="{{ route('admin.customers.index') }}" class="card px-4 py-3 leading-tight transition hover:border-teal-300 hover:shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div class="section-label">Customers</div>
                <div class="text-xl font-semibold text-slate-900">{{ $customerCount }}</div>
            </div>
        </a>
        <a href="{{ route('admin.subscriptions.index') }}" class="card px-4 py-3 leading-tight transition hover:border-teal-300 hover:shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div class="section-label">Subscriptions</div>
                <div class="text-xl font-semibold text-slate-900">{{ $subscriptionCount }}</div>
            </div>
        </a>
        <a href="{{ route('admin.licenses.index') }}" class="card px-4 py-3 leading-tight transition hover:border-teal-300 hover:shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div class="section-label">Licenses</div>
                <div class="text-xl font-semibold text-slate-900">{{ $licenseCount }}</div>
            </div>
        </a>
        <a href="{{ route('admin.invoices.unpaid') }}" class="card px-4 py-3 leading-tight transition hover:border-teal-300 hover:shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div class="section-label">Unpaid Invoices</div>
                <div class="text-xl font-semibold text-blue-600">{{ $pendingInvoiceCount }}</div>
            </div>
        </a>
    </div>

    @php
        $businessPulse = $businessPulse ?? [
            'today_income' => 0,
            'income_30d' => 0,
            'previous_income_30d' => 0,
            'expense_30d' => 0,
            'net_30d' => 0,
            'income_growth_percent' => null,
            'pending_orders' => 0,
            'unpaid_invoices' => 0,
            'overdue_invoices' => 0,
            'overdue_share_percent' => 0,
            'open_tickets' => 0,
            'customer_reply_tickets' => 0,
            'support_load' => 0,
            'health_score' => 0,
            'health_label' => 'Unknown',
            'health_classes' => 'bg-slate-100 text-slate-700',
        ];
        $projectMaintenance = $projectMaintenance ?? ['projects_active' => 0, 'projects_on_hold' => 0, 'subscriptions_blocked' => 0, 'renewals_30d' => 0, 'projects_profitable' => 0, 'projects_loss' => 0];
        $hrStats = $hrStats ?? [
            'active_employees' => 0,
            'pending_timesheets' => 0,
            'approved_timesheets' => 0,
            'draft_payroll_periods' => 0,
            'finalized_payroll_periods' => 0,
            'payroll_items_to_pay' => 0,
        ];
        $billingAmounts = $billingAmounts ?? ['today' => 0, 'month' => 0, 'year' => 0, 'all_time' => 0];
        $currency = $currency ?? 'BDT';
        $systemOverview = $systemOverview ?? [
            'automation_last_run' => '--',
            'automation_cards' => ['status_badge' => ''],
        ];
        $periodMetrics = $periodMetrics ?? [
            'today' => ['new_orders' => 0, 'active_orders' => 0, 'income' => 0, 'hosting_income' => 0],
            'month' => ['new_orders' => 0, 'active_orders' => 0, 'income' => 0, 'hosting_income' => 0],
            'year' => ['new_orders' => 0, 'active_orders' => 0, 'income' => 0, 'hosting_income' => 0],
        ];
        $periodSeries = $periodSeries ?? [
            'today' => ['labels' => [], 'new_orders' => [], 'active_orders' => [], 'income' => []],
            'month' => ['labels' => [], 'new_orders' => [], 'active_orders' => [], 'income' => []],
            'year' => ['labels' => [], 'new_orders' => [], 'active_orders' => [], 'income' => []],
        ];
        $periodDefault = 'month';
        $defaultMetrics = $periodMetrics[$periodDefault] ?? ['new_orders' => 0, 'active_orders' => 0, 'income' => 0, 'hosting_income' => 0];
        $incomeGrowth = $businessPulse['income_growth_percent'];
        $incomeGrowthText = $incomeGrowth === null
            ? 'N/A'
            : (($incomeGrowth >= 0 ? '+' : '') . number_format($incomeGrowth, 1) . '%');
        $incomeGrowthClass = $incomeGrowth === null
            ? 'text-slate-500'
            : ($incomeGrowth >= 0 ? 'text-emerald-600' : 'text-rose-600');
    @endphp

    <div class="mt-6 card p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="section-label">Business Pulse</div>
                <div class="mt-1 text-sm text-slate-500">Quick view of business health, cashflow and operational pressure.</div>
            </div>
            <div class="flex items-center gap-2">
                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $businessPulse['health_classes'] }}">
                    {{ $businessPulse['health_label'] }}
                </span>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                    Score {{ (int) ($businessPulse['health_score'] ?? 0) }}/100
                </span>
            </div>
        </div>

        <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Net (30d)</div>
                <div class="mt-2 text-2xl font-semibold {{ ($businessPulse['net_30d'] ?? 0) >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                    {{ $currency }}{{ number_format($businessPulse['net_30d'] ?? 0, 2) }}
                </div>
                <div class="mt-1 text-xs {{ $incomeGrowthClass }}">
                    Income trend: {{ $incomeGrowthText }} vs previous 30d
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Receivable Pressure</div>
                <div class="mt-2 text-2xl font-semibold text-amber-600">
                    {{ number_format($businessPulse['overdue_share_percent'] ?? 0, 1) }}%
                </div>
                <div class="mt-1 text-xs text-slate-500">
                    Overdue {{ $businessPulse['overdue_invoices'] ?? 0 }} / Open {{ ($businessPulse['unpaid_invoices'] ?? 0) + ($businessPulse['overdue_invoices'] ?? 0) }}
                </div>
                <a href="{{ route('admin.invoices.overdue') }}" class="mt-2 inline-flex text-xs font-semibold text-teal-600 hover:text-teal-500">View overdue invoices</a>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Sales Pipeline</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">
                    {{ $businessPulse['pending_orders'] ?? 0 }}
                </div>
                <div class="mt-1 text-xs text-slate-500">Pending orders awaiting conversion.</div>
                <a href="{{ route('admin.orders.index') }}" class="mt-2 inline-flex text-xs font-semibold text-teal-600 hover:text-teal-500">Review orders</a>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Support Load</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">
                    {{ $businessPulse['support_load'] ?? 0 }}
                </div>
                <div class="mt-1 text-xs text-slate-500">
                    Open: {{ $businessPulse['open_tickets'] ?? 0 }}, Customer reply: {{ $businessPulse['customer_reply_tickets'] ?? 0 }}
                </div>
                <a href="{{ route('admin.support-tickets.index') }}" class="mt-2 inline-flex text-xs font-semibold text-teal-600 hover:text-teal-500">Open tickets</a>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <div class="card px-4 py-3 leading-tight">
            <a href="{{ route('admin.projects.index', ['status' => 'ongoing']) }}" class="flex items-center justify-between gap-3 transition hover:text-teal-600">
                <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Ongoing projects</div>
                <div class="text-xl font-semibold text-slate-900">{{ $projectMaintenance['projects_active'] }}</div>
            </a>
            <a href="{{ route('admin.projects.index', ['status' => 'hold']) }}" class="mt-1 flex items-center justify-between text-[11px] text-slate-500 transition hover:text-teal-600">
                <span>Hold</span>
                <span>{{ $projectMaintenance['projects_on_hold'] }}</span>
            </a>
        </div>
        <div class="card px-4 py-3 leading-tight">
            <a href="{{ route('admin.subscriptions.index') }}" class="flex items-center justify-between gap-3 transition hover:text-teal-600">
                <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Blocked services</div>
                <div class="text-xl font-semibold text-rose-600">{{ $projectMaintenance['subscriptions_blocked'] }}</div>
            </a>
            <a href="{{ route('admin.subscriptions.index') }}" class="mt-1 text-[11px] text-slate-500 transition hover:text-teal-600">Suspended subscriptions</a>
        </div>
        <div class="card px-4 py-3 leading-tight">
            <a href="{{ route('admin.subscriptions.index') }}" class="flex items-center justify-between gap-3 transition hover:text-teal-600">
                <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Renewals (30d)</div>
                <div class="text-xl font-semibold text-emerald-600">{{ $projectMaintenance['renewals_30d'] }}</div>
            </a>
            <a href="{{ route('admin.project-maintenances.index') }}" class="mt-1 text-[11px] text-slate-500 transition hover:text-teal-600">Upcoming maintenance invoices</a>
        </div>
        <div class="card px-4 py-3 leading-tight">
            <a href="{{ route('admin.projects.index') }}" class="flex items-center justify-between gap-3 transition hover:text-teal-600">
                <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Profitability</div>
                <div class="text-xl font-semibold text-emerald-600">{{ $projectMaintenance['projects_profitable'] }}</div>
            </a>
            <a href="{{ route('admin.projects.index') }}" class="mt-1 flex items-center justify-between text-[11px] text-rose-600 transition hover:text-rose-700">
                <span>Loss risk</span>
                <span>{{ $projectMaintenance['projects_loss'] }}</span>
            </a>
        </div>
    </div>

    <div class="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <div class="card px-4 py-3 leading-tight">
            <a href="{{ route('admin.hr.employees.index') }}" class="flex items-center justify-between gap-3 transition hover:text-teal-600">
                <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Active employees</div>
                <div class="text-xl font-semibold text-slate-900">{{ $hrStats['active_employees'] }}</div>
            </a>
            <a href="{{ route('admin.hr.employees.index') }}" class="mt-1 text-[11px] text-slate-500 transition hover:text-teal-600">Currently enabled profiles</a>
        </div>
        <div class="card px-4 py-3 leading-tight">
            <a href="{{ route('admin.hr.timesheets.index') }}" class="flex items-center justify-between gap-3 transition hover:text-teal-600">
                <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Work Logs (7d)</div>
                <div class="text-xl font-semibold text-amber-600">{{ $hrStats['pending_timesheets'] }}</div>
            </a>
            <a href="{{ route('admin.hr.timesheets.index') }}" class="mt-1 flex items-center justify-between text-[11px] text-slate-500 transition hover:text-teal-600">
                <span>Generated from work sessions</span>
                <span class="text-emerald-600">On-target: {{ $hrStats['approved_timesheets'] }}</span>
            </a>
        </div>
        <div class="card px-4 py-3 leading-tight">
            <a href="{{ route('admin.hr.payroll.index') }}" class="flex items-center justify-between gap-3 transition hover:text-teal-600">
                <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Payroll periods</div>
                <div class="text-xl font-semibold text-slate-900">{{ $hrStats['draft_payroll_periods'] }}</div>
            </a>
            <a href="{{ route('admin.hr.payroll.index') }}" class="mt-1 flex items-center justify-between text-[11px] text-slate-500 transition hover:text-teal-600">
                <span>Draft</span>
                <span class="text-emerald-600">Finalized: {{ $hrStats['finalized_payroll_periods'] }}</span>
            </a>
        </div>
        <div class="card px-4 py-3 leading-tight">
            <a href="{{ route('admin.hr.payroll.index') }}" class="flex items-center justify-between gap-3 transition hover:text-teal-600">
                <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Payroll to pay</div>
                <div class="text-xl font-semibold text-rose-600">{{ $hrStats['payroll_items_to_pay'] }}</div>
            </a>
            <a href="{{ route('admin.hr.payroll.index') }}" class="mt-1 text-[11px] text-slate-500 transition hover:text-teal-600">Pending disbursements</a>
        </div>
    </div>

    <div class="mt-8">
        <div class="card p-6" id="system-overview-card">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div class="section-label">System Overview</div>
                    <div class="mt-2 text-sm text-slate-500">Period activity snapshot</div>
                </div>
                <div class="btn-group btn-group-sm btn-period-chooser" role="group" aria-label="Period chooser">
                    <button type="button" class="btn btn-default" data-period="today">Today</button>
                    <button type="button" class="btn btn-default active" data-period="month">Last 30 Days</button>
                    <button type="button" class="btn btn-default" data-period="year">Last 1 Year</button>
                </div>
            </div>

            <div id="system-overview-chart-wrap" class="mt-5 rounded-2xl border border-slate-200 p-4">
                <div class="flex flex-wrap items-center justify-center gap-2 text-xs" id="system-overview-legend">
                    <span class="legend-chip"><span class="legend-swatch legend-swatch-new"></span>New Orders</span>
                    <span class="legend-chip"><span class="legend-swatch legend-swatch-active"></span>Activated Orders</span>
                    <span class="legend-chip"><span class="legend-swatch legend-swatch-income"></span>Income</span>
                </div>
                <div class="relative mt-4">
                    <svg viewBox="0 0 760 340" class="h-72 w-full" id="system-overview-graph" role="img" aria-label="System overview chart">
                        <defs>
                            <linearGradient id="systemOverviewNewArea" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#d1d5db" stop-opacity="0.48"></stop>
                                <stop offset="100%" stop-color="#f1f5f9" stop-opacity="0"></stop>
                            </linearGradient>
                            <linearGradient id="systemOverviewActiveArea" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#3b82f6" stop-opacity="0.3"></stop>
                                <stop offset="100%" stop-color="#dbeafe" stop-opacity="0"></stop>
                            </linearGradient>
                            <linearGradient id="systemOverviewIncomeArea" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#22c55e" stop-opacity="0.42"></stop>
                                <stop offset="100%" stop-color="#dcfce7" stop-opacity="0"></stop>
                            </linearGradient>
                            <linearGradient id="systemOverviewActiveBar" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#2563eb" stop-opacity="0.95"></stop>
                                <stop offset="100%" stop-color="#93c5fd" stop-opacity="0.82"></stop>
                            </linearGradient>
                        </defs>
                        <g id="system-overview-grid"></g>
                        <g id="system-overview-y-left"></g>
                        <g id="system-overview-y-right"></g>
                        <g id="system-overview-x-axis"></g>
                        <g id="system-overview-series-new"></g>
                        <g id="system-overview-series-active"></g>
                        <g id="system-overview-series-income"></g>
                        <g id="system-overview-hit"></g>
                    </svg>
                    <div id="system-overview-tooltip" class="absolute left-0 top-0 z-10 hidden rounded-xl bg-slate-900 px-3 py-2 text-xs text-white shadow-xl" aria-hidden="true">
                        <div class="text-[11px] font-semibold text-slate-100" data-system-overview-tooltip-label>--</div>
                        <div class="mt-1 flex items-center gap-1.5">
                            <span class="inline-flex h-2.5 w-2.5 rounded-sm bg-emerald-400"></span>
                            <span>Income:</span>
                            <span class="font-semibold" data-system-overview-tooltip-income>0</span>
                        </div>
                        <div class="mt-1 text-[11px] text-slate-300">
                            New: <span data-system-overview-tooltip-new-orders>0</span>
                            <span class="px-1 text-slate-500">|</span>
                            Active: <span data-system-overview-tooltip-active-orders>0</span>
                        </div>
                    </div>
                </div>
            </div>

            @php
                $avgIncome = 0;
                $orderCount = (int) ($defaultMetrics['new_orders'] ?? 0);
                if ($orderCount > 0) {
                    $avgIncome = ($defaultMetrics['income'] ?? 0) / $orderCount;
                }
            @endphp
            <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                    <div class="text-[11px] uppercase tracking-[0.18em] text-slate-500">New Orders</div>
                    <div class="mt-1 text-xl font-semibold text-slate-700" id="left-sidebar-new-orders">{{ (int) ($defaultMetrics['new_orders'] ?? 0) }}</div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                    <div class="text-[11px] uppercase tracking-[0.18em] text-slate-500">Activated Orders</div>
                    <div class="mt-1 text-xl font-semibold text-blue-600" id="left-sidebar-active-orders">{{ (int) ($defaultMetrics['active_orders'] ?? 0) }}</div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                    <div class="text-[11px] uppercase tracking-[0.18em] text-slate-500">Total Income</div>
                    <div class="mt-1 text-xl font-semibold text-emerald-600" id="right-sidebar-income">{{ $currency }}{{ number_format($defaultMetrics['income'] ?? 0, 2) }}</div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                    <div class="text-[11px] uppercase tracking-[0.18em] text-slate-500">Hosting Income</div>
                    <div class="mt-1 text-xl font-semibold text-emerald-600" id="right-sidebar-hosting-income">{{ $currency }}{{ number_format($defaultMetrics['hosting_income'] ?? 0, 2) }}</div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                    <div class="text-[11px] uppercase tracking-[0.18em] text-slate-500">Avg Per Order</div>
                    <div class="mt-1 text-xl font-semibold text-emerald-600" id="right-sidebar-avg-income">{{ $currency }}{{ number_format($avgIncome, 2) }}</div>
                </div>
            </div>

            <div id="system-period-metrics" data-period-default="{{ $periodDefault }}"
                 data-period-metrics='@json($periodMetrics)'
                 data-period-series='@json($periodSeries)'
                 data-currency="{{ $currency }}" style="display:none"></div>
        </div>
    </div>


    @php
        $clientActivity = $clientActivity ?? ['recentClients' => collect()];
        $recentClients = $clientActivity['recentClients'] ?? collect();
    @endphp
    <div class="mt-6 card p-6">
        <div class="flex items-center justify-between">
            <div>
                <a href="{{ route('admin.customers.index') }}" class="text-xs uppercase tracking-[0.25em] text-slate-400 hover:text-teal-600 transition">Client Activity</a>
                <div class="text-xl font-semibold text-slate-900">Last 30 clients login (all time)</div>
            </div>
            <a href="{{ route('admin.customers.index') }}" class="text-xs font-semibold text-teal-600 hover:text-teal-500">View customers</a>
        </div>
        <div class="mt-4 text-sm text-slate-700">
            @if($recentClients->isNotEmpty())
                <div class="overflow-x-auto">
                    <div class="max-h-[250px] overflow-y-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="sticky top-0 z-10 border-b border-slate-200 bg-white text-xs uppercase tracking-[0.2em] text-slate-500">
                                <tr>
                                    <th class="py-2 pr-4">User</th>
                                    <th class="py-2 pr-4">Last login</th>
                                    <th class="py-2 pr-4">IP</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($recentClients as $session)
                                    <tr>
                                        <td class="py-2 pr-4">
                                            @if(!empty($session['customer_id']))
                                                <a href="{{ route('admin.customers.show', $session['customer_id']) }}" class="hover:text-teal-600">
                                                    {{ $session['name'] ?? '--' }}
                                                </a>
                                            @else
                                                {{ $session['name'] ?? '--' }}
                                            @endif
                                        </td>
                                        <td class="py-2 pr-4">{{ $session['last_login'] ?? '--' }}</td>
                                        <td class="py-2 pr-4">{{ $session['ip'] ?? '--' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="text-slate-500">No login sessions to show.</div>
            @endif
        </div>
    </div>

    @php
        $buildAutomationSpark = function ($rawValue, string $style = 'peak'): array {
            $value = max(0, (float) $rawValue);
            $base = 30.0;
            $x = [0, 24, 48, 72, 96, 120];
            $y = array_fill(0, count($x), $base);

            if ($value > 0) {
                $scaled = min($value, 25.0);
                $amplitude = min(22.0, 4.0 + (log($scaled + 1.0, 2) * 5.0));

                if ($style === 'ramp') {
                    $y = [$base, $base, $base, $base, $base - ($amplitude * 0.45), $base - $amplitude];
                } elseif ($style === 'mid') {
                    $y = [$base, $base, $base - ($amplitude * 0.65), $base, $base, $base];
                } elseif ($style === 'wave') {
                    $y = [$base, $base - ($amplitude * 0.35), $base - $amplitude, $base - ($amplitude * 0.4), $base, $base];
                } else {
                    $y = [$base, $base - ($amplitude * 0.3), $base - ($amplitude * 0.78), $base, $base, $base];
                }
            }

            $linePoints = collect($x)
                ->map(fn ($xPos, $index) => number_format($xPos, 2, '.', '').','.number_format($y[$index], 2, '.', ''))
                ->implode(' ');

            return [
                'line' => $linePoints,
                'area' => '0,'.$base.' '.$linePoints.' 120,'.$base,
            ];
        };

        $automationSparkCards = [
            [
                'label' => 'Invoices Created',
                'value' => (int) ($automation['invoices_created'] ?? 0),
                'stroke' => '#10b981',
                'fill' => '#10b98122',
                'style' => 'wave',
            ],
            [
                'label' => 'Overdue Suspensions',
                'value' => (int) ($automation['overdue_suspensions'] ?? 0),
                'stroke' => '#f59e0b',
                'fill' => '#f59e0b22',
                'style' => 'mid',
            ],
            [
                'label' => 'Inactive Tickets Closed',
                'value' => (int) ($automation['tickets_closed'] ?? 0),
                'stroke' => '#0ea5e9',
                'fill' => '#0ea5e922',
                'style' => 'peak',
            ],
            [
                'label' => 'Overdue Reminders',
                'value' => (int) ($automation['overdue_reminders'] ?? 0),
                'stroke' => '#f43f5e',
                'fill' => '#f43f5e22',
                'style' => 'ramp',
            ],
        ];
    @endphp

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <div class="card p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="section-label">Billing status</div>
                    <div class="mt-1 text-sm text-slate-500">Revenue snapshots (including hosting income)</div>
                </div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">Live</span>
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-2">
                <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                    <div class="text-xs uppercase tracking-[0.2em] text-emerald-600">Today</div>
                    <div class="mt-2 text-2xl font-semibold text-emerald-600">{{ $currency }}{{ number_format($billingAmounts['today'] ?? 0, 2) }}</div>
                </div>
                <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                    <div class="text-xs uppercase tracking-[0.2em] text-amber-600">This Month</div>
                    <div class="mt-2 text-2xl font-semibold text-amber-500">{{ $currency }}{{ number_format($billingAmounts['month'] ?? 0, 2) }}</div>
                </div>
                <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                    <div class="text-xs uppercase tracking-[0.2em] text-rose-600">This Year</div>
                    <div class="mt-2 text-2xl font-semibold text-rose-500">{{ $currency }}{{ number_format($billingAmounts['year'] ?? 0, 2) }}</div>
                </div>
                <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-600">All Time</div>
                    <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $currency }}{{ number_format($billingAmounts['all_time'] ?? 0, 2) }}</div>
                </div>
            </div>
        </div>

        <div class="card p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="section-label">Automation Overview</div>
                    <div class="mt-1 text-sm text-slate-500">Last Automation Run: {{ $systemOverview['automation_last_run'] ?? '--' }}</div>
                    <a href="{{ route('admin.automation-status') }}" class="mt-1 inline-flex items-center text-xs font-semibold text-teal-600 hover:text-teal-500">
                        View automation status
                    </a>
                </div>
                <span class="rounded-full px-3 py-1 text-xs font-semibold bg-emerald-100 text-emerald-700">{{ $systemOverview['automation_cards']['status_badge'] ?? '' }}</span>
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-2">
                @foreach($automationSparkCards as $card)
                    @php($spark = $buildAutomationSpark($card['value'], $card['style']))
                    <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">{{ $card['label'] }}</div>
                        <div class="mt-2 flex items-center justify-between">
                            <div class="text-2xl font-semibold text-slate-900">{{ $card['value'] }}</div>
                            <svg viewBox="0 0 120 32" class="h-8 w-28">
                                <polygon fill="{{ $card['fill'] }}" points="{{ $spark['area'] }}"></polygon>
                                <polyline fill="none" stroke="{{ $card['stroke'] }}" stroke-width="2" stroke-linecap="square" points="{{ $spark['line'] }}"></polyline>
                            </svg>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const metricsEl = document.getElementById('system-period-metrics');
                const graph = document.getElementById('system-overview-graph');
                const tooltipEl = document.getElementById('system-overview-tooltip');
                const newOrdersEl = document.getElementById('left-sidebar-new-orders');
                const activeOrdersEl = document.getElementById('left-sidebar-active-orders');
                const incomeEl = document.getElementById('right-sidebar-income');
                const hostingIncomeEl = document.getElementById('right-sidebar-hosting-income');
                const avgIncomeEl = document.getElementById('right-sidebar-avg-income');
                const periodButtons = document.querySelectorAll('.btn-period-chooser [data-period]');
                const tooltipLabelEl = document.querySelector('[data-system-overview-tooltip-label]');
                const tooltipIncomeEl = document.querySelector('[data-system-overview-tooltip-income]');
                const tooltipNewEl = document.querySelector('[data-system-overview-tooltip-new-orders]');
                const tooltipActiveEl = document.querySelector('[data-system-overview-tooltip-active-orders]');

                if (!metricsEl || !graph) {
                    return;
                }

                const NS = 'http://www.w3.org/2000/svg';
                const periodMetrics = JSON.parse(metricsEl.dataset.periodMetrics || '{}');
                const periodSeries = JSON.parse(metricsEl.dataset.periodSeries || '{}');
                const currency = metricsEl.dataset.currency || '';
                const defaultPeriod = metricsEl.dataset.periodDefault || 'month';
                let chartWidth = 760;
                let chartHeight = 340;
                let plot = {
                    left: 64,
                    top: 36,
                    right: 696,
                    bottom: 282,
                    width: 632,
                    height: 246,
                };
                let activeCache = null;
                let currentPeriod = defaultPeriod;

                const formatMoney = (value) => {
                    const number = Number(value || 0);
                    return `${currency}${number.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                };

                const setActiveButton = (periodKey) => {
                    periodButtons.forEach((btn) => {
                        btn.classList.toggle('active', btn.dataset.period === periodKey);
                    });
                };

                const syncChartLayout = () => {
                    chartWidth = Math.max(760, Math.round(graph.clientWidth || 760));
                    chartHeight = Math.max(280, Math.round(graph.clientHeight || 340));
                    graph.setAttribute('viewBox', `0 0 ${chartWidth} ${chartHeight}`);

                    const sideMargin = Math.max(62, Math.min(90, Math.round(chartWidth * 0.06)));
                    const topMargin = 36;
                    const bottomMargin = 58;
                    plot = {
                        left: sideMargin,
                        top: topMargin,
                        right: chartWidth - sideMargin,
                        bottom: chartHeight - bottomMargin,
                    };
                    plot.width = Math.max(120, plot.right - plot.left);
                    plot.height = Math.max(120, plot.bottom - plot.top);
                };

                const svg = (tagName, attributes = {}) => {
                    const el = document.createElementNS(NS, tagName);
                    Object.entries(attributes).forEach(([key, value]) => {
                        el.setAttribute(key, String(value));
                    });
                    return el;
                };

                const toNumberArray = (values, size) => {
                    const source = Array.isArray(values) ? values : [];
                    return Array.from({ length: size }, (_, index) => Number(source[index] || 0));
                };

                const normalizeSeries = (rawSeries) => {
                    const labels = Array.isArray(rawSeries?.labels) ? rawSeries.labels : [];
                    const count = Math.max(
                        labels.length,
                        Array.isArray(rawSeries?.new_orders) ? rawSeries.new_orders.length : 0,
                        Array.isArray(rawSeries?.active_orders) ? rawSeries.active_orders.length : 0,
                        Array.isArray(rawSeries?.income) ? rawSeries.income.length : 0,
                        1
                    );

                    return {
                        labels: Array.from({ length: count }, (_, index) => labels[index] ?? String(index + 1)),
                        newOrders: toNumberArray(rawSeries?.new_orders, count),
                        activeOrders: toNumberArray(rawSeries?.active_orders, count),
                        income: toNumberArray(rawSeries?.income, count),
                    };
                };

                const calcOrderMax = (values) => {
                    const max = Math.max(...values, 0);
                    return max <= 0 ? 4 : Math.max(4, Math.ceil(max));
                };

                const calcIncomeMax = (values) => {
                    const max = Math.max(...values, 0);
                    if (max <= 0) {
                        return 100;
                    }
                    const roughStep = max / 5;
                    const magnitude = Math.pow(10, Math.floor(Math.log10(roughStep)));
                    const normalized = roughStep / magnitude;
                    let step = 1;
                    if (normalized > 5) {
                        step = 10;
                    } else if (normalized > 2) {
                        step = 5;
                    } else if (normalized > 1) {
                        step = 2;
                    }
                    return Math.max(10, Math.ceil(max / (step * magnitude)) * step * magnitude);
                };

                const xForIndex = (index, size) => {
                    if (size <= 1) {
                        return plot.left + (plot.width / 2);
                    }
                    return plot.left + (index / (size - 1)) * plot.width;
                };

                const yForValue = (value, max) => {
                    const safeMax = Math.max(1, Number(max || 1));
                    return plot.bottom - (Math.max(0, Number(value || 0)) / safeMax) * plot.height;
                };

                const linePath = (points) => {
                    if (!points.length) {
                        return '';
                    }
                    const head = `M ${points[0].x.toFixed(2)} ${points[0].y.toFixed(2)}`;
                    const body = points.slice(1).map((point) => `L ${point.x.toFixed(2)} ${point.y.toFixed(2)}`).join(' ');
                    return `${head} ${body}`.trim();
                };

                const areaPath = (points) => {
                    if (!points.length) {
                        return '';
                    }
                    const first = points[0];
                    const last = points[points.length - 1];
                    const body = points.map((point) => `L ${point.x.toFixed(2)} ${point.y.toFixed(2)}`).join(' ');
                    return `M ${first.x.toFixed(2)} ${plot.bottom.toFixed(2)} ${body} L ${last.x.toFixed(2)} ${plot.bottom.toFixed(2)} Z`;
                };

                const renderGrid = (labels, orderMax, incomeMax) => {
                    const gridGroup = graph.querySelector('#system-overview-grid');
                    const yLeftGroup = graph.querySelector('#system-overview-y-left');
                    const yRightGroup = graph.querySelector('#system-overview-y-right');
                    const xAxisGroup = graph.querySelector('#system-overview-x-axis');

                    if (!gridGroup || !yLeftGroup || !yRightGroup || !xAxisGroup) {
                        return;
                    }

                    gridGroup.innerHTML = '';
                    yLeftGroup.innerHTML = '';
                    yRightGroup.innerHTML = '';
                    xAxisGroup.innerHTML = '';

                    const horizontalTicks = 6;
                    for (let tick = 0; tick <= horizontalTicks; tick += 1) {
                        const ratio = tick / horizontalTicks;
                        const y = plot.top + ratio * plot.height;
                        gridGroup.appendChild(svg('line', {
                            x1: plot.left.toFixed(2),
                            y1: y.toFixed(2),
                            x2: plot.right.toFixed(2),
                            y2: y.toFixed(2),
                            stroke: '#cbd5e1',
                            'stroke-width': tick === horizontalTicks ? 1 : 0.7,
                        }));

                        const leftValue = Math.round(orderMax - ratio * orderMax);
                        const rightValue = Math.round(incomeMax - ratio * incomeMax);

                        const leftText = svg('text', {
                            x: (plot.left - 10).toFixed(2),
                            y: (y + 4).toFixed(2),
                            'text-anchor': 'end',
                            'font-size': '11',
                            fill: '#475569',
                        });
                        leftText.textContent = leftValue;
                        yLeftGroup.appendChild(leftText);

                        const rightText = svg('text', {
                            x: (plot.right + 10).toFixed(2),
                            y: (y + 4).toFixed(2),
                            'text-anchor': 'start',
                            'font-size': '11',
                            fill: '#475569',
                        });
                        rightText.textContent = rightValue;
                        yRightGroup.appendChild(rightText);
                    }

                    const step = labels.length > 18 ? Math.ceil(labels.length / 12) : 1;
                    labels.forEach((label, index) => {
                        const x = xForIndex(index, labels.length);
                        const showLabel = (index % step === 0) || index === labels.length - 1;
                        gridGroup.appendChild(svg('line', {
                            x1: x.toFixed(2),
                            y1: plot.top.toFixed(2),
                            x2: x.toFixed(2),
                            y2: plot.bottom.toFixed(2),
                            stroke: showLabel ? '#d1d5db' : '#e2e8f0',
                            'stroke-width': 0.6,
                            'stroke-dasharray': showLabel ? '3 3' : '2 5',
                        }));

                        if (!showLabel) {
                            return;
                        }

                        const text = svg('text', {
                            x: x.toFixed(2),
                            y: (plot.bottom + 18).toFixed(2),
                            'font-size': '10',
                            fill: '#64748b',
                            'text-anchor': 'end',
                        });
                        text.setAttribute('transform', `rotate(-45 ${x.toFixed(2)} ${(plot.bottom + 18).toFixed(2)})`);
                        text.textContent = label;
                        xAxisGroup.appendChild(text);
                    });

                    const leftTitle = svg('text', {
                        x: (plot.left - 10).toFixed(2),
                        y: (plot.top - 16).toFixed(2),
                        'text-anchor': 'end',
                        'font-size': '11',
                        fill: '#334155',
                        'font-weight': '600',
                    });
                    leftTitle.textContent = 'Orders';
                    yLeftGroup.appendChild(leftTitle);

                    const rightTitle = svg('text', {
                        x: (plot.right + 10).toFixed(2),
                        y: (plot.top - 16).toFixed(2),
                        'text-anchor': 'start',
                        'font-size': '11',
                        fill: '#334155',
                        'font-weight': '600',
                    });
                    rightTitle.textContent = 'Income';
                    yRightGroup.appendChild(rightTitle);
                };

                const renderSeries = (series) => {
                    const newGroup = graph.querySelector('#system-overview-series-new');
                    const activeGroup = graph.querySelector('#system-overview-series-active');
                    const incomeGroup = graph.querySelector('#system-overview-series-income');
                    const hitGroup = graph.querySelector('#system-overview-hit');

                    if (!newGroup || !activeGroup || !incomeGroup || !hitGroup) {
                        return;
                    }

                    newGroup.innerHTML = '';
                    activeGroup.innerHTML = '';
                    incomeGroup.innerHTML = '';
                    hitGroup.innerHTML = '';

                    const total = series.labels.length;
                    const orderMax = calcOrderMax([...series.newOrders, ...series.activeOrders]);
                    const incomeMax = calcIncomeMax(series.income);
                    renderGrid(series.labels, orderMax, incomeMax);

                    const newPoints = series.newOrders.map((value, index) => ({
                        x: xForIndex(index, total),
                        y: yForValue(value, orderMax),
                        value,
                    }));
                    const activePoints = series.activeOrders.map((value, index) => ({
                        x: xForIndex(index, total),
                        y: yForValue(value, orderMax),
                        value,
                    }));
                    const incomePoints = series.income.map((value, index) => ({
                        x: xForIndex(index, total),
                        y: yForValue(value, incomeMax),
                        value,
                    }));

                    if (newPoints.length) {
                        newGroup.appendChild(svg('path', {
                            d: areaPath(newPoints),
                            fill: 'url(#systemOverviewNewArea)',
                        }));
                        newGroup.appendChild(svg('path', {
                            d: linePath(newPoints),
                            fill: 'none',
                            stroke: '#cbd5e1',
                            'stroke-width': '2',
                            'stroke-linecap': 'round',
                            'stroke-linejoin': 'round',
                        }));
                    }

                    if (activePoints.length) {
                        activeGroup.appendChild(svg('path', {
                            d: areaPath(activePoints),
                            fill: 'url(#systemOverviewActiveArea)',
                        }));

                        const gap = total > 1 ? plot.width / (total - 1) : 20;
                        const barWidth = Math.max(6, Math.min(14, gap * 0.42));
                        activePoints.forEach((point) => {
                            const y = yForValue(point.value, orderMax);
                            activeGroup.appendChild(svg('rect', {
                                x: (point.x - (barWidth / 2)).toFixed(2),
                                y: y.toFixed(2),
                                width: barWidth.toFixed(2),
                                height: Math.max(0, plot.bottom - y).toFixed(2),
                                rx: '2',
                                fill: 'url(#systemOverviewActiveBar)',
                                opacity: '0.9',
                            }));
                        });

                        activeGroup.appendChild(svg('path', {
                            d: linePath(activePoints),
                            fill: 'none',
                            stroke: '#1d4ed8',
                            'stroke-width': '1.2',
                            'stroke-linecap': 'round',
                            'stroke-linejoin': 'round',
                            opacity: '0.72',
                        }));
                    }

                    if (incomePoints.length) {
                        incomeGroup.appendChild(svg('path', {
                            d: areaPath(incomePoints),
                            fill: 'url(#systemOverviewIncomeArea)',
                        }));
                        incomeGroup.appendChild(svg('path', {
                            d: linePath(incomePoints),
                            fill: 'none',
                            stroke: '#22c55e',
                            'stroke-width': '2.4',
                            'stroke-linecap': 'round',
                            'stroke-linejoin': 'round',
                        }));
                        incomePoints.forEach((point) => {
                            incomeGroup.appendChild(svg('circle', {
                                cx: point.x.toFixed(2),
                                cy: point.y.toFixed(2),
                                r: '2.2',
                                fill: '#f8fafc',
                                stroke: '#16a34a',
                                'stroke-width': '1.4',
                            }));
                        });
                    }

                    hitGroup.appendChild(svg('rect', {
                        x: plot.left.toFixed(2),
                        y: plot.top.toFixed(2),
                        width: plot.width.toFixed(2),
                        height: plot.height.toFixed(2),
                        fill: 'transparent',
                    }));

                    activeCache = {
                        ...series,
                        points: { newPoints, activePoints, incomePoints },
                    };
                };

                const hideTooltip = () => {
                    if (!tooltipEl) {
                        return;
                    }
                    tooltipEl.classList.add('hidden');
                    tooltipEl.setAttribute('data-visible', 'false');
                    tooltipEl.setAttribute('aria-hidden', 'true');
                };

                const showTooltip = (index) => {
                    if (!tooltipEl || !activeCache) {
                        return;
                    }

                    const total = activeCache.labels.length;
                    if (!total) {
                        hideTooltip();
                        return;
                    }

                    const safeIndex = Math.max(0, Math.min(index, total - 1));
                    if (tooltipLabelEl) {
                        tooltipLabelEl.textContent = activeCache.labels[safeIndex] ?? '--';
                    }
                    if (tooltipIncomeEl) {
                        tooltipIncomeEl.textContent = formatMoney(activeCache.income[safeIndex] || 0);
                    }
                    if (tooltipNewEl) {
                        tooltipNewEl.textContent = String(Number(activeCache.newOrders[safeIndex] || 0));
                    }
                    if (tooltipActiveEl) {
                        tooltipActiveEl.textContent = String(Number(activeCache.activeOrders[safeIndex] || 0));
                    }

                    const point = activeCache.points.incomePoints[safeIndex] || { x: plot.left, y: plot.bottom };
                    const xPx = (point.x / chartWidth) * graph.clientWidth;
                    const yPx = (point.y / chartHeight) * graph.clientHeight;

                    tooltipEl.classList.remove('hidden');
                    tooltipEl.setAttribute('data-visible', 'true');
                    tooltipEl.setAttribute('aria-hidden', 'false');

                    const width = tooltipEl.offsetWidth || 170;
                    const height = tooltipEl.offsetHeight || 76;
                    let left = xPx + 14;
                    let top = yPx - height - 14;

                    if (left + width > graph.clientWidth - 8) {
                        left = xPx - width - 14;
                    }
                    if (left < 4) {
                        left = 4;
                    }
                    if (top < 4) {
                        top = yPx + 14;
                    }
                    if (top + height > graph.clientHeight - 4) {
                        top = graph.clientHeight - height - 4;
                    }

                    tooltipEl.style.left = `${Math.round(left)}px`;
                    tooltipEl.style.top = `${Math.round(top)}px`;
                };

                const bindTooltip = () => {
                    const hitRect = graph.querySelector('#system-overview-hit rect');
                    if (!hitRect) {
                        return;
                    }

                    hitRect.addEventListener('mousemove', (event) => {
                        if (!activeCache) {
                            return;
                        }

                        const total = activeCache.labels.length;
                        if (total <= 0) {
                            return;
                        }

                        const ctm = graph.getScreenCTM();
                        if (!ctm) {
                            return;
                        }

                        const point = graph.createSVGPoint();
                        point.x = event.clientX;
                        point.y = event.clientY;
                        const local = point.matrixTransform(ctm.inverse());
                        const ratio = total <= 1 ? 0 : (local.x - plot.left) / plot.width;
                        const index = Math.round(Math.max(0, Math.min(1, ratio)) * (total - 1));
                        showTooltip(index);
                    });

                    hitRect.addEventListener('mouseenter', () => showTooltip(0));
                    hitRect.addEventListener('mouseleave', hideTooltip);
                };

                const updateMetrics = (periodKey) => {
                    currentPeriod = periodKey;
                    syncChartLayout();
                    const summary = periodMetrics[periodKey] || { new_orders: 0, active_orders: 0, income: 0, hosting_income: 0 };
                    const normalized = normalizeSeries(periodSeries[periodKey] || { labels: [], new_orders: [], active_orders: [], income: [] });

                    if (newOrdersEl) {
                        newOrdersEl.textContent = summary.new_orders ?? 0;
                    }
                    if (activeOrdersEl) {
                        activeOrdersEl.textContent = summary.active_orders ?? 0;
                    }
                    if (incomeEl) {
                        incomeEl.textContent = formatMoney(summary.income ?? 0);
                    }
                    if (hostingIncomeEl) {
                        hostingIncomeEl.textContent = formatMoney(summary.hosting_income ?? 0);
                    }
                    if (avgIncomeEl) {
                        const orderCount = Number(summary.new_orders || 0);
                        const avg = orderCount > 0 ? (Number(summary.income || 0) / orderCount) : 0;
                        avgIncomeEl.textContent = formatMoney(avg);
                    }

                    renderSeries(normalized);
                    bindTooltip();
                    hideTooltip();
                    setActiveButton(periodKey);
                };

                periodButtons.forEach((btn) => {
                    btn.addEventListener('click', () => {
                        updateMetrics(btn.dataset.period || defaultPeriod);
                    });
                });

                updateMetrics(defaultPeriod);
                window.addEventListener('resize', () => {
                    hideTooltip();
                    updateMetrics(currentPeriod);
                });
            });
        </script>
    @endpush
@endsection
