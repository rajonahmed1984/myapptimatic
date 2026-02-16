@php
    $summary = $summary ?? ['total' => 0, 'pending' => 0, 'in_progress' => 0, 'blocked' => 0, 'completed' => 0];
    $totalTasks = max(0, (int) ($summary['total'] ?? 0));
    $completedTasks = max(0, (int) ($summary['completed'] ?? 0));
    $completionPercent = $totalTasks > 0 ? (int) round(($completedTasks / $totalTasks) * 100) : 0;
@endphp

<div id="projectTaskStats" class="grid gap-3 md:grid-cols-5">
    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
        <div class="text-[11px] uppercase tracking-[0.2em] text-slate-400">Total</div>
        <div class="mt-1 text-lg font-semibold text-slate-900">{{ $totalTasks }}</div>
    </div>
    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">
        <div class="text-[11px] uppercase tracking-[0.2em] text-amber-600">Pending</div>
        <div class="mt-1 text-lg font-semibold text-amber-900">{{ (int) ($summary['pending'] ?? 0) }}</div>
    </div>
    <div class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3">
        <div class="text-[11px] uppercase tracking-[0.2em] text-sky-600">In Progress</div>
        <div class="mt-1 text-lg font-semibold text-sky-900">{{ (int) ($summary['in_progress'] ?? 0) }}</div>
    </div>
    <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3">
        <div class="text-[11px] uppercase tracking-[0.2em] text-rose-600">Blocked</div>
        <div class="mt-1 text-lg font-semibold text-rose-900">{{ (int) ($summary['blocked'] ?? 0) }}</div>
    </div>
    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3">
        <div class="text-[11px] uppercase tracking-[0.2em] text-emerald-600">Complete</div>
        <div class="mt-1 flex items-center justify-between gap-3">
            <span class="text-lg font-semibold text-emerald-900">{{ $completedTasks }}</span>
            <span class="text-xs font-semibold text-emerald-700">{{ $completionPercent }}%</span>
        </div>
    </div>
</div>
