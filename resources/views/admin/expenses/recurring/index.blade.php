@extends('layouts.admin')

@section('title', 'Recurring Expenses')
@section('page-title', 'Recurring Expenses')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Finance</div>
            <div class="text-2xl font-semibold text-slate-900">Recurring expenses</div>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <form method="POST" action="{{ route('admin.expenses.recurring.generate') }}">
                @csrf
                <button type="submit" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Generate now</button>
            </form>
            <a href="{{ route('admin.expenses.recurring.create') }}" class="rounded-full bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Add recurring</a>
            <a href="{{ route('admin.expenses.index') }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Back</a>
        </div>
    </div>

    <div class="overflow-hidden">
        <div class="px-3 py-3 overflow-x-auto rounded-2xl border border-slate-300 bg-white/80">
            <table class="min-w-full text-sm text-slate-700">
                <thead>
                    <tr class="text-xs uppercase tracking-[0.2em] text-slate-500">
                        <th class="py-2 px-3">ID</th>
                        <th class="py-2 px-3">Title</th>
                        <th class="py-2 px-3">Category</th>
                        <th class="py-2 px-3">Amount</th>
                        <th class="py-2 px-3">Recurrence</th>
                        <th class="py-2 px-3">Next run</th>
                        <th class="py-2 px-3">Next due</th>
                        <th class="py-2 px-3">Status</th>
                        <th class="py-2 px-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recurringExpenses as $recurring)
                        <tr class="border-b border-slate-100">
                            <td class="py-2 px-3 font-semibold text-slate-900">{{ $recurring->id }}</td>
                            <td class="py-2 px-3 font-semibold text-slate-900">
                                <a href="{{ route('admin.expenses.recurring.show', $recurring) }}" class="hover:text-teal-600">
                                    {{ $recurring->title }}
                                </a>
                            </td>
                            <td class="py-2 px-3">{{ $recurring->category?->name ?? '--' }}</td>
                            <td class="py-2 px-3">{{ number_format($recurring->amount, 2) }}</td>
                            <td class="py-2 px-3">
                                Every {{ $recurring->recurrence_interval }} {{ $recurring->recurrence_type === 'yearly' ? 'year(s)' : 'month(s)' }}
                            </td>
                            <td class="py-2 px-3">{{ $recurring->next_run_date?->format($globalDateFormat) ?? '--' }}</td>
                            <td class="py-2 px-3">
                                {{ !empty($nextDueMap[$recurring->id]) ? \Carbon\Carbon::parse($nextDueMap[$recurring->id])->format($globalDateFormat) : '--' }}
                            </td>
                            <td class="py-2 px-3">
                                <span class="rounded-full border px-2 py-0.5 text-xs font-semibold {{ $recurring->status === 'active' ? 'border-emerald-200 text-emerald-700 bg-emerald-50' : ($recurring->status === 'paused' ? 'border-amber-200 text-amber-700 bg-amber-50' : 'border-slate-300 text-slate-600 bg-slate-50') }}">
                                    {{ ucfirst($recurring->status) }}
                                </span>
                            </td>
                            <td class="py-2 px-3 text-right">
                                <div class="flex justify-end gap-3 text-xs font-semibold">
                                    <a href="{{ route('admin.expenses.recurring.edit', $recurring) }}" class="text-teal-600 hover:text-teal-500">Edit</a>
                                    @if($recurring->status === 'active')
                                        <form method="POST" action="{{ route('admin.expenses.recurring.pause', $recurring) }}">
                                            @csrf
                                            <button type="submit" class="text-amber-600 hover:text-amber-500">Pause</button>
                                        </form>
                                    @elseif($recurring->status === 'paused')
                                        <form method="POST" action="{{ route('admin.expenses.recurring.resume', $recurring) }}">
                                            @csrf
                                            <button type="submit" class="text-emerald-600 hover:text-emerald-500">Resume</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.expenses.recurring.stop', $recurring) }}">
                                        @csrf
                                        <button type="submit" class="text-rose-600 hover:text-rose-500">Stop</button>
                                    </form>
                                    <a href="{{ route('admin.expenses.recurring.show', $recurring) }}" class="text-slate-600 hover:text-teal-600">View</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-4 px-3 text-center text-slate-500">No recurring expenses yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $recurringExpenses->links() }}</div>
    </div>
@endsection
