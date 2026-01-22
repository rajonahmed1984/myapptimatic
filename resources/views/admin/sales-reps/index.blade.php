@extends('layouts.admin')

@section('title', 'Sales Representatives')
@section('page-title', 'Sales Representatives')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Sales</div>
            <div class="text-2xl font-semibold text-slate-900">Sales Representatives</div>
            <div class="text-sm text-slate-500">Manage rep accounts and review totals.</div>
        </div>
        <a href="{{ route('admin.sales-reps.create') }}" class="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Add sales rep</a>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[900px] divide-y divide-slate-200 text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-[0.2em] text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left">ID</th>
                        <th class="px-4 py-3 text-left">Name</th>
                        {{-- <th class="px-4 py-3 text-left">User</th> --}}
                        <th class="px-4 py-3 text-left">Services</th>
                        <th class="px-4 py-3 text-right">Projects</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-left">Login status</th>
                        <th class="px-4 py-3 text-right">Total earned</th>
                        <th class="px-4 py-3 text-right">Payable</th>
                        <th class="px-4 py-3 text-right">Paid</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    @forelse($reps as $rep)
                        @php
                            $repTotals = $totals[$rep->id] ?? null;
                            $loginStatus = $loginStatuses[$rep->id] ?? 'logout';
                            $loginLabel = match ($loginStatus) {
                                'login' => 'Login',
                                'idle' => 'Idle',
                                default => 'Logout',
                            };
                        @endphp
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-4 py-3 font-semibold text-slate-900">#{{ $rep->id }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <x-avatar :path="$rep->avatar_path" :name="$rep->name" size="h-8 w-8" textSize="text-xs" />
                                    <div>
                                        <div class="font-semibold text-slate-900">
                                            <a href="{{ route('admin.sales-reps.show', $rep) }}" class="hover:text-teal-600">
                                                {{ $rep->name }}
                                            </a>
                                        </div>
                                        <div class="text-xs text-slate-500">{{ $rep->email ?? '--' }}</div>
                                        @if($rep->employee)
                                            <div class="text-xs text-emerald-600">Employee: {{ $rep->employee->name }}</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            {{-- <td class="px-4 py-3">
                                <div>{{ $rep->user?->name ?? '--' }}</div>
                                <div class="text-xs text-slate-500">{{ $rep->user?->email ?? '' }}</div>
                            </td> --}}
                            <td class="px-4 py-3">
                                <div class="text-slate-700 text-sm">
                                    {{ $rep->active_subscriptions_count ?? 0 }} ({{ $rep->subscriptions_count ?? 0 }})
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right">{{ $rep->projects_count ?? 0 }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full border px-2 py-0.5 text-xs font-semibold {{ $rep->status === 'active' ? 'border-emerald-200 text-emerald-700 bg-emerald-50' : 'border-slate-200 text-slate-600 bg-slate-50' }}">
                                    {{ ucfirst($rep->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span @class([
                                    'rounded-full border px-2 py-0.5 text-xs font-semibold',
                                    'border-emerald-200 text-emerald-700 bg-emerald-50' => $loginStatus === 'login',
                                    'border-amber-200 text-amber-700 bg-amber-50' => $loginStatus === 'idle',
                                    'border-rose-200 text-rose-700 bg-rose-50' => $loginStatus === 'logout',
                                ])>
                                    {{ $loginLabel }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right font-semibold">{{ number_format($repTotals->total_earned ?? 0, 2) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($repTotals->total_payable ?? 0, 2) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($repTotals->total_paid ?? 0, 2) }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-3 text-sm font-semibold">
                                    <a href="{{ route('admin.sales-reps.show', $rep) }}" class="text-teal-700 hover:text-teal-600">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16">
                                            <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/>
                                            <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/>
                                        </svg>
                                    </a>
                                    <a href="{{ route('admin.sales-reps.edit', $rep) }}" class="text-slate-700 hover:text-slate-900">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-pencil-square" viewBox="0 0 16 16">
                                            <path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/>
                                            <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5z"/>
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="px-4 py-6 text-center text-slate-500">No sales representatives yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
