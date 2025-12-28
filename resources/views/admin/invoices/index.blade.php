@extends('layouts.admin')

@section('title', 'Invoices')
@section('page-title', 'Invoices')

@section('content')
    <h1 class="mb-6 text-2xl font-semibold text-slate-900">Invoices</h1>

    <div class="card overflow-hidden">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-200 text-xs uppercase tracking-[0.25em] text-slate-500">
                <tr>
                    <th class="px-4 py-3">Invoice</th>
                    <th class="px-4 py-3">Customer</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Total</th>
                    <th class="px-4 py-3">Due</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoices as $invoice)
                    <tr class="border-b border-slate-100">
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $invoice->number }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $invoice->customer->name }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ ucfirst($invoice->status) }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $invoice->currency }} {{ $invoice->total }}</td>
                        <td class="px-4 py-3">{{ $invoice->due_date->format('Y-m-d') }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.invoices.show', $invoice) }}" class="text-teal-600 hover:text-teal-500">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-slate-500">No invoices yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
