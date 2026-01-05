@extends('layouts.admin')

@section('title', 'Manual Payments')
@section('page-title', 'Manual Payments')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Manual Payment Requests</h1>
            <p class="mt-1 text-sm text-slate-500">Review bank transfer submissions and approve or reject.</p>
        </div>
        <div class="flex items-center gap-2 text-xs">
            <a href="{{ route('admin.payment-proofs.index', ['status' => 'pending']) }}" class="{{ $status === 'pending' ? 'rounded-full bg-slate-900 px-3 py-1 text-white' : 'rounded-full border border-slate-200 px-3 py-1 text-slate-600 hover:border-teal-300 hover:text-teal-600' }}">Pending</a>
            <a href="{{ route('admin.payment-proofs.index', ['status' => 'approved']) }}" class="{{ $status === 'approved' ? 'rounded-full bg-slate-900 px-3 py-1 text-white' : 'rounded-full border border-slate-200 px-3 py-1 text-slate-600 hover:border-teal-300 hover:text-teal-600' }}">Approved</a>
            <a href="{{ route('admin.payment-proofs.index', ['status' => 'rejected']) }}" class="{{ $status === 'rejected' ? 'rounded-full bg-slate-900 px-3 py-1 text-white' : 'rounded-full border border-slate-200 px-3 py-1 text-slate-600 hover:border-teal-300 hover:text-teal-600' }}">Rejected</a>
        </div>
    </div>

    @if($paymentProofs->isEmpty())
        <div class="card p-6 text-sm text-slate-500">No manual payment submissions found.</div>
    @else
        <div class="card overflow-x-auto">
            <table class="w-full min-w-[900px] text-left text-sm">
                <thead class="border-b border-slate-200 text-xs uppercase tracking-[0.25em] text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Invoice</th>
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3">Gateway</th>
                        <th class="px-4 py-3">Amount</th>
                        <th class="px-4 py-3">Reference</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Submitted</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($paymentProofs as $proof)
                        <tr class="border-b border-slate-100">
                            <td class="px-4 py-3 font-medium text-slate-900">
                                <a href="{{ route('admin.invoices.show', $proof->invoice) }}" class="hover:text-teal-600">
                                    #{{ is_numeric($proof->invoice?->number) ? $proof->invoice->number : $proof->invoice_id }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $proof->customer?->name ?? '--' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $proof->paymentGateway?->name ?? 'Manual' }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $proof->invoice?->currency ?? 'BDT' }} {{ number_format((float) $proof->amount, 2) }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $proof->reference ?: '--' }}</td>
                            <td class="px-4 py-3">
                                <x-status-badge :status="$proof->status" />
                            </td>
                            <td class="px-4 py-3 text-slate-500">{{ $proof->created_at->format($globalDateFormat) }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex flex-wrap items-center justify-end gap-2 text-xs">
                                    @if($proof->attachment_url)
                                        <a href="{{ route('admin.payment-proofs.receipt', $proof) }}" target="_blank" rel="noopener" class="rounded-full border border-slate-200 px-3 py-1 font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">View receipt</a>
                                    @elseif($proof->attachment_path)
                                        <span class="text-xs font-semibold text-slate-400">Receipt unavailable</span>
                                    @endif
                                    @if($proof->status === 'pending')
                                        <form method="POST" action="{{ route('admin.payment-proofs.approve', $proof) }}">
                                            @csrf
                                            <button type="submit" class="rounded-full bg-emerald-500 px-3 py-1 text-xs font-semibold text-white">Approve</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.payment-proofs.reject', $proof) }}">
                                            @csrf
                                            <button type="submit" class="rounded-full border border-rose-200 px-3 py-1 text-xs font-semibold text-rose-600 hover:border-rose-300">Reject</button>
                                        </form>
                                    @elseif($proof->reviewer)
                                        <span class="text-xs text-slate-400">Reviewed by {{ $proof->reviewer->name }}</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
