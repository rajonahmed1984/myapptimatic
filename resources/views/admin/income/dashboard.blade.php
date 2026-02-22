@extends('layouts.admin')

@section('title', 'Income dashboard')
@section('page-title', 'Income Dashboard')

@section('content')
    @php
        $formatCurrency = function ($amount) use ($currencySymbol, $currencyCode) {
            $formatted = number_format((float) ($amount ?? 0), 2);
            return "{$currencySymbol}{$formatted}{$currencyCode}";
        };
    @endphp 

    <div class="card p-6">
        <div class="section-label">Trend overview</div>
        @php
            $trendLabelsSafe = $trendLabels ?? [];
            $trendValues = array_map('floatval', $trendTotals ?? []);
            $trendCount = count($trendValues);
            $maxValueRaw = $trendCount > 0 ? max($trendValues) : 0;
            $yStep = max(1, (int) ceil((max(1, $maxValueRaw) / 5) / 10) * 10);
            $yMax = max(10, $yStep * 5);

            $plotTop = 4.0;
            $plotBottom = 56.0;
            $plotHeight = $plotBottom - $plotTop;
            $toY = function (float $value) use ($plotBottom, $plotHeight, $yMax): float {
                $scaled = $yMax > 0 ? ($value / $yMax) : 0;
                return round($plotBottom - ($scaled * $plotHeight), 2);
            };

            $coords = [];
            if ($trendCount > 1) {
                foreach ($trendValues as $index => $value) {
                    $x = round(($index / ($trendCount - 1)) * 100, 2);
                    $coords[] = [
                        'x' => $x,
                        'y' => $toY($value),
                        'value' => $value,
                        'label' => $trendLabelsSafe[$index] ?? '',
                    ];
                }
            }

            $linePath = '';
            $areaPath = '';
            if (! empty($coords)) {
                $first = $coords[0];
                $linePath = "M {$first['x']} {$first['y']}";
                for ($i = 1; $i < count($coords); $i++) {
                    $prev = $coords[$i - 1];
                    $curr = $coords[$i];
                    $midX = round(($prev['x'] + $curr['x']) / 2, 2);
                    $linePath .= " Q {$midX} {$prev['y']} {$curr['x']} {$curr['y']}";
                }
                $areaPath = $linePath . " L {$coords[count($coords) - 1]['x']} {$plotBottom} L {$coords[0]['x']} {$plotBottom} Z";
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
            $currentWindowTotal = $window > 0 ? array_sum(array_slice($trendValues, -$window)) : 0.0;
            $previousWindowTotal = $window > 0 ? array_sum(array_slice($trendValues, -($window * 2), $window)) : 0.0;
            $incomeDeltaPercent = $previousWindowTotal > 0
                ? (($currentWindowTotal - $previousWindowTotal) / $previousWindowTotal) * 100
                : null;
            $manualSharePercent = $totalAmount > 0 ? ($manualTotal / $totalAmount) * 100 : 0;
            $systemSharePercent = $totalAmount > 0 ? ($systemTotal / $totalAmount) * 100 : 0;
            $creditSharePercent = $totalAmount > 0 ? (($creditSettlementTotal ?? 0) / $totalAmount) * 100 : 0;
        @endphp

        <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                @if($trendCount > 1)
                    <svg viewBox="-8 0 112 62" class="h-72 w-full">
                        <defs>
                            <linearGradient id="incomeTrendArea" x1="0" x2="0" y1="0" y2="1">
                                <stop offset="0%" stop-color="#22c55e" stop-opacity="0.45" />
                                <stop offset="100%" stop-color="#22c55e" stop-opacity="0.05" />
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
                                $labelX = $coords[$index]['x'] ?? null;
                            @endphp
                            @if($labelX !== null)
                                <line x1="{{ $labelX }}" y1="{{ $plotTop }}" x2="{{ $labelX }}" y2="{{ $plotBottom }}" stroke="#e2e8f0" stroke-width="0.2" />
                            @endif
                        @endforeach

                        <path d="{{ $areaPath }}" fill="url(#incomeTrendArea)" />
                        <path d="{{ $linePath }}" fill="none" stroke="#22c55e" stroke-width="0.8" stroke-linecap="round" />

                        @foreach($coords as $point)
                            <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="0.65" fill="#16a34a" stroke="#dcfce7" stroke-width="0.35" />
                        @endforeach

                        @foreach($labelIndexes as $index)
                            @php
                                $label = $trendLabelsSafe[$index] ?? '';
                                $formattedLabel = $label ? \Illuminate\Support\Carbon::parse($label)->format('d-m-Y') : '';
                            @endphp
                            @if($formattedLabel !== '' && isset($coords[$index]))
                                <text
                                    x="{{ $coords[$index]['x'] }}"
                                    y="59"
                                    transform="rotate(45 {{ $coords[$index]['x'] }} 59)"
                                    font-size="2"
                                    fill="#64748b"
                                >{{ $formattedLabel }}</text>
                            @endif
                        @endforeach
                    </svg>
                @else
                    <div class="text-sm text-slate-500">Not enough data to plot trends.</div>
                @endif
            </div>

            <div class="space-y-3">
                <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-3 py-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-slate-600 text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 17h18M7 13h2m4 0h4M5 9h14M4 5h16" />
                        </svg>
                    </div>
                    <div>
                        <div class="text-xs text-slate-500">Total Income</div>
                        <div class="text-2xl font-semibold text-slate-900">{{ $formatCurrency($totalAmount) }}</div>
                        <div class="text-xs text-emerald-600">
                            @if($incomeDeltaPercent !== null)
                                +{{ number_format($incomeDeltaPercent, 0) }}% vs previous {{ $window }} points
                            @else
                                No previous period data
                            @endif
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-3 py-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-slate-600 text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h8M8 12h8M8 17h5M5 4h14v16H5z" />
                        </svg>
                    </div>
                    <div>
                        <div class="text-xs text-slate-500">Manual Income</div>
                        <div class="text-2xl font-semibold text-slate-900">{{ $formatCurrency($manualTotal) }}</div>
                        <div class="text-xs text-emerald-600">{{ number_format($manualSharePercent, 0) }}% of total income</div>
                    </div>
                </div>

                <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-3 py-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-slate-600 text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h8M8 12h8M8 17h5M5 4h14v16H5z" />
                        </svg>
                    </div>
                    <div>
                        <div class="text-xs text-slate-500">System Income</div>
                        <div class="text-2xl font-semibold text-slate-900">{{ $formatCurrency($systemTotal) }}</div>
                        <div class="text-xs text-emerald-600">{{ number_format($systemSharePercent, 0) }}% of total income</div>
                    </div>
                </div>

                <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-3 py-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-slate-600 text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-10V4m0 14v2m8-8a8 8 0 11-16 0 8 8 0 0116 0z" />
                        </svg>
                    </div>
                    <div>
                        <div class="text-xs text-slate-500">Credit Settlement</div>
                        <div class="text-2xl font-semibold text-slate-900">{{ $formatCurrency($creditSettlementTotal ?? 0) }}</div>
                        <div class="text-xs text-emerald-600">{{ number_format($creditSharePercent, 0) }}% of total income</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6 card p-6">
        <div class="section-label">Filters</div>
        <form method="GET" action="{{ route('admin.income.dashboard') }}" class="mt-4 grid gap-3 text-sm md:grid-cols-4">
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
            <div class="mt-7">
                <button type="submit" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Apply</button>
                <a href="{{ route('admin.income.dashboard') }}" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Reset</a>
            </div>
            <div class="md:col-span-4">
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
                        <input type="checkbox" name="sources[]" value="system" class="h-4 w-4 rounded border-slate-300 text-emerald-600" @checked(in_array('system', $sourceSelections, true))>
                        System
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="sources[]" value="credit_settlement" class="h-4 w-4 rounded border-slate-300 text-emerald-600" @checked(in_array('credit_settlement', $sourceSelections, true))>
                        Credit Settlement
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="sources[]" value="carrothost" class="h-4 w-4 rounded border-slate-300 text-emerald-600" @checked(in_array('carrothost', $sourceSelections, true))>
                        CarrotHost (WHMCS)
                    </label>
                </div>
            </div>            
        </form>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_2fr]">
        <div class="card p-6">
            <div class="section-label">Category totals</div>
            <div class="mt-4 space-y-3 text-sm text-slate-600">
                @forelse($categoryTotals as $summary)
                    <div class="flex items-center justify-between">
                        <div>{{ $summary['name'] }}</div>
                        <div class="font-semibold text-slate-900">{{ $formatCurrency($summary['total']) }}</div>
                    </div>
                @empty
                    <div class="text-sm text-slate-500">No income entries yet.</div>
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
                <a href="{{ route('admin.income.dashboard', array_merge(request()->query(), ['ai' => 'refresh'])) }}" class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-white px-3 py-1 text-[11px] font-semibold text-emerald-700 shadow-sm transition hover:border-emerald-300 hover:text-emerald-800">
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

    @if(!empty($whmcsErrors))
        <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            <div class="font-semibold text-amber-900">WHMCS warnings</div>
            <ul class="mt-2 list-disc pl-5">
                @foreach($whmcsErrors as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

@endsection
