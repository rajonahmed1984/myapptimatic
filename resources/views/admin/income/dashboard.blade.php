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

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="card p-6">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Total Income</div>
            <div class="mt-2 text-2xl font-semibold text-emerald-600">{{ $formatCurrency($totalAmount) }}</div>
            <div class="mt-1 text-xs text-slate-500">Filtered range</div>
        </div>
        <div class="card p-6">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Manual Income</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $formatCurrency($manualTotal) }}</div>
        </div>
        <div class="card p-6">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">System Income</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $formatCurrency($systemTotal) }}</div>
        </div>
        <div class="card p-6">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Top Customers</div>
            <div class="mt-2 text-sm text-slate-600">
                @forelse($topCustomers->take(3) as $customer)
                    <div class="flex items-center justify-between">
                        <span class="truncate">{{ $customer['name'] }}</span>
                        <span class="font-semibold text-slate-900">{{ $formatCurrency($customer['total']) }}</span>
                    </div>
                @empty
                    <div class="text-xs text-slate-500">No customer income yet.</div>
                @endforelse
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
                </div>
            </div>            
        </form>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[2fr_2fr]">
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

    <div class="mt-6 card p-6">
        <div class="section-label">Trend overview</div>
        <div class="mt-4 rounded-2xl border border-slate-200 bg-white/80 p-4">
            @php
                $trendCount = count($trendLabels ?? []);
                $maxTrend = max([1, ...array_map('floatval', $trendTotals ?? [])]);
                $points = [];
                if ($trendCount > 1) {
                    foreach (($trendTotals ?? []) as $index => $value) {
                        $x = round(($index / ($trendCount - 1)) * 100, 2);
                        $y = round(100 - (($maxTrend > 0 ? ($value / $maxTrend) : 0) * 100), 2);
                        $points[] = "{$x},{$y}";
                    }
                }
            @endphp
            @if($trendCount > 1)
                <svg viewBox="0 0 100 100" class="h-40 w-full">
                    <defs>
                        <linearGradient id="incomeLineDash" x1="0" x2="1">
                            <stop offset="0%" stop-color="#34d399" />
                            <stop offset="100%" stop-color="#10b981" />
                        </linearGradient>
                    </defs>
                    <polyline fill="none" stroke="url(#incomeLineDash)" stroke-width="2" points="{{ implode(' ', $points) }}" />
                </svg>
                <div class="mt-2 flex flex-wrap items-center justify-between text-xs text-slate-500">
                    <span>Start: {{ $trendLabels[0] ?? '' }}</span>
                    <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>Income</span>
                    <span>End: {{ $trendLabels[$trendCount - 1] ?? '' }}</span>
                </div>
            @else
                <div class="text-sm text-slate-500">Not enough data to plot trends.</div>
            @endif
        </div>
    </div>
    
@endsection
