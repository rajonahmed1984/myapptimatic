@extends('layouts.admin')

@section('title', 'Customer Details')
@section('page-title', 'Customer Details')

@section('content')
    <div class="card p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="section-label">Customer</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $customer->name }}</div>
                <div class="mt-1 text-sm text-slate-500">Client ID: {{ $customer->id }}</div>
            </div>
            <div class="text-sm text-slate-600">
                <div>Status: {{ ucfirst($customer->status) }}</div>
                <div>Created: {{ $customer->created_at?->format('Y-m-d') ?? '--' }}</div>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <a href="{{ route('admin.customers.index') }}" class="rounded-full border border-slate-200 px-5 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Back to Customers</a>
                    <form method="POST" action="{{ route('admin.customers.impersonate', $customer) }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-2 rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600" title="Login as client">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                                <path d="M15 3h4a2 2 0 0 1 2 2v4"></path>
                                <path d="M10 14L21 3"></path>
                                <path d="M21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h9"></path>
                            </svg>
                            Login as client
                        </button>
                    </form>
                </div>
            </div>
        </div>
        

        @include('admin.customers.partials.tabs', ['customer' => $customer, 'activeTab' => $tab])

        @if($tab === 'summary')
            <div class="mt-6 grid gap-4 md:grid-cols-2 text-sm text-slate-600">
                <div class="rounded-2xl border border-slate-200 bg-white/70 p-4">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Profile</div>
                    <div class="mt-2">Company: {{ $customer->company_name ?: '--' }}</div>
                    <div class="mt-1">Email: {{ $customer->email ?: '--' }}</div>
                    <div class="mt-1">Mobile: {{ $customer->phone ?: '--' }}</div>
                    <div class="mt-1">Address: {{ $customer->address ?: '--' }}</div>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white/70 p-4">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Summary</div>
                    <div class="mt-2">Services: {{ $customer->subscriptions->count() }}</div>
                    <div class="mt-1">Active services: {{ $customer->subscriptions->where('status', 'active')->count() }}</div>
                    <div class="mt-1">Invoices: {{ $customer->invoices->count() }}</div>
                    <div class="mt-1">Tickets: {{ $customer->supportTickets->count() }}</div>
                </div>
            </div>

            <div class="mt-6 grid gap-4 lg:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-white/70 p-4">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Recent Invoices</div>
                    <div class="mt-3 space-y-2 text-sm">
                        @forelse($customer->invoices->take(5) as $invoice)
                            <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                                <div>
                                    <div class="font-semibold text-slate-900">
                                        Invoice #{{ is_numeric($invoice->number) ? $invoice->number : $invoice->id }}
                                    </div>
                                    <div class="text-xs text-slate-500">{{ $invoice->issue_date?->format('Y-m-d') ?? '--' }}</div>
                                </div>
                                <div class="text-sm text-slate-600">{{ ucfirst($invoice->status) }}</div>
                            </div>
                        @empty
                            <div class="text-sm text-slate-500">No invoices yet.</div>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white/70 p-4">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Recent Tickets</div>
                    <div class="mt-3 space-y-2 text-sm">
                        @forelse($customer->supportTickets->take(5) as $ticket)
                            <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                                <div>
                                    <div class="font-semibold text-slate-900">{{ $ticket->subject }}</div>
                                    <div class="text-xs text-slate-500">
                                        TKT-{{ str_pad($ticket->id, 5, '0', STR_PAD_LEFT) }}
                                        <span class="mx-1">•</span>
                                        {{ $ticket->created_at?->format('Y-m-d') ?? '--' }}
                                    </div>
                                </div>
                                <div class="text-sm text-slate-600">{{ ucfirst(str_replace('_', ' ', $ticket->status)) }}</div>
                            </div>
                        @empty
                            <div class="text-sm text-slate-500">No support tickets yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        @elseif($tab === 'services')
            <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200">
                <table class="w-full min-w-[800px] text-left text-sm">
                    <thead class="border-b border-slate-200 text-xs uppercase tracking-[0.25em] text-slate-500">
                        <tr>
                            <th class="px-4 py-3">SL</th>
                            <th class="px-4 py-3">Product</th>
                            <th class="px-4 py-3">Plan</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Order number</th>
                            <th class="px-4 py-3">Next invoice</th>
                            <th class="px-4 py-3">Period end</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($customer->subscriptions as $subscription)
                            <tr class="border-b border-slate-100">
                                <td class="px-4 py-3 text-slate-500">{{ $loop->iteration }}</td>
                                <td class="px-4 py-3 text-slate-900">{{ $subscription->plan?->product?->name ?? '--' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $subscription->plan?->name ?? '--' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ ucfirst($subscription->status) }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ $subscription->latestOrder?->order_number ?? '--' }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ $subscription->next_invoice_at?->format('Y-m-d') ?? '--' }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ $subscription->current_period_end?->format('Y-m-d') ?? '--' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.subscriptions.edit', $subscription) }}" class="text-teal-600 hover:text-teal-500">Manage</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-6 text-center text-slate-500">No services yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @elseif($tab === 'invoices')
            <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200">
                <table class="w-full min-w-[800px] text-left text-sm">
                    <thead class="border-b border-slate-200 text-xs uppercase tracking-[0.25em] text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Invoice</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Total</th>
                            <th class="px-4 py-3">Issue date</th>
                            <th class="px-4 py-3">Due date</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($customer->invoices as $invoice)
                            <tr class="border-b border-slate-100">
                                <td class="px-4 py-3 font-medium text-slate-900">{{ is_numeric($invoice->number) ? $invoice->number : $invoice->id }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ ucfirst($invoice->status) }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $invoice->currency }} {{ $invoice->total }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ $invoice->issue_date?->format('Y-m-d') ?? '--' }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ $invoice->due_date?->format('Y-m-d') ?? '--' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.invoices.show', $invoice) }}" class="text-slate-500 hover:text-teal-600">View</a>
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
        @elseif($tab === 'tickets')
            <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200">
                <table class="w-full min-w-[800px] text-left text-sm">
                    <thead class="border-b border-slate-200 text-xs uppercase tracking-[0.25em] text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Ticket</th>
                            <th class="px-4 py-3">Subject</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Last reply</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($customer->supportTickets as $ticket)
                            @php
                                $label = ucfirst(str_replace('_', ' ', $ticket->status));
                                $statusClasses = match ($ticket->status) {
                                    'open' => 'bg-amber-100 text-amber-700',
                                    'answered' => 'bg-emerald-100 text-emerald-700',
                                    'customer_reply' => 'bg-blue-100 text-blue-700',
                                    'closed' => 'bg-slate-100 text-slate-600',
                                    default => 'bg-slate-100 text-slate-600',
                                };
                            @endphp
                            <tr class="border-b border-slate-100">
                                <td class="px-4 py-3 font-medium text-slate-900">TKT-{{ str_pad($ticket->id, 5, '0', STR_PAD_LEFT) }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $ticket->subject }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $statusClasses }}">{{ $label }}</span>
                                </td>
                                <td class="px-4 py-3 text-slate-500">{{ $ticket->last_reply_at?->format('Y-m-d H:i') ?? '--' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.support-tickets.show', $ticket) }}" class="text-slate-500 hover:text-teal-600">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-slate-500">No support tickets yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @elseif($tab === 'emails')
            <div class="mt-6 rounded-2xl border border-slate-200 bg-white/70 p-5 text-sm text-slate-600">
                Email history is not available yet for this customer.
            </div>
        @elseif($tab === 'log')
            <div class="mt-6 rounded-2xl border border-slate-200 bg-white/70 p-5 text-sm text-slate-600">
                Activity log tracking is not enabled for this customer.
            </div>
        @endif
    </div>
@endsection
