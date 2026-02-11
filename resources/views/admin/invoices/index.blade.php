@extends('layouts.admin')

@section('title', $title ?? 'Invoices')
@section('page-title', $title ?? 'Invoices')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold text-slate-900">{{ $title ?? 'Invoices' }}</h1>

        <form method="GET" action="" class="flex flex-nowrap items-end gap-3 text-sm">
            <div>
                <label class="text-xs uppercase tracking-[0.2em] text-slate-400">Product</label>
                <select name="product_id" class="mt-1 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                    <option value="">All</option>
                    @foreach($products ?? [] as $product)
                        <option value="{{ $product->id }}" @selected((string)$productFilter === (string)$product->id)>
                            {{ $product->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs uppercase tracking-[0.2em] text-slate-400">Maintenance</label>
                <select name="maintenance_id" class="mt-1 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                    <option value="">All</option>
                    @foreach($maintenances ?? [] as $maintenance)
                        <option value="{{ $maintenance->id }}" @selected((string)$maintenanceFilter === (string)$maintenance->id)>
                            {{ $maintenance->title }} @if($maintenance->project) — {{ $maintenance->project->name }} @endif
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white">Filter</button>
            @if($productFilter || $maintenanceFilter)
                <a href="{{ url()->current() }}" class="text-xs font-semibold text-slate-600 hover:text-teal-600">Clear</a>
            @endif
        </form>
    </div>

    <div class="card overflow-x-auto">
        <table class="w-full min-w-[1050px] text-left text-sm">
            <thead class="border-b border-slate-300 text-xs uppercase tracking-[0.25em] text-slate-500">
                <tr>
                    <th class="px-4 py-3">Invoice</th>
                    <th class="px-4 py-3">Customer</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Partial status</th>
                    <th class="px-4 py-3">Total</th>
                    <th class="px-4 py-3">Paid date</th>
                    <th class="px-4 py-3">Due</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoices as $invoice)
                    @php
                        $creditTotal = $invoice->accountingEntries->where('type', 'credit')->sum('amount');
                        $paidTotal = $invoice->accountingEntries->where('type', 'payment')->sum('amount');
                        $paidAmount = (float) $paidTotal + (float) $creditTotal;
                        $isPartial = $paidAmount > 0 && $paidAmount < (float) $invoice->total;
                    @endphp
                    <tr class="border-b border-slate-100">
                        <td class="px-4 py-3 font-medium text-slate-900">
                            <a href="{{ route('admin.invoices.show', $invoice) }}" class="text-teal-600 hover:text-teal-500">#{{ is_numeric($invoice->number) ? $invoice->number : $invoice->id }}</a>                            
                        </td>
                        <td class="px-4 py-3 text-slate-500">
                            <a href="{{ route('admin.customers.show', $invoice->customer) }}" class="text-teal-600 hover:text-teal-500">{{ $invoice->customer->name }}</a>
                        </td>
                        <td class="px-4 py-3">
                            <x-status-badge :status="$invoice->status" />
                            @php
                                $pendingProof = $invoice->paymentProofs->firstWhere('status', 'pending');
                                $rejectedProof = $invoice->paymentProofs->firstWhere('status', 'rejected');
                            @endphp
                            @if($pendingProof)
                                <div class="mt-1 text-xs text-amber-600">Manual payment pending review</div>
                            @elseif($rejectedProof)
                                <div class="mt-1 text-xs text-rose-600">Manual payment rejected</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-500">
                            @if($isPartial)
                                <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">Partial</span>
                                <div class="mt-1 text-xs text-slate-500">{{ $invoice->currency }} {{ number_format($paidAmount, 2) }} paid</div>
                            @else
                                --
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ $invoice->currency }} {{ $invoice->total }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $invoice->paid_at?->format($globalDateFormat) ?? '--' }}</td>
                        <td class="px-4 py-3">{{ $invoice->due_date->format($globalDateFormat) }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('admin.invoices.show', $invoice) }}" class="text-teal-600 hover:text-teal-500">View</a>
                                <form
                                    method="POST"
                                    action="{{ route('admin.invoices.destroy', $invoice) }}"
                                    data-delete-confirm
                                    data-confirm-name="{{ is_numeric($invoice->number) ? $invoice->number : $invoice->id }}"
                                    data-confirm-title="Delete invoice #{{ is_numeric($invoice->number) ? $invoice->number : $invoice->id }}?"
                                    data-confirm-description="This will permanently delete the invoice and related data."
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-rose-600 hover:text-rose-500">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-slate-500">
                            {{ $statusFilter ? 'No '.$title.' found.' : 'No invoices yet.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $invoices->links() }}
    </div>
@endsection
