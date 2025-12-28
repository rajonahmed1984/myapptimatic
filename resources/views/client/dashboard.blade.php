@extends('layouts.client')

@section('title', 'Client Dashboard')
@section('page-title', 'Client Overview')

@section('content')
    <div class="grid gap-8">
        <div class="card p-6">
            <div class="section-label">Account</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $customer?->name ?? 'No customer linked' }}</div>
            <div class="mt-1 text-sm text-slate-500">{{ $customer?->email }}</div>
        </div>

        <div class="card p-6">
            <div class="section-label">Subscriptions</div>
            <div class="mt-4 space-y-3">
                @forelse($subscriptions as $subscription)
                    <div class="card-muted p-4">
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <div class="text-sm text-slate-500">{{ $subscription->plan->product->name }}</div>
                                <div class="text-lg font-semibold text-slate-900">{{ $subscription->plan->name }}</div>
                            </div>
                            <div class="text-sm text-slate-600">
                                {{ ucfirst($subscription->status) }} - Next invoice {{ $subscription->next_invoice_at->format('Y-m-d') }}
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-sm text-slate-500">No active subscriptions yet.</div>
                @endforelse
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2 stagger">
            <div id="invoices" class="card p-6">
                <div class="section-label">Recent invoices</div>
                <div class="mt-4 space-y-3">
                    @forelse($invoices as $invoice)
                        <div class="card-muted p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="text-sm text-slate-500">Invoice {{ $invoice->number }}</div>
                                    <div class="text-lg font-semibold text-slate-900">{{ $invoice->currency }} {{ $invoice->total }}</div>
                                </div>
                                <div class="text-sm text-slate-600">{{ ucfirst($invoice->status) }}</div>
                            </div>
                            <div class="mt-2 text-xs text-slate-500">Due {{ $invoice->due_date->format('Y-m-d') }}</div>
                        </div>
                    @empty
                        <div class="text-sm text-slate-500">No invoices yet.</div>
                    @endforelse
                </div>
            </div>

            <div id="licenses" class="card p-6">
                <div class="section-label">Licenses</div>
                <div class="mt-4 space-y-3">
                    @forelse($licenses as $license)
                        <div class="card-muted p-4">
                            <div class="text-sm text-slate-500">{{ $license->product->name }}</div>
                            <div class="mt-1 font-mono text-sm text-teal-700">{{ $license->license_key }}</div>
                            <div class="mt-2 text-xs text-slate-500">{{ ucfirst($license->status) }}</div>
                        </div>
                    @empty
                        <div class="text-sm text-slate-500">No licenses issued yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
