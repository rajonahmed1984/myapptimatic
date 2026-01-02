@extends('layouts.client')

@section('title', $title ?? 'Invoices')
@section('page-title', $title ?? 'Invoices')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">{{ $title ?? 'Invoices' }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $subtitle ?? 'Review invoices and complete payment for unpaid items.' }}</p>
        </div>
        <a href="{{ route('client.dashboard') }}" class="text-sm text-slate-500 hover:text-teal-600">Back to dashboard</a>
    </div>

    @if($invoices->isEmpty())
        <div class="card p-6 text-sm text-slate-500">
            {{ $statusFilter ? 'No '.$title.' found.' : 'No invoices found.' }}
        </div>
    @else
        <div class="card overflow-hidden">
            <table class="w-full min-w-[860px] text-left text-sm">
                <thead class="border-b border-slate-200 text-xs uppercase tracking-[0.25em] text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Invoice</th>
                        <th class="px-4 py-3">Service</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Total</th>
                        <th class="px-4 py-3">Issue</th>
                        <th class="px-4 py-3">Due</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoices as $invoice)
                        @php
                            $plan = $invoice->subscription?->plan;
                            $product = $plan?->product;
                            $service = $product ? $product->name . ' · ' . ($plan?->name ?? '') : ($plan?->name ?? '--');
                        @endphp
                        <tr class="border-b border-slate-100">
                            <td class="px-4 py-3 font-medium text-slate-900">#{{ is_numeric($invoice->number) ? $invoice->number : $invoice->id }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $service }}</td>
                            <td class="px-4 py-3 text-slate-600">
                                <div>{{ ucfirst($invoice->status) }}</div>
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
                            <td class="px-4 py-3 text-slate-700">{{ $invoice->currency }} {{ $invoice->total }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $invoice->issue_date->format($globalDateFormat) }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $invoice->due_date->format($globalDateFormat) }}</td>
                            <td class="px-4 py-3 text-right overflow-visible">
                                <div class="flex flex-wrap items-center justify-end gap-3 text-xs">
                                    <a href="{{ route('client.invoices.show', $invoice) }}" class="text-slate-500 hover:text-teal-600">View</a>
                                    @if(in_array($invoice->status, ['unpaid', 'overdue'], true))
                                        <a href="{{ route('client.invoices.pay', $invoice) }}" class="text-teal-600 hover:text-teal-500">Pay now</a>
                                    @endif
                                    @if($pendingProof && $pendingProof->paymentAttempt)
                                        <a href="{{ route('client.invoices.manual', [$invoice, $pendingProof->paymentAttempt]) }}" class="text-slate-500 hover:text-teal-600">View submission</a>
                                    @endif
                                    <a href="{{ route('client.invoices.download', $invoice) }}" class="text-slate-500 hover:text-teal-600">Download</a>
                                    <details class="relative">
                                        <summary class="cursor-pointer text-slate-500 hover:text-teal-600">Request</summary>
                                        <form method="POST" action="{{ route('client.requests.store') }}" class="absolute right-0 z-10 mt-2 w-64 rounded-xl border border-slate-200 bg-white p-3 text-left shadow-lg">
                                            @csrf
                                            <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
                                            <label class="text-xs font-semibold text-slate-500">Type</label>
                                            <select name="type" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-600">
                                                <option value="invoice_edit">Request edit</option>
                                                <option value="invoice_cancel">Request cancellation</option>
                                                <option value="invoice_delete">Request delete</option>
                                            </select>
                                            <label class="mt-3 block text-xs font-semibold text-slate-500">Message (optional)</label>
                                            <textarea name="message" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-600" placeholder="Add any details..."></textarea>
                                            <button type="submit" class="mt-3 w-full rounded-full bg-teal-500 px-4 py-2 text-xs font-semibold text-white">Submit request</button>
                                        </form>
                                    </details>
                                </div>
                                @if($invoice->clientRequests->where('status', 'pending')->isNotEmpty())
                                    <div class="mt-2 text-xs text-amber-600">Request pending</div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection

