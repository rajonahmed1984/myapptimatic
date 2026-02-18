@extends('layouts.admin')

@section('title', 'Maintenance #'.$maintenance->id)
@section('page-title', 'Maintenance')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Projects</div>
            <div class="text-2xl font-semibold text-slate-900">{{ $maintenance->title }}</div>
            <div class="text-sm text-slate-500">Maintenance #{{ $maintenance->id }}</div>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.project-maintenances.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Back to maintenance</a>
            <a href="{{ route('admin.project-maintenances.edit', $maintenance) }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Edit</a>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-4">
        <div class="card p-4">
            <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Status</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">{{ ucfirst($maintenance->status) }}</div>
        </div>
        <div class="card p-4">
            <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Amount</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $maintenance->currency }} {{ number_format((float) $maintenance->amount, 2) }}</div>
        </div>
        <div class="card p-4">
            <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Next Billing</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $maintenance->next_billing_date?->format($globalDateFormat ?? 'Y-m-d') ?? '--' }}</div>
        </div>
        <div class="card p-4">
            <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Cycle</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">{{ ucfirst($maintenance->billing_cycle) }}</div>
        </div>
    </div>

    <div class="mt-6 grid gap-4 md:grid-cols-3">
        <div class="card p-6 md:col-span-2">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Maintenance Summary</div>
            <div class="mt-4 grid gap-4 md:grid-cols-2 text-sm text-slate-700">
                <div>
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Project</div>
                    <div class="mt-2 font-semibold text-slate-900">{{ $maintenance->project?->name ?? '--' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Customer</div>
                    <div class="mt-2 font-semibold text-slate-900">{{ $maintenance->customer?->name ?? '--' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Start Date</div>
                    <div class="mt-2 text-slate-700">{{ $maintenance->start_date?->format($globalDateFormat ?? 'Y-m-d') ?? '--' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Last Billed</div>
                    <div class="mt-2 text-slate-700">{{ $maintenance->last_billed_at?->format($globalDateFormat ?? 'Y-m-d') ?? '--' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Auto Invoice</div>
                    <div class="mt-2 text-slate-700">{{ $maintenance->auto_invoice ? 'Yes' : 'No' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Sales Rep Visible</div>
                    <div class="mt-2 text-slate-700">{{ $maintenance->sales_rep_visible ? 'Yes' : 'No' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Created By</div>
                    <div class="mt-2 text-slate-700">{{ $maintenance->creator?->name ?? '--' }}</div>
                </div>
            </div>
        </div>
        <div class="card p-6">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Quick Actions</div>
            <div class="mt-4 space-y-3 text-sm text-slate-700">
                <a href="{{ route('admin.projects.show', $maintenance->project_id) }}" class="inline-flex w-full items-center justify-center rounded-full border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Open project</a>
                <a href="{{ route('admin.invoices.index', ['maintenance_id' => $maintenance->id]) }}" class="inline-flex w-full items-center justify-center rounded-full border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">View invoices</a>
            </div>
        </div>
    </div>

    <div class="mt-6 rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Invoices</div>
                <div class="text-xs text-slate-500">Maintenance billing history.</div>
            </div>
        </div>

        @php $invoices = $maintenance->invoices ?? collect(); @endphp
        @if($invoices->isEmpty())
            <div class="mt-4 text-xs text-slate-500">No invoices yet.</div>
        @else
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead>
                    <tr class="text-xs uppercase tracking-[0.2em] text-slate-500">
                        <th class="px-3 py-2">Invoice</th>
                        <th class="px-3 py-2">Issue</th>
                        <th class="px-3 py-2">Due</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2 text-right">Total</th>
                        <th class="px-3 py-2 text-right">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($invoices as $invoice)
                        <tr class="border-t border-slate-100">
                            <td class="px-3 py-2 font-semibold text-slate-900">
                                <a href="{{ route('admin.invoices.show', $invoice) }}" class="text-teal-700 hover:text-teal-600">
                                    #{{ $invoice->number ?? $invoice->id }}
                                </a>
                            </td>
                            <td class="px-3 py-2 text-xs text-slate-600">{{ $invoice->issue_date?->format($globalDateFormat ?? 'Y-m-d') ?? '--' }}</td>
                            <td class="px-3 py-2 text-xs text-slate-600">{{ $invoice->due_date?->format($globalDateFormat ?? 'Y-m-d') ?? '--' }}</td>
                            <td class="px-3 py-2">
                                <span class="rounded-full border px-2 py-0.5 text-xs font-semibold {{ $invoice->status === 'paid' ? 'border-emerald-200 text-emerald-700 bg-emerald-50' : ($invoice->status === 'overdue' ? 'border-rose-200 text-rose-700 bg-rose-50' : ($invoice->status === 'cancelled' ? 'border-slate-200 text-slate-600 bg-slate-50' : 'border-amber-200 text-amber-700 bg-amber-50')) }}">
                                    {{ ucfirst($invoice->status) }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-right font-semibold">{{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }}</td>
                            <td class="px-3 py-2 text-right">
                                <div class="flex items-center justify-end gap-2 text-xs font-semibold">
                                    <a href="{{ route('admin.invoices.show', $invoice) }}" class="text-slate-700 hover:text-teal-600">View</a>
                                    @if($invoice->status !== 'paid')
                                        <form method="POST" action="{{ route('admin.invoices.mark-paid', $invoice) }}">
                                            @csrf
                                            <button type="submit" class="text-emerald-700 hover:text-emerald-600">Mark paid</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
