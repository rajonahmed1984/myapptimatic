@extends('layouts.admin')

@section('title', 'Expense Dashboard')
@section('page-title', 'Expense Dashboard')

@section('content')
    @php
        $formatCurrency = function ($amount) use ($currencySymbol, $currencyCode) {
            $formatted = number_format((float) ($amount ?? 0), 2);
            return "{$currencySymbol}{$formatted}{$currencyCode}";
        };

        $trendLabelsSafe = $trendLabels ?? [];
        $trendExpenseValues = array_map('floatval', $trendExpenses ?? []);
        $trendIncomeValues = array_map('floatval', $trendIncome ?? []);
        $trendCount = count($trendExpenseValues);

        $maxValueRaw = 0.0;
        if (! empty($trendExpenseValues) || ! empty($trendIncomeValues)) {
            $maxValueRaw = max(array_merge([0.0], $trendExpenseValues, $trendIncomeValues));
        }

        $yStep = max(1, (int) ceil((max(1, $maxValueRaw) / 5) / 10) * 10);
        $yMax = max(10, $yStep * 5);

        $plotTop = 4.0;
        $plotBottom = 56.0;
        $plotHeight = $plotBottom - $plotTop;
        $toY = function (float $value) use ($plotBottom, $plotHeight, $yMax): float {
            $scaled = $yMax > 0 ? ($value / $yMax) : 0;
            return round($plotBottom - ($scaled * $plotHeight), 2);
        };

        $expenseCoords = [];
        $incomeCoords = [];
        if ($trendCount > 1) {
            for ($index = 0; $index < $trendCount; $index++) {
                $x = round(($index / ($trendCount - 1)) * 100, 2);
                $expenseCoords[] = [
                    'x' => $x,
                    'y' => $toY($trendExpenseValues[$index] ?? 0),
                    'value' => $trendExpenseValues[$index] ?? 0,
                    'label' => $trendLabelsSafe[$index] ?? '',
                ];
                $incomeCoords[] = [
                    'x' => $x,
                    'y' => $toY($trendIncomeValues[$index] ?? 0),
                    'value' => $trendIncomeValues[$index] ?? 0,
                    'label' => $trendLabelsSafe[$index] ?? '',
                ];
            }
        }

        $buildSmoothPath = function (array $coords): string {
            if (empty($coords)) {
                return '';
            }

            $first = $coords[0];
            $path = "M {$first['x']} {$first['y']}";
            for ($i = 1; $i < count($coords); $i++) {
                $prev = $coords[$i - 1];
                $curr = $coords[$i];
                $midX = round(($prev['x'] + $curr['x']) / 2, 2);
                $path .= " Q {$midX} {$prev['y']} {$curr['x']} {$curr['y']}";
            }

            return $path;
        };

        $expenseLinePath = $buildSmoothPath($expenseCoords);
        $incomeLinePath = $buildSmoothPath($incomeCoords);
        $expenseAreaPath = '';
        if (! empty($expenseCoords) && $expenseLinePath !== '') {
            $lastIndex = count($expenseCoords) - 1;
            $expenseAreaPath = $expenseLinePath . " L {$expenseCoords[$lastIndex]['x']} {$plotBottom} L {$expenseCoords[0]['x']} {$plotBottom} Z";
        }

        $ticks = [];
        for ($step = 0; $step <= 5; $step++) {
            $value = round($yMax - (($yMax / 5) * $step), 2);
            $ticks[] = [
                'value' => $value,
                'y' => $toY($value),
            ];
        }

        $labelStep = max(1, (int) ceil(max(1, $trendCount) / 9));
        $labelIndexes = [];
        for ($i = 0; $i < $trendCount; $i += $labelStep) {
            $labelIndexes[] = $i;
        }
        if ($trendCount > 1 && ! in_array($trendCount - 1, $labelIndexes, true)) {
            $labelIndexes[] = $trendCount - 1;
        }
        sort($labelIndexes);

        $window = min(30, $trendCount);
        $currentExpenseWindowTotal = $window > 0 ? array_sum(array_slice($trendExpenseValues, -$window)) : 0.0;
        $previousExpenseWindowTotal = $window > 0 ? array_sum(array_slice($trendExpenseValues, -($window * 2), $window)) : 0.0;
        $expenseDeltaPercent = $previousExpenseWindowTotal > 0
            ? (($currentExpenseWindowTotal - $previousExpenseWindowTotal) / $previousExpenseWindowTotal) * 100
            : null;

        $incomeCoveragePercent = $expenseTotal > 0 ? ($incomeReceived / $expenseTotal) * 100 : 0;
        $payoutSharePercent = $expenseTotal > 0 ? ($payoutExpenseTotal / $expenseTotal) * 100 : 0;
        $topCategory = collect($categoryTotals ?? [])->first();
    @endphp

    <div class="card p-6">
        <div class="section-label">Trend overview</div>
        <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                @if($trendCount > 1)
                    <svg viewBox="-8 0 112 62" class="h-72 w-full">
                        <defs>
                            <linearGradient id="expenseTrendArea" x1="0" x2="0" y1="0" y2="1">
                                <stop offset="0%" stop-color="#ef4444" stop-opacity="0.42" />
                                <stop offset="100%" stop-color="#ef4444" stop-opacity="0.06" />
                            </linearGradient>
                        </defs>

                        @foreach($ticks as $tick)
                            <line x1="0" y1="{{ $tick['y'] }}" x2="100" y2="{{ $tick['y'] }}" stroke="#cbd5e1" stroke-width="0.25" />
                            <text x="-1.5" y="{{ $tick['y'] + 0.9 }}" text-anchor="end" font-size="2.2" fill="#64748b">
                                {{ rtrim(rtrim(number_format((float) $tick['value'], 2, '.', ''), '0'), '.') }}
                            </text>
                        @endforeach

                        @foreach($labelIndexes as $index)
                            @php
                                $labelX = $expenseCoords[$index]['x'] ?? null;
                            @endphp
                            @if($labelX !== null)
                                <line x1="{{ $labelX }}" y1="{{ $plotTop }}" x2="{{ $labelX }}" y2="{{ $plotBottom }}" stroke="#e2e8f0" stroke-width="0.2" />
                            @endif
                        @endforeach

                        @if($expenseAreaPath !== '')
                            <path d="{{ $expenseAreaPath }}" fill="url(#expenseTrendArea)" />
                        @endif
                        @if($expenseLinePath !== '')
                            <path d="{{ $expenseLinePath }}" fill="none" stroke="#ef4444" stroke-width="0.8" stroke-linecap="round" />
                        @endif
                        @if($incomeLinePath !== '')
                            <path d="{{ $incomeLinePath }}" fill="none" stroke="#10b981" stroke-width="0.8" stroke-linecap="round" stroke-dasharray="1.7 1.2" />
                        @endif

                        @foreach($expenseCoords as $point)
                            <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="0.65" fill="#dc2626" stroke="#fee2e2" stroke-width="0.35" />
                        @endforeach

                        @foreach($incomeCoords as $point)
                            <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="0.45" fill="#059669" stroke="#d1fae5" stroke-width="0.3" />
                        @endforeach

                        @foreach($labelIndexes as $index)
                            @php
                                $label = $trendLabelsSafe[$index] ?? '';
                                $formattedLabel = '';
                                if ($label !== '') {
                                    try {
                                        $formattedLabel = strlen($label) <= 7
                                            ? \Illuminate\Support\Carbon::createFromFormat('Y-m', $label)->format('M y')
                                            : \Illuminate\Support\Carbon::parse($label)->format('d-m');
                                    } catch (\Throwable $e) {
                                        $formattedLabel = $label;
                                    }
                                }
                            @endphp
                            @if($formattedLabel !== '' && isset($expenseCoords[$index]))
                                <text
                                    x="{{ $expenseCoords[$index]['x'] }}"
                                    y="59"
                                    transform="rotate(45 {{ $expenseCoords[$index]['x'] }} 59)"
                                    font-size="2"
                                    fill="#64748b"
                                >{{ $formattedLabel }}</text>
                            @endif
                        @endforeach
                    </svg>
                    <div class="mt-3 flex flex-wrap items-center gap-4 text-xs text-slate-600">
                        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-rose-500"></span>Expenses</span>
                        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>Income received</span>
                    </div>
                @else
                    <div class="text-sm text-slate-500">Not enough data to plot trends.</div>
                @endif
            </div>

            <div class="space-y-3">
                <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-3 py-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-rose-600 text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a5 5 0 00-10 0v2m-2 0h14l-1 10H6L5 9z" />
                        </svg>
                    </div>
                    <div>
                        <div class="text-xs text-slate-500">Total Expenses</div>
                        <div class="text-2xl font-semibold text-slate-900">{{ $formatCurrency($expenseTotal) }}</div>
                        <div class="text-xs text-rose-600">
                            @if($expenseDeltaPercent !== null)
                                {{ $expenseDeltaPercent >= 0 ? '+' : '' }}{{ number_format($expenseDeltaPercent, 0) }}% vs previous {{ $window }} points
                            @else
                                No previous period data
                            @endif
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-3 py-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-emerald-600 text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-10V4m0 14v2m8-8a8 8 0 11-16 0 8 8 0 0116 0z" />
                        </svg>
                    </div>
                    <div>
                        <div class="text-xs text-slate-500">Income Received</div>
                        <div class="text-2xl font-semibold text-slate-900">{{ $formatCurrency($incomeReceived) }}</div>
                        <div class="text-xs text-emerald-600">{{ number_format($incomeCoveragePercent, 0) }}% coverage of expenses</div>
                    </div>
                </div>

                <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-3 py-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-amber-600 text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h8m-8 4h6M5 5h14v14H5V5z" />
                        </svg>
                    </div>
                    <div>
                        <div class="text-xs text-slate-500">Payout Expenses</div>
                        <div class="text-2xl font-semibold text-slate-900">{{ $formatCurrency($payoutExpenseTotal) }}</div>
                        <div class="text-xs text-amber-600">{{ number_format($payoutSharePercent, 0) }}% of total expenses</div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-xl border border-slate-200 bg-white px-3 py-3">
                        <div class="text-xs text-slate-500">Net Income</div>
                        <div class="mt-1 text-lg font-semibold {{ $netIncome < 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ $formatCurrency($netIncome) }}</div>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white px-3 py-3">
                        <div class="text-xs text-slate-500">Net Cashflow</div>
                        <div class="mt-1 text-lg font-semibold {{ $netCashflow < 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ $formatCurrency($netCashflow) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6 card p-6">
        <div class="section-label">Filters</div>
        <form method="GET" action="{{ route('admin.expenses.dashboard') }}" class="mt-4 grid gap-3 text-sm md:grid-cols-5">
            <div>
                <label class="text-xs text-slate-500">Start date</label>
                <input type="date" name="start_date" value="{{ $filters['start_date'] }}" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs text-slate-500">End date</label>
                <input type="date" name="end_date" value="{{ $filters['end_date'] }}" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs text-slate-500">Category</label>
                <select name="category_id" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                    <option value="">All</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) $filters['category_id'] === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs text-slate-500">Employee</label>
                <select name="person" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                    <option value="">All</option>
                    @foreach($peopleOptions as $option)
                        <option value="{{ $option['key'] }}" @selected((string) $filters['person'] === (string) $option['key'])>{{ $option['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mt-7 md:col-span-5 flex flex-wrap items-center gap-3">
                <button type="submit" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Apply</button>
                <a href="{{ route('admin.expenses.dashboard') }}" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Reset</a>
            </div>
            <div class="md:col-span-5">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Sources</div>
                <div class="mt-2 flex flex-wrap gap-4 text-xs text-slate-600">
                    @php
                        $sourceSelections = $filters['sources'] ?? [];
                    @endphp
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="sources[]" value="manual" class="h-4 w-4 rounded border-slate-300 text-emerald-600" @checked(in_array('manual', $sourceSelections, true))>
                        Manual
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="sources[]" value="salary" class="h-4 w-4 rounded border-slate-300 text-emerald-600" @checked(in_array('salary', $sourceSelections, true))>
                        Salaries
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="sources[]" value="contract_payout" class="h-4 w-4 rounded border-slate-300 text-emerald-600" @checked(in_array('contract_payout', $sourceSelections, true))>
                        Contract Payouts
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="sources[]" value="sales_payout" class="h-4 w-4 rounded border-slate-300 text-emerald-600" @checked(in_array('sales_payout', $sourceSelections, true))>
                        Sales Rep Payouts
                    </label>
                </div>
            </div>
        </form>
    </div>

    <div class="mt-6 grid gap-4 md:grid-cols-3">
        <div class="card px-4 py-3">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">This month expenses</div>
            <div class="mt-2 text-2xl font-semibold text-rose-600">{{ $formatCurrency($monthlyTotal) }}</div>
        </div>
        <div class="card px-4 py-3">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">This year expenses</div>
            <div class="mt-2 text-2xl font-semibold text-rose-600">{{ $formatCurrency($yearlyTotal) }}</div>
        </div>
        <div class="card px-4 py-3">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Top category</div>
            <div class="mt-2 text-base font-semibold text-slate-900">{{ $topCategory['name'] ?? 'No data' }}</div>
            <div class="mt-1 text-sm text-slate-500">{{ isset($topCategory['total']) ? $formatCurrency($topCategory['total']) : '-' }}</div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_2fr]">
        <div class="card p-6">
            <div class="section-label">Expense by category</div>
            <div class="mt-4 space-y-3 text-sm text-slate-600">
                @forelse($categoryTotals as $summary)
                    <div class="flex items-center justify-between">
                        <div>{{ $summary['name'] }}</div>
                        <div class="font-semibold text-slate-900">{{ $formatCurrency($summary['total']) }}</div>
                    </div>
                @empty
                    <div class="text-sm text-slate-500">No expenses found.</div>
                @endforelse
            </div>
        </div>

        <div class="card relative overflow-hidden p-6">
            <div class="pointer-events-none absolute -right-20 -top-24 h-44 w-44 rounded-full bg-emerald-100/70 blur-2xl"></div>
            <div class="pointer-events-none absolute -bottom-24 -left-16 h-44 w-44 rounded-full bg-teal-100/70 blur-2xl"></div>
            <div class="relative flex items-start justify-between gap-3">
                <div class="flex items-center gap-2">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-600">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M12 2l1.7 3.7L18 7.4l-3 3 0.8 4.2L12 12.8 8.2 14.6 9 10.4l-3-3 4.3-1.7L12 2z"/>
                        </svg>
                    </span>
                    <div>
                        <div class="section-label">Google AI Summary</div>
                        <div class="mt-1 text-[11px] text-slate-500">Quick signals for this period</div>
                    </div>
                </div>
                <a href="{{ route('admin.expenses.dashboard', array_merge(request()->query(), ['ai' => 'refresh'])) }}" class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-white px-3 py-1 text-[11px] font-semibold text-emerald-700 shadow-sm transition hover:border-emerald-300 hover:text-emerald-800">
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                    Refresh AI
                </a>
            </div>
            <div class="relative mt-4 rounded-2xl bg-gradient-to-br from-emerald-200/60 via-teal-200/40 to-sky-200/40 p-[1px]">
                <div class="rounded-[15px] border border-white/60 bg-white/80 p-4 text-[13px] text-slate-600 leading-relaxed">
                    @if(!empty($aiSummary))
                        {!! nl2br(e($aiSummary)) !!}
                    @elseif(!empty($aiError))
                        <div class="text-xs text-slate-500">AI summary unavailable: {{ $aiError }}</div>
                    @else
                        <div class="text-xs text-slate-500">AI summary is not available yet.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_1fr]">
        <div class="card p-6">
            <div class="section-label">Expense by employee</div>
            <div class="mt-4 space-y-3 text-sm text-slate-600">
                @forelse($employeeTotals as $summary)
                    <div class="flex items-center justify-between">
                        <div>{{ $summary['label'] }}</div>
                        <div class="font-semibold text-slate-900">{{ $formatCurrency($summary['total']) }}</div>
                    </div>
                @empty
                    <div class="text-sm text-slate-500">No employee payouts in this range.</div>
                @endforelse
            </div>
        </div>
        <div class="card p-6">
            <div class="section-label">Expense by sales representatives</div>
            <div class="mt-4 space-y-3 text-sm text-slate-600">
                @forelse($salesRepTotals as $summary)
                    <div class="flex items-center justify-between">
                        <div>{{ $summary['label'] }}</div>
                        <div class="font-semibold text-slate-900">{{ $formatCurrency($summary['total']) }}</div>
                    </div>
                @empty
                    <div class="text-sm text-slate-500">No sales rep payouts in this range.</div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
