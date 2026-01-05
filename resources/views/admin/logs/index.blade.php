@extends('layouts.admin')

@section('title', $pageTitle)
@section('page-title', $pageTitle)

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">System Logs</div>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ $pageTitle }}</h1>
            <p class="mt-1 text-sm text-slate-500">Track recent events and notifications.</p>
        </div>
    </div>

    <div class="card p-6">
        <div class="flex flex-wrap gap-2 text-sm">
            @foreach($logTypes as $slug => $type)
                <a href="{{ route($type['route']) }}"
                   class="{{ $slug === $activeType ? 'rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-white' : 'rounded-full border border-slate-200 px-4 py-2 text-slate-600 hover:border-teal-300 hover:text-teal-600' }}">
                    {{ $type['label'] }}
                </a>
            @endforeach
        </div>
    </div>

    <div class="mt-6 card overflow-x-auto">
        @if($logs->isEmpty())
            <div class="px-6 py-8 text-sm text-slate-500">No log entries yet.</div>
        @else
            <table class="w-full min-w-[900px] text-left text-sm">
                <thead class="border-b border-slate-200 text-xs uppercase tracking-[0.25em] text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">User</th>
                        <th class="px-4 py-3">IP</th>
                        <th class="px-4 py-3">Level</th>
                        <th class="px-4 py-3">Message</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($logs as $log)
                        @php
                            $level = strtolower((string) $log->level);
                            $levelClasses = match ($level) {
                                'error' => 'bg-rose-100 text-rose-700',
                                'warning' => 'bg-amber-100 text-amber-700',
                                'info' => 'bg-blue-100 text-blue-700',
                                default => 'bg-slate-100 text-slate-600',
                            };
                        @endphp
                        <tr class="border-b border-slate-100">
                            <td class="px-4 py-3 text-slate-500">{{ $log->created_at?->format($globalDateFormat.' H:i') ?? '--' }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $log->user?->name ?? 'System' }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $log->ip_address ?? '--' }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $levelClasses }}">{{ strtoupper($level) }}</span>
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                <div class="font-semibold text-slate-800">{{ $log->message }}</div>
                                @if(!empty($log->context))
                                    <div class="mt-1 text-xs text-slate-500">{{ json_encode($log->context) }}</div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if($logs->hasPages())
                <div class="flex items-center justify-between px-4 py-3 text-sm text-slate-500">
                    <div>Showing {{ $logs->count() }} of {{ $logs->total() }}</div>
                    <div class="flex items-center gap-2">
                        @if($logs->previousPageUrl())
                            <a href="{{ $logs->previousPageUrl() }}" class="rounded-full border border-slate-200 px-3 py-1 text-slate-600 hover:border-teal-300 hover:text-teal-600">Previous</a>
                        @else
                            <span class="rounded-full border border-slate-100 px-3 py-1 text-slate-300">Previous</span>
                        @endif
                        @if($logs->nextPageUrl())
                            <a href="{{ $logs->nextPageUrl() }}" class="rounded-full border border-slate-200 px-3 py-1 text-slate-600 hover:border-teal-300 hover:text-teal-600">Next</a>
                        @else
                            <span class="rounded-full border border-slate-100 px-3 py-1 text-slate-300">Next</span>
                        @endif
                    </div>
                </div>
            @endif
        @endif
    </div>
@endsection
