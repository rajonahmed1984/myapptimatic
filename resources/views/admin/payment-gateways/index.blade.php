@extends('layouts.admin')

@section('title', 'Payment Gateways')
@section('page-title', 'Payment Gateways')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Payment Gateways</h1>
            <p class="mt-1 text-sm text-slate-500">Enable gateways and store credentials for manual and online payments.</p>
        </div>
    </div>

    <div class="card overflow-hidden">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-200 text-xs uppercase tracking-[0.25em] text-slate-500">
                <tr>
                    <th class="px-4 py-3">Gateway</th>
                    <th class="px-4 py-3">Driver</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($gateways as $gateway)
                    <tr class="border-b border-slate-100">
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $gateway->name }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ ucfirst($gateway->driver) }}</td>
                        <td class="px-4 py-3">
                            @if($gateway->is_active)
                                <span class="pill">Active</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Inactive</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.payment-gateways.edit', $gateway) }}" class="text-teal-600 hover:text-teal-500">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-slate-500">No gateways found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
