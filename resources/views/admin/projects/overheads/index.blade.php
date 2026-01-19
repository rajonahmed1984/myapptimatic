@extends('layouts.admin')

@section('title', 'Project #' . $project->id . ' overheads')
@section('page-title', 'Overhead fees')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Project</div>
            <div class="text-lg font-semibold text-slate-900">{{ $project->name }}</div>
            <div class="text-xs text-slate-600">ID: {{ $project->id }} · Status: {{ ucfirst(str_replace('_', ' ', $project->status)) }}</div>
        </div>
        <a href="{{ route('admin.projects.show', $project) }}" class="text-xs font-semibold text-teal-600 hover:text-teal-500">Back to project</a>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="text-sm font-semibold text-slate-800">Overhead fees</div>
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Project remaining: {{ $project->remaining_budget !== null ? $project->currency.' '.number_format($project->remaining_budget, 2) : '--' }}</div>
        </div>

        @if($overheads->isEmpty())
            <div class="text-xs text-slate-500">No overhead line items yet.</div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead>
                        <tr class="text-xs uppercase tracking-[0.2em] text-slate-500">
                            <th class="px-3 py-2">Invoice</th>
                            <th class="px-3 py-2">Details</th>
                            <th class="px-3 py-2">Amount</th>
                            <th class="px-3 py-2">Date</th>
                            <th class="px-3 py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($overheads as $overhead)
                            <tr class="border-t border-slate-100">
                                <td class="px-3 py-2">
                                    @if($overhead->invoice_id && $overhead->invoice)
                                        <a href="{{ route('admin.invoices.show', $overhead->invoice) }}" class="text-teal-700 hover:text-teal-600 font-semibold">#{{ $overhead->invoice->number ?? $overhead->invoice_id }}</a>
                                    @else
                                        <span class="text-slate-400 text-xs">--</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 w-2/5">{{ $overhead->short_details }}</td>
                                <td class="px-3 py-2 text-right">{{ $project->currency }} {{ number_format((float) $overhead->amount, 2) }}</td>
                                <td class="px-3 py-2">{{ $overhead->created_at?->format($globalDateFormat) ?? '--' }}</td>
                                <td class="px-3 py-2">
                                    <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-semibold {{ $overhead->invoice_id ? 'border-emerald-200 text-emerald-700 bg-emerald-50' : 'border-slate-200 text-slate-600 bg-slate-50' }}">
                                        {{ $overhead->invoice_id ? 'Invoiced' : 'Pending' }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="border-t border-slate-100 pt-4">
            @php($pendingCount = $overheads->where('invoice_id', null)->count())
            @if($pendingCount > 0)
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div class="text-xs text-slate-500">{{ $pendingCount }} pending overhead{{ $pendingCount > 1 ? 's' : '' }} can be invoiced.</div>
                    <form method="POST" action="{{ route('admin.projects.overheads.invoice', $project) }}">
                        @csrf
                        <button type="submit" class="rounded-full bg-teal-600 px-4 py-2 text-xs font-semibold text-white hover:bg-teal-500">Invoice pending overheads</button>
                    </form>
                </div>
            @endif
            <form method="POST" action="{{ route('admin.projects.overheads.store', $project) }}" class="space-y-3 text-xs text-slate-500">
                @csrf
                <div class="grid gap-3 md:grid-cols-3">
                    <div class="md:col-span-2">
                        <label class="text-xs text-slate-500">Details</label>
                        <input name="short_details" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="Feature fee or description">
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Amount</label>
                        <input name="amount" required type="number" step="0.01" min="0" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                    </div>
                    <div class="md:col-span-3 flex justify-end">
                        <button type="submit" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Add overhead fee</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
