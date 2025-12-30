@extends('layouts.client')

@section('title', 'Invoice')
@section('page-title', 'Invoice')

@section('content')
    @php
        $displayNumber = is_numeric($invoice->number) ? $invoice->number : $invoice->id;
        $creditTotal = $invoice->accountingEntries->where('type', 'credit')->sum('amount');
        $paidTotal = $invoice->accountingEntries->where('type', 'payment')->sum('amount');
        $balance = max(0, (float) $invoice->total - $paidTotal - $creditTotal);
    @endphp

    <div class="card p-6 md:p-8">
        <div class="flex flex-wrap items-start justify-between gap-6">
            <div class="flex items-center gap-4">
                <div class="flex flex-col">
                    @if(!empty($portalBranding['logo_url']))
                        <img src="{{ $portalBranding['logo_url'] }}" alt="Logo" class="h-14 w-max rounded-2xl bg-white p-2">
                    @else
                        <div class="grid h-14 w-max place-items-center rounded-2xl bg-slate-900 text-lg font-semibold text-white p-2">Apptimatic</div>
                    @endif
                    <div class="text-2xl font-semibold text-slate-900 p-2">Invoice #{{ $displayNumber }}</div>
                </div>
            </div>
            <div class="text-right">
                <div class="text-xs uppercase tracking-[0.3em] text-slate-400">Status</div>
                <div class="mt-2 inline-flex items-center rounded-full px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] {{ $invoice->status === 'paid' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                    {{ ucfirst($invoice->status) }}
                </div>
                <div class="mt-2 text-xs text-slate-500">Invoice date: {{ $invoice->issue_date->format('d-m-Y') }}</div>
                <div class="mt-2 text-xs text-slate-500">Due date: {{ $invoice->due_date->format('d-m-Y') }}</div>
            </div>
        </div>

        <div class="mt-8 grid gap-6 md:grid-cols-2">
            <div class="card-muted p-4 text-sm text-slate-600">
                <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Invoiced to</div>
                <div class="mt-2 text-base font-semibold text-slate-900">{{ $invoice->customer?->name }}</div>
                <div class="mt-1">{{ $invoice->customer?->email }}</div>
                <div class="mt-2 text-xs text-slate-500">{{ $invoice->customer?->address ?: 'Address not provided.' }}</div>
            </div>
            <div class="card-muted p-4 text-sm text-slate-600">
                <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Pay to</div>
                <div class="mt-2 text-base font-semibold text-slate-900">{{ $portalBranding['company_name'] ?? 'License Portal' }}</div>
                <div class="mt-1">{{ $payToText ?: 'Billing Department' }}</div>
                <div class="mt-2 text-xs text-slate-500">{{ $companyEmail ?: 'support@example.com' }}</div>
            </div>
        </div>

        <div class="mt-8 grid gap-6 md:grid-cols-2">
            <div class="text-sm text-slate-600">                
                <div class="mt-2">
                    <span class="text-slate-500">Service:</span>
                    <span class="font-semibold text-slate-900">
                        {{ $invoice->subscription?->plan?->product?->name ?? 'Service' }}
                        {{ $invoice->subscription?->plan?->name ? ' - '.$invoice->subscription->plan->name : '' }}
                    </span>
                </div>
            </div>
            <div class="text-sm text-slate-600 no-print">
                <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Payment method</div>
                @php
                    $pendingProof = $invoice->paymentProofs->firstWhere('status', 'pending');
                    $rejectedProof = $invoice->paymentProofs->firstWhere('status', 'rejected');
                @endphp
                @if($pendingProof)
                    <div class="mt-3 rounded-2xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-700">
                        Manual payment submitted and pending review.
                        @if($pendingProof->paymentGateway)
                            <div class="mt-1">Gateway: {{ $pendingProof->paymentGateway->name }}</div>
                        @endif
                        <div class="mt-1">Amount: {{ $invoice->currency }} {{ number_format((float) $pendingProof->amount, 2) }}</div>
                        @if($pendingProof->reference)
                            <div class="mt-1">Reference: {{ $pendingProof->reference }}</div>
                        @endif
                    </div>
                @elseif($rejectedProof)
                    <div class="mt-3 rounded-2xl border border-rose-200 bg-rose-50 p-3 text-xs text-rose-700">
                        Manual payment was rejected. Please submit a new transfer.
                    </div>
                @endif
                @if($gateways->isEmpty())
                    <div class="mt-3 text-xs text-slate-500">No active payment gateways configured.</div>
                @else
                    <form method="POST" action="{{ route('client.invoices.checkout', $invoice) }}" id="gateway-form" class="mt-3 rounded-2xl border border-slate-200 bg-white p-4">
                        @csrf
                        <label for="gateway-select" class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Select gateway</label>
                        <select id="gateway-select" name="payment_gateway_id" class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700">
                            @foreach($gateways as $gateway)
                                <option value="{{ $gateway->id }}">{{ $gateway->name }}</option>
                            @endforeach
                        </select>

                        <div id="gateway-instructions" class="mt-3 text-xs text-slate-500"></div>

                        @if($invoice->status !== 'paid')
                            <button type="submit" id="gateway-submit" class="mt-4 w-full rounded-full bg-teal-500 px-4 py-2 text-xs font-semibold text-white">
                                Pay now
                            </button>
                        @else
                            <div class="mt-4 text-xs text-slate-400">Paid</div>
                        @endif
                    </form>
                    @php
                        $gatewayData = $gateways->map(function ($gateway) {
                            return [
                                'id' => $gateway->id,
                                'name' => $gateway->name,
                                'driver' => $gateway->driver,
                                'payment_url' => $gateway->settings['payment_url'] ?? '',
                                'instructions' => $gateway->settings['instructions'] ?? '',
                                'button_label' => $gateway->settings['button_label'] ?? '',
                            ];
                        })->values();
                    @endphp
                    <script>
                        const gateways = @json($gatewayData);
                        const gatewaySelect = document.getElementById('gateway-select');
                        const gatewayInstructions = document.getElementById('gateway-instructions');
                        const gatewaySubmit = document.getElementById('gateway-submit');
                        const gatewayForm = document.getElementById('gateway-form');

                        function syncGatewayDetails() {
                            const selectedId = Number(gatewaySelect.value);
                            const selected = gateways.find((gateway) => gateway.id === selectedId);

                            if (!selected) {
                                gatewayInstructions.textContent = '';
                                if (gatewaySubmit) {
                                    gatewaySubmit.textContent = 'Pay now';
                                }
                                return;
                            }

                            const instructions = selected.instructions || '';
                            gatewayInstructions.innerHTML = instructions
                                ? instructions.replace(/\n/g, '<br>')
                                : 'No additional instructions for this gateway.';

                            if (gatewaySubmit) {
                                const label = (selected.button_label || '').trim();
                                gatewaySubmit.textContent = label ? label : `${selected.name} Pay`;
                            }

                            if (gatewayForm) {
                                const openNew = selected.driver === 'bkash' && selected.payment_url;
                                gatewayForm.setAttribute('target', openNew ? '_blank' : '_self');
                            }
                        }

                        gatewaySelect.addEventListener('change', syncGatewayDetails);
                        syncGatewayDetails();
                    </script>
                @endif
                @if(!empty($paymentInstructions))
                    <div class="mt-4 text-xs text-slate-500">
                        {!! nl2br(e($paymentInstructions)) !!}
                    </div>
                @endif
            </div>
        </div>

        <div class="mt-8">
            <div class="section-label">Invoice items</div>
            <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-[0.2em] text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Description</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoice->items as $item)
                            <tr class="border-t border-slate-200">
                                <td class="px-4 py-3 text-slate-600">{{ $item->description }}</td>
                                <td class="px-4 py-3 text-right text-slate-700">{{ $invoice->currency }} {{ $item->line_total }}</td>
                            </tr>
                        @endforeach
                        <tr class="border-t border-slate-200 bg-slate-50">
                            <td class="px-4 py-3 text-right font-semibold text-slate-600">Sub total</td>
                            <td class="px-4 py-3 text-right font-semibold text-slate-900">{{ $invoice->currency }} {{ number_format((float) $invoice->subtotal, 2) }}</td>
                        </tr>
                        <tr class="border-t border-slate-200 bg-slate-50">
                            <td class="px-4 py-3 text-right font-semibold text-slate-600">Credit</td>
                            <td class="px-4 py-3 text-right font-semibold text-slate-900">{{ $invoice->currency }} {{ number_format((float) $creditTotal, 2) }}</td>
                        </tr>
                        <tr class="border-t border-slate-200 bg-slate-50">
                            <td class="px-4 py-3 text-right font-semibold text-slate-600">Total</td>
                            <td class="px-4 py-3 text-right font-semibold text-slate-900">{{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }}</td>
                        </tr>
                        <tr class="border-t border-slate-200 bg-slate-50">
                            <td class="px-4 py-3 text-right font-semibold text-slate-600">Balance</td>
                            <td class="px-4 py-3 text-right font-semibold text-slate-900">{{ $invoice->currency }} {{ number_format((float) $balance, 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-8">
            <div class="section-label">Transactions</div>
            <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-[0.2em] text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Gateway</th>
                            <th class="px-4 py-3">Reference</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($invoice->accountingEntries as $entry)
                            <tr class="border-t border-slate-200">
                                <td class="px-4 py-3 text-slate-500">{{ $entry->entry_date->format('d-m-Y') }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $entry->paymentGateway?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ $entry->reference ?? '-' }}</td>
                                <td class="px-4 py-3 text-right text-slate-700">{{ $entry->currency }} {{ number_format((float) $entry->amount, 2) }}</td>
                            </tr>
                        @empty
                            <tr class="border-t border-slate-200">
                                <td colspan="4" class="px-4 py-4 text-center text-sm text-slate-500">No related transactions found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-8 flex flex-wrap items-center justify-between gap-3 no-print">
            <a href="{{ route('client.invoices.download', $invoice) }}" class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">
                Download PDF
            </a>
            <div class="flex items-center gap-2">
                <a href="{{ route('client.dashboard') }}" class="text-xs font-semibold text-slate-500 hover:text-teal-600">Back to client area</a>
                <button type="button" onclick="window.print()" class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">
                    Print
                </button>
            </div>
        </div>
    </div>
@endsection
