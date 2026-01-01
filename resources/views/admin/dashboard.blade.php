@extends('layouts.admin')

@section('title', 'Admin Dashboard')
@section('page-title', 'Admin Overview')

@section('content')
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
        $billingPaid = $paidInvoiceCount ?? 0;
        $billingUnpaid = $pendingInvoiceCount ?? 0;
        $billingOverdue = $overdueCount ?? 0;
        $billingTotal = max(1, $billingPaid + $billingUnpaid + $billingOverdue);
        $pct = function ($value) use ($billingTotal) {
            return round($value / $billingTotal * 100);
        };
        $currencySymbol = $currency ?? 'BDT';
        $money = function ($value) use ($currencySymbol) {
            return $currencySymbol . number_format((float) $value, 2);
        };
        $periodMetrics = $periodMetrics ?? [];
        $periodDefault = 'month';
        $periodDefaultMetrics = $periodMetrics[$periodDefault] ?? ['new_orders' => 0, 'active_orders' => 0, 'income' => 0];
        $periodSeries = $periodSeries ?? [];
        $periodGraphDefault = $periodSeries[$periodDefault] ?? ['labels' => [], 'new_orders' => [], 'active_orders' => [], 'income' => []];
        $graphLabels = $periodGraphDefault['labels'] ?? [];
    @endphp

    <div class="mt-8">
        <div class="card p-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <div class="section-label">System Overview</div>
                    <div class="mt-2 text-sm text-slate-500">Period activity snapshot</div>
                </div>
                <div class="rounded-full bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">Live</div>
            </div>

            <!-- Full width with sidebars -->
            <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-12">
                <!-- Left Sidebar: Orders Amount -->
                <div class="lg:col-span-2 flex flex-col justify-center">
                    <div class="rounded-2xl border border-slate-200 bg-gradient-to-br from-slate-50 to-slate-100 p-4">
                        <div class="text-xs font-semibold uppercase tracking-widest text-slate-600">Orders Amount</div>
                        <div class="mt-4 space-y-3">
                            <div class="rounded-xl bg-white p-3 shadow-sm">
                                <div class="text-xs text-slate-500">New Orders</div>
                                <div class="mt-1 text-xl font-bold text-slate-900" id="left-sidebar-new-orders">0</div>
                            </div>
                            <div class="rounded-xl bg-white p-3 shadow-sm">
                                <div class="text-xs text-slate-500">Active Orders</div>
                                <div class="mt-1 text-xl font-bold text-blue-600" id="left-sidebar-active-orders">0</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Center: Graph -->
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
                            <g id="system-overview-areas"></g>
                            <g id="system-overview-lines"></g>
                            <g id="system-overview-bars"></g>
                            <defs>
                                <linearGradient id="ordersGradient" x1="0" y1="0" x2="1" y2="0">
                                    <stop offset="0%" stop-color="#cbd5e1" />
                                    <stop offset="100%" stop-color="#94a3b8" />
                                </linearGradient>
                                <linearGradient id="activeGradient" x1="0" y1="0" x2="1" y2="0">
                                    <stop offset="0%" stop-color="#2563eb" />
                                    <stop offset="100%" stop-color="#60a5fa" />
                                </linearGradient>
                                <linearGradient id="incomeGradient" x1="0" y1="0" x2="1" y2="0">
                                    <stop offset="0%" stop-color="#22c55e" />
                                    <stop offset="100%" stop-color="#86efac" />
                                </linearGradient>
                            </defs>
                        </svg>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-x-4 gap-y-2 text-[11px] text-slate-500" id="system-overview-axis">
                        @foreach($graphLabels as $label)
                            <span>{{ $label }}</span>
                        @endforeach
                    </div>
                    <div class="mt-4 flex flex-wrap items-center gap-6 text-xs text-slate-600">
                        <span class="flex items-center gap-2"><span class="h-3 w-3 rounded-full bg-slate-400"></span><span class="font-medium">New Orders</span></span>
                        <span class="flex items-center gap-2"><span class="h-3 w-3 rounded-full bg-blue-500"></span><span class="font-medium">Active Orders</span></span>
                        <span class="flex items-center gap-2"><span class="h-3 w-3 rounded-full bg-emerald-500"></span><span class="font-medium">Income</span></span>
                    </div>
                </div>

                <!-- Right Sidebar: Income Breakdown -->
                <div class="lg:col-span-2 flex flex-col justify-center">
                    <div class="rounded-2xl border border-slate-200 bg-gradient-to-br from-emerald-50 to-emerald-100 p-4">
                        <div class="text-xs font-semibold uppercase tracking-widest text-emerald-700">Income Breakdown</div>
                        <div class="mt-4 space-y-3">
                            <div class="rounded-xl bg-white p-3 shadow-sm">
                                <div class="text-xs text-slate-500">Total Income</div>
                                <div class="mt-1 text-lg font-bold text-emerald-600" id="right-sidebar-income">{{ $currencySymbol }}0</div>
                            </div>
                            <div class="rounded-xl bg-white p-3 shadow-sm">
                                <div class="text-xs text-slate-500">Avg Per Order</div>
                                <div class="mt-1 text-lg font-bold text-emerald-600" id="right-sidebar-avg-income">{{ $currencySymbol }}0</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Period Controls -->
            <div class="mt-6 flex flex-wrap items-center justify-between gap-4 border-t border-slate-200 pt-6">
                <div class="section-label">Period Controls</div>
                <div class="btn-group btn-group-sm btn-period-chooser" role="group" aria-label="Period chooser">
                    <button type="button" class="btn btn-default" data-period="today">Today</button>
                    <button type="button" class="btn btn-default active" data-period="month">Last 30 Days</button>
                    <button type="button" class="btn btn-default" data-period="year">Last 1 Year</button>
                </div>
            </div>

            <div id="system-period-metrics"
                 data-period-default="{{ $periodDefault }}"
                 data-period-metrics='@json($periodMetrics)'
                 data-period-series='@json($periodSeries)'
                 data-currency="{{ $currencySymbol }}"
                 style="display:none"></div>
        </div>
    </div>

    @php
        $billingToday = $billingAmounts['today'] ?? 0;
        $billingMonth = $billingAmounts['month'] ?? 0;
        $billingYear = $billingAmounts['year'] ?? 0;
        $billingAll = $billingAmounts['all_time'] ?? 0;
    @endphp

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
                    <div class="mt-2 text-2xl font-semibold text-emerald-600">{{ $money($billingToday) }}</div>
                </div>
                <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                    <div class="text-xs uppercase tracking-[0.2em] text-amber-600">This Month</div>
                    <div class="mt-2 text-2xl font-semibold text-amber-500">{{ $money($billingMonth) }}</div>
                </div>
                <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                    <div class="text-xs uppercase tracking-[0.2em] text-rose-600">This Year</div>
                    <div class="mt-2 text-2xl font-semibold text-rose-500">{{ $money($billingYear) }}</div>
                </div>
                <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-600">All Time</div>
                    <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $money($billingAll) }}</div>
                </div>
            </div>
        </div>

        @php
            $automation = $automation ?? [];
            $automationRuns = $automationRuns ?? [0, 0, 0, 0, 0, 0, 0, 1];
            $automationMax = max($automationRuns) ?: 1;
            $automationMetrics = $automationMetrics ?? [
                ['label' => 'Invoices Created', 'value' => $automation['invoices_created'] ?? 1, 'color' => 'emerald', 'stroke' => '#10b981'],
                ['label' => 'Overdue Suspensions', 'value' => $automation['overdue_suspensions'] ?? 0, 'color' => 'amber', 'stroke' => '#f59e0b'],
                ['label' => 'Inactive Tickets Closed', 'value' => $automation['tickets_closed'] ?? 0, 'color' => 'sky', 'stroke' => '#0ea5e9'],
                ['label' => 'Overdue Reminders', 'value' => $automation['overdue_reminders'] ?? 0, 'color' => 'rose', 'stroke' => '#f43f5e'],
            ];
        @endphp

        @php
            use App\Support\StatusColorHelper;
            $billingLastRunAt = \App\Models\Setting::getValue('billing_last_run_at');
            $billingLastStatus = \App\Models\Setting::getValue('billing_last_status');
            $billingLastError = \App\Models\Setting::getValue('billing_last_error');
            
            $lastRunText = 'Never run';
            $statusToDisplay = 'pending';
            
            if ($billingLastRunAt) {
                $lastRun = \Carbon\Carbon::parse($billingLastRunAt);
                $now = \Carbon\Carbon::now();
                $diff = $lastRun->diffInSeconds($now);
                
                if ($diff < 60) {
                    $lastRunText = 'Just now';
                } elseif ($diff < 3600) {
                    $mins = ceil($diff / 60);
                    $lastRunText = $mins === 1 ? '1 minute ago' : $mins . ' minutes ago';
                } elseif ($diff < 86400) {
                    $hours = ceil($diff / 3600);
                    $lastRunText = $hours === 1 ? '1 hour ago' : $hours . ' hours ago';
                } else {
                    $days = ceil($diff / 86400);
                    $lastRunText = $days === 1 ? 'Yesterday' : $days . ' days ago';
                }
                
                if ($billingLastStatus === 'success') {
                    $statusToDisplay = 'success';
                } elseif ($billingLastStatus === 'running') {
                    $statusToDisplay = 'running';
                } elseif ($billingLastStatus === 'failed') {
                    $statusToDisplay = 'failed';
                }
            }
            $statusColors = StatusColorHelper::getStatusColors($statusToDisplay);
            $statusBadgeClass = "{$statusColors['bg']} {$statusColors['text']}";
            $statusLabel = match($statusToDisplay) {
                'success' => '✓ Success',
                'running' => '⟳ Running',
                'failed' => '✕ Failed',
                default => 'Pending',
            };
        @endphp

        <div class="card p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="section-label">Automation Overview</div>
                    <div class="mt-1 text-sm text-slate-500">Last Automation Run: {{ $lastRunText }}</div>
                    @if($billingLastStatus === 'failed' && $billingLastError)
                        <div class="mt-1 text-xs text-rose-600">Error: {{ substr($billingLastError, 0, 80) }}{{ strlen($billingLastError) > 80 ? '...' : '' }}</div>
                    @endif
                </div>
                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $statusBadgeClass }}">{{ $statusLabel }}</span>
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-2">
                @foreach($automationMetrics as $metric)
                    <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">{{ $metric['label'] }}</div>
                        <div class="mt-2 flex items-center justify-between">
                            <div class="text-2xl font-semibold text-slate-900">{{ $metric['value'] }}</div>
                            <svg viewBox="0 0 120 32" class="h-8 w-28">
                                <polygon fill="{{ $metric['stroke'] }}22" points="@foreach($automationRuns as $i => $point){{ $i * (120 / (count($automationRuns) - 1)) }} {{ 31 }} @endforeach 120 31"></polygon>
                                <polyline
                                    fill="none"
                                    stroke="{{ $metric['stroke'] }}"
                                    stroke-width="2"
                                    stroke-linecap="square"
                                    points="@foreach($automationRuns as $i => $point){{ $i * (120 / (count($automationRuns) - 1)) }},{{ 31 - ($point / $automationMax * 30) }} @endforeach"
                                ></polyline>
                            </svg>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <script>
        (() => {
            const container = document.getElementById('system-period-metrics');
            if (!container) {
                return;
            }

            const metrics = JSON.parse(container.dataset.periodMetrics || '{}');
            const seriesData = JSON.parse(container.dataset.periodSeries || '{}');
            const currency = container.dataset.currency || '';
            const formatter = new Intl.NumberFormat(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const buttons = document.querySelectorAll('.btn-period-chooser [data-period]');
            const graph = document.getElementById('system-overview-graph');
            const barsGroup = document.getElementById('system-overview-bars');
            const areasGroup = document.getElementById('system-overview-areas');
            const linesGroup = document.getElementById('system-overview-lines');
            const axis = document.getElementById('system-overview-axis');

            const renderChart = (data) => {
                if (!graph || !barsGroup || !areasGroup || !linesGroup) {
                    return;
                }

                const width = 400;
                const height = 200;
                const maxHeight = 160;
                const newOrders = Array.isArray(data.new_orders) ? data.new_orders : [];
                const activeOrders = Array.isArray(data.active_orders) ? data.active_orders : [];
                const income = Array.isArray(data.income) ? data.income : [];
                const count = Math.max(newOrders.length, activeOrders.length, income.length, 1);
                const xStep = count === 1 ? width : width / (count - 1);
                const ordersMax = Math.max(1, ...newOrders, ...activeOrders);
                const incomeMax = Math.max(1, ...income);
                const orderScale = maxHeight / ordersMax;
                const incomeScale = maxHeight / incomeMax;

                const pointsForSeries = (series, scale) => {
                    const points = [];
                    for (let i = 0; i < count; i++) {
                        const value = Number(series[i] ?? 0);
                        const x = count === 1 ? 0 : i * xStep;
                        const y = height - value * scale;
                        points.push({ x, y });
                    }
                    return points;
                };

                const areaPath = (points) => {
                    if (points.length === 0) {
                        return '';
                    }
                    const lastPoint = points[points.length - 1];
                    const firstPoint = points[0];
                    const line = points.map((point) => `L ${point.x.toFixed(2)} ${point.y.toFixed(2)}`).join(' ');
                    return `M ${firstPoint.x.toFixed(2)} ${height} ${line} L ${lastPoint.x.toFixed(2)} ${height} Z`;
                };

                const linePoints = (points) => points.map((point) => `${point.x.toFixed(2)},${point.y.toFixed(2)}`).join(' ');

                const newOrdersPoints = pointsForSeries(newOrders, orderScale);
                const incomePoints = pointsForSeries(income, incomeScale);

                areasGroup.innerHTML = '';
                linesGroup.innerHTML = '';
                barsGroup.innerHTML = '';

                if (newOrdersPoints.length) {
                    const area = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                    area.setAttribute('d', areaPath(newOrdersPoints));
                    area.setAttribute('fill', 'url(#ordersGradient)');
                    area.setAttribute('fill-opacity', '0.35');
                    areasGroup.appendChild(area);

                    const line = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
                    line.setAttribute('points', linePoints(newOrdersPoints));
                    line.setAttribute('fill', 'none');
                    line.setAttribute('stroke', 'url(#ordersGradient)');
                    line.setAttribute('stroke-width', '2');
                    linesGroup.appendChild(line);
                }

                if (incomePoints.length) {
                    const area = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                    area.setAttribute('d', areaPath(incomePoints));
                    area.setAttribute('fill', 'url(#incomeGradient)');
                    area.setAttribute('fill-opacity', '0.35');
                    areasGroup.appendChild(area);

                    const line = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
                    line.setAttribute('points', linePoints(incomePoints));
                    line.setAttribute('fill', 'none');
                    line.setAttribute('stroke', 'url(#incomeGradient)');
                    line.setAttribute('stroke-width', '2');
                    linesGroup.appendChild(line);
                }

                const groupWidth = width / count;
                const barWidth = Math.max(2, Math.min(groupWidth * 0.45, 8));
                for (let i = 0; i < count; i++) {
                    const value = Number(activeOrders[i] ?? 0);
                    const barHeight = value * orderScale;
                    const rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
                    rect.setAttribute('x', (i * groupWidth + (groupWidth - barWidth) / 2).toFixed(2));
                    rect.setAttribute('y', (height - barHeight).toFixed(2));
                    rect.setAttribute('width', barWidth.toFixed(2));
                    rect.setAttribute('height', barHeight.toFixed(2));
                    rect.setAttribute('rx', '2');
                    rect.setAttribute('fill', 'url(#activeGradient)');
                    barsGroup.appendChild(rect);
                }
            };

            const updateGraph = (period) => {
                const data = seriesData[period] || {};
                renderChart(data);

                if (!axis) {
                    return;
                }

                axis.innerHTML = '';
                (data.labels || []).forEach((label) => {
                    const span = document.createElement('span');
                    span.textContent = label;
                    axis.appendChild(span);
                });
            };

            const updateSidebars = (period) => {
                const data = seriesData[period] || { new_orders: [], active_orders: [], income: [] };
                
                // Left Sidebar - Orders Amount
                const totalNewOrders = Array.isArray(data.new_orders) 
                    ? data.new_orders.reduce((sum, val) => sum + Number(val || 0), 0)
                    : 0;
                const totalActiveOrders = Array.isArray(data.active_orders)
                    ? data.active_orders.reduce((sum, val) => sum + Number(val || 0), 0)
                    : 0;
                const newOrdersEl = document.getElementById('left-sidebar-new-orders');
                const activeOrdersEl = document.getElementById('left-sidebar-active-orders');
                if (newOrdersEl) newOrdersEl.textContent = totalNewOrders;
                if (activeOrdersEl) activeOrdersEl.textContent = totalActiveOrders;

                // Right Sidebar - Income Breakdown
                const totalIncome = Array.isArray(data.income)
                    ? data.income.reduce((sum, val) => sum + Number(val || 0), 0)
                    : 0;
                const avgIncome = totalNewOrders > 0 ? totalIncome / totalNewOrders : 0;
                const incomeEl = document.getElementById('right-sidebar-income');
                const avgIncomeEl = document.getElementById('right-sidebar-avg-income');
                if (incomeEl) incomeEl.textContent = `${currency}${formatter.format(totalIncome)}`;
                if (avgIncomeEl) avgIncomeEl.textContent = `${currency}${formatter.format(avgIncome)}`;
            };

            const update = (period) => {
                const data = metrics[period] || { new_orders: 0, active_orders: 0, income: 0 };
                container.querySelectorAll('[data-metric]').forEach((el) => {
                    const key = el.dataset.metric;
                    const value = data[key] ?? 0;
                    if (key === 'income') {
                        el.textContent = `${currency}${formatter.format(Number(value) || 0)}`;
                    } else {
                        el.textContent = Number(value) || 0;
                    }
                });
                updateGraph(period);
                updateSidebars(period);
            };

            buttons.forEach((btn) => {
                btn.addEventListener('click', () => {
                    buttons.forEach((item) => item.classList.remove('active'));
                    btn.classList.add('active');
                    update(btn.dataset.period);
                });
            });

            update(container.dataset.periodDefault || 'month');
        })();
    </script>
@endsection
