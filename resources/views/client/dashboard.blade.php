@extends('layouts.client')

@section('title', 'Client Dashboard')
@section('page-title', 'Client Overview')

@section('content')
    <div class="space-y-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="mt-2 text-3xl font-semibold text-slate-900">
                    Welcome Back, {{ $customer?->name ?? 'Client' }}
                </div>
                <div class="mt-1 text-sm text-slate-500">{{ $customer?->email }}</div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('client.orders.index') }}" class="rounded-full bg-teal-500 px-4 py-2 text-xs font-semibold text-white">Place an order</a>
                <a href="{{ route('client.support-tickets.create') }}" class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Open ticket</a>
            </div>
        </div>

        <div class="h-px bg-slate-200/80"></div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="card p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-2xl font-semibold text-slate-900">{{ $serviceCount }}</div>
                        <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Services</div>
                    </div>
                    <div class="rounded-2xl bg-teal-100 p-2 text-teal-600">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M4 7h16v10H4z"></path>
                            <path d="M8 7V5h8v2"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 h-1 w-full rounded-full bg-teal-100">
                    <div class="h-1 w-1/2 rounded-full bg-teal-500"></div>
                </div>
            </div>
            <div class="card p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-2xl font-semibold text-slate-900">{{ $domainCount }}</div>
                        <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Domains</div>
                    </div>
                    <div class="rounded-2xl bg-sky-100 p-2 text-sky-600">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <circle cx="12" cy="12" r="9"></circle>
                            <path d="M3 12h18"></path>
                            <path d="M12 3a15 15 0 0 1 0 18"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 h-1 w-full rounded-full bg-sky-100">
                    <div class="h-1 w-2/3 rounded-full bg-sky-500"></div>
                </div>
            </div>
            <div class="card p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-2xl font-semibold text-slate-900">{{ $ticketOpenCount }}</div>
                        <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Tickets</div>
                    </div>
                    <div class="rounded-2xl bg-amber-100 p-2 text-amber-600">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M4 6h16v9H7l-3 3z"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 h-1 w-full rounded-full bg-amber-100">
                    <div class="h-1 w-1/3 rounded-full bg-amber-500"></div>
                </div>
            </div>
            <div class="card p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-2xl font-semibold text-slate-900">{{ $openInvoiceCount }}</div>
                        <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Invoices</div>
                    </div>
                    <div class="rounded-2xl bg-rose-100 p-2 text-rose-600">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M7 4h10v16H7z"></path>
                            <path d="M9 8h6"></path>
                            <path d="M9 12h6"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 h-1 w-full rounded-full bg-rose-100">
                    <div class="h-1 w-1/2 rounded-full bg-rose-500"></div>
                </div>
            </div>
        </div>

        <div class="card p-4">
            <div class="flex items-center gap-3">
                <div class="text-slate-400">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <circle cx="11" cy="11" r="7"></circle>
                        <path d="M21 21l-4.3-4.3"></path>
                    </svg>
                </div>
                <input
                    type="text"
                    placeholder="Search knowledgebase or ask a question..."
                    class="w-full border-0 bg-transparent text-sm text-slate-600 placeholder:text-slate-400 focus:outline-none"
                />
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="space-y-6">
                <div class="card p-6">
                    <div class="flex items-center justify-between">
                        <div class="section-label">Your Active Products/Services</div>
                        <a href="{{ route('client.licenses.index') }}" class="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">My services</a>
                    </div>
                    <div class="mt-4 space-y-3">
                        @forelse($subscriptions as $subscription)
                            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                                <div class="flex flex-wrap items-center justify-between gap-4">
                                    <div>
                                        <div class="text-sm text-slate-500">{{ $subscription->plan->product->name }}</div>
                                        <div class="text-lg font-semibold text-slate-900">{{ $subscription->plan->name }}</div>
                                        <div class="mt-2 text-xs text-slate-500">Next invoice {{ $subscription->next_invoice_at->format('d-m-Y') }}</div>
                                    </div>
                                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">
                                        {{ ucfirst($subscription->status) }}
                                    </span>
                                </div>
                            </div>
                        @empty
                            <div class="text-sm text-slate-500">
                                You do not have any active services yet.
                                <a href="{{ route('client.orders.index') }}" class="font-semibold text-teal-600 hover:text-teal-500">Place an order</a>
                                to get started.
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="card p-6">
                    <div class="flex items-center justify-between">
                        <div class="section-label">Overdue invoices</div>
                        @if($nextOverdueInvoice)
                            <a href="{{ route('client.invoices.pay', $nextOverdueInvoice) }}" class="rounded-full bg-rose-500 px-3 py-1 text-xs font-semibold text-white">Pay now</a>
                        @endif
                    </div>
                    <div class="mt-4 text-sm text-slate-600">
                        @if($overdueInvoiceCount > 0)
                            You have {{ $overdueInvoiceCount }} overdue invoice(s) with a total balance due of
                            {{ $currency }} {{ number_format((float) $overdueInvoiceBalance, 2) }}. Pay now to avoid interruptions.
                        @else
                            No overdue invoices. You are all caught up.
                        @endif
                    </div>
                </div>

                <div class="card p-6">
                    <div class="flex items-center justify-between">
                        <div class="section-label">Recent support tickets</div>
                        <a href="{{ route('client.support-tickets.create') }}" class="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Open new ticket</a>
                    </div>
                    <div class="mt-4 space-y-3 text-sm">
                        @forelse($recentTickets as $ticket)
                            <div class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                <div>
                                    <div class="font-semibold text-slate-900">{{ $ticket->subject }}</div>
                                    <div class="text-xs text-slate-500">Updated {{ $ticket->updated_at->format('d-m-Y') }}</div>
                                </div>
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                                    {{ str_replace('_', ' ', ucfirst($ticket->status)) }}
                                </span>
                            </div>
                        @empty
                            <div class="text-sm text-slate-500">No recent tickets. Need help? Open a ticket anytime.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="card p-6">
                    <div class="flex items-center justify-between">
                        <div class="section-label">Domains expiring soon</div>
                        <a href="{{ route('client.orders.index') }}" class="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Renew now</a>
                    </div>
                    <div class="mt-4 text-sm text-slate-600">
                        @if($expiringLicenses->isNotEmpty())
                            You have {{ $expiringLicenses->count() }} license(s) expiring within 45 days.
                            <div class="mt-3 space-y-2 text-xs text-slate-500">
                                @foreach($expiringLicenses as $license)
                                    <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-3 py-2">
                                        <span>{{ $license->product?->name ?? 'Service' }}</span>
                                        <span>Expires {{ $license->expires_at->format('d-m-Y') }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            No expiring licenses in the next 45 days.
                        @endif
                    </div>
                </div>

                <div class="card p-6">
                    <div class="section-label">Add a new website</div>
                    <div class="mt-4 text-sm text-slate-600">
                        Start a new service or transfer an existing site into your account.
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <a href="{{ route('client.orders.index') }}" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white">Order</a>
                        <a href="{{ route('client.support-tickets.create') }}" class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Contact support</a>
                    </div>
                </div>

                <div id="invoices" class="card p-6">
                    <div class="flex items-center justify-between">
                        <div class="section-label">Recent invoices</div>
                        <a href="{{ route('client.invoices.index') }}" class="text-xs font-semibold text-slate-500 hover:text-teal-600">View all</a>
                    </div>
                    <div class="mt-4 space-y-3">
                        @forelse($invoices as $invoice)
                            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="text-sm text-slate-500">Invoice {{ $invoice->number }}</div>
                                        <div class="text-lg font-semibold text-slate-900">{{ $invoice->currency }} {{ $invoice->total }}</div>
                                    </div>
                                    <div class="text-sm text-slate-600">{{ ucfirst($invoice->status) }}</div>
                                </div>
                                <div class="mt-2 text-xs text-slate-500">Due {{ $invoice->due_date->format('d-m-Y') }}</div>
                                @if(in_array($invoice->status, ['unpaid', 'overdue'], true))
                                    <div class="mt-3">
                                        <a href="{{ route('client.invoices.pay', $invoice) }}" class="text-xs font-semibold text-teal-600 hover:text-teal-500">Pay now</a>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="text-sm text-slate-500">No invoices yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
