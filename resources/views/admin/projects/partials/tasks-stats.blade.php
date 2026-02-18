@php
    $summary = $summary ?? ['total' => 0, 'pending' => 0, 'in_progress' => 0, 'blocked' => 0, 'completed' => 0];
    $totalTasks = max(0, (int) ($summary['total'] ?? 0));
    $completedTasks = max(0, (int) ($summary['completed'] ?? 0));
    $completionPercent = $totalTasks > 0 ? (int) round(($completedTasks / $totalTasks) * 100) : 0;
    $statusFilter = $statusFilter ?? null;
    $buildStatusUrl = static fn (?string $status = null): string => route('admin.projects.tasks.index', array_filter([
        'project' => $project,
        'status' => $status,
    ], fn ($value) => $value !== null && $value !== ''));
@endphp

<div id="projectTaskStats" class="grid gap-3 md:grid-cols-5">
    <a href="{{ $buildStatusUrl() }}" class="rounded-2xl border px-4 py-3 transition {{ $statusFilter === null ? 'border-teal-300 bg-teal-50 ring-1 ring-teal-200' : 'border-slate-200 bg-white hover:border-teal-200' }}">
        <div class="text-[11px] uppercase tracking-[0.2em] text-slate-400">Total</div>
        <div class="mt-1 text-lg font-semibold text-slate-900">{{ $totalTasks }}</div>
    </a>
    <a href="{{ $buildStatusUrl('pending') }}" class="rounded-2xl border px-4 py-3 transition {{ $statusFilter === 'pending' ? 'border-amber-300 bg-amber-100 ring-1 ring-amber-200' : 'border-amber-200 bg-amber-50 hover:border-amber-300' }}">
        <div class="text-[11px] uppercase tracking-[0.2em] text-amber-600">Pending</div>
        <div class="mt-1 text-lg font-semibold text-amber-900">{{ (int) ($summary['pending'] ?? 0) }}</div>
    </a>
    <a href="{{ $buildStatusUrl('in_progress') }}" class="rounded-2xl border px-4 py-3 transition {{ $statusFilter === 'in_progress' ? 'border-sky-300 bg-sky-100 ring-1 ring-sky-200' : 'border-sky-200 bg-sky-50 hover:border-sky-300' }}">
        <div class="text-[11px] uppercase tracking-[0.2em] text-sky-600">In Progress</div>
        <div class="mt-1 text-lg font-semibold text-sky-900">{{ (int) ($summary['in_progress'] ?? 0) }}</div>
    </a>
    <a href="{{ $buildStatusUrl('blocked') }}" class="rounded-2xl border px-4 py-3 transition {{ $statusFilter === 'blocked' ? 'border-rose-300 bg-rose-100 ring-1 ring-rose-200' : 'border-rose-200 bg-rose-50 hover:border-rose-300' }}">
        <div class="text-[11px] uppercase tracking-[0.2em] text-rose-600">Blocked</div>
        <div class="mt-1 text-lg font-semibold text-rose-900">{{ (int) ($summary['blocked'] ?? 0) }}</div>
    </a>
    <a href="{{ $buildStatusUrl('completed') }}" class="rounded-2xl border px-4 py-3 transition {{ $statusFilter === 'completed' ? 'border-emerald-300 bg-emerald-100 ring-1 ring-emerald-200' : 'border-emerald-200 bg-emerald-50 hover:border-emerald-300' }}">
        <div class="text-[11px] uppercase tracking-[0.2em] text-emerald-600">Complete</div>
        <div class="mt-1 flex items-center justify-between gap-3">
            <span class="text-lg font-semibold text-emerald-900">{{ $completedTasks }}</span>
            <span class="text-xs font-semibold text-emerald-700">{{ $completionPercent }}%</span>
        </div>
    </a>
</div>
