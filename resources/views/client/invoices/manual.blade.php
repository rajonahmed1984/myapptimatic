@extends('layouts.client')

@section('title', 'Manual Payment')
@section('page-title', 'Manual Payment')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Manual Payment</h1>
            <p class="mt-1 text-sm text-slate-500">Submit transfer details so we can verify your payment.</p>
        </div>
        <a href="{{ route('client.invoices.pay', $invoice) }}" class="text-sm text-slate-500 hover:text-teal-600">Back to invoice</a>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="card p-6">
            <div class="section-label">Invoice</div>
            <div class="mt-2 text-xl font-semibold text-slate-900">Invoice #{{ is_numeric($invoice->number) ? $invoice->number : $invoice->id }}</div>
            <div class="mt-4 space-y-2 text-sm text-slate-600">
                <div><span class="text-slate-500">Total:</span> {{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }}</div>
                <div><span class="text-slate-500">Due date:</span> {{ $invoice->due_date->format($globalDateFormat) }}</div>
                <div><span class="text-slate-500">Service:</span> {{ $invoice->subscription?->plan?->product?->name ?? 'Service' }}</div>
            </div>
            @if(!empty($gateway->settings['account_name'] ?? null) || !empty($gateway->settings['account_number'] ?? null) || !empty($gateway->settings['bank_name'] ?? null))
                <div class="mt-4 text-xs text-slate-500">
                    <div><span class="font-semibold text-slate-700">Account name:</span> {{ $gateway->settings['account_name'] ?? '--' }}</div>
                    <div><span class="font-semibold text-slate-700">Account number:</span> {{ $gateway->settings['account_number'] ?? '--' }}</div>
                    <div><span class="font-semibold text-slate-700">Bank name:</span> {{ $gateway->settings['bank_name'] ?? '--' }}</div>
                    <div><span class="font-semibold text-slate-700">Branch:</span> {{ $gateway->settings['branch'] ?? '--' }}</div>
                    <div><span class="font-semibold text-slate-700">Routing:</span> {{ $gateway->settings['routing_number'] ?? '--' }}</div>
                </div>
            @endif
            @if(!empty($gateway->settings['instructions'] ?? null))
                <div class="mt-4 text-xs text-slate-500">
                    {!! nl2br(e($gateway->settings['instructions'])) !!}
                </div>
            @endif
            @if(!empty($paymentInstructions))
                <div class="mt-4 text-xs text-slate-500">
                    {!! nl2br(e($paymentInstructions)) !!}
                </div>
            @endif
        </div>

        <div class="card p-6">
            <div class="section-label">Payment Submission</div>
            <form method="POST" action="{{ route('client.invoices.manual.store', [$invoice, $attempt]) }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label class="text-sm text-slate-600">Reference / Transaction ID</label>
                    <input name="reference" value="{{ old('reference') }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" placeholder="e.g. TRX123456" />
                </div>
                <div>
                    <label class="text-sm text-slate-600">Amount paid</label>
                    <input name="amount" type="number" step="0.01" min="0" value="{{ old('amount', $invoice->total) }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" required />
                </div>
                <div>
                    <label class="text-sm text-slate-600">Payment date</label>
                    <input name="paid_at" type="date" value="{{ old('paid_at') }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-sm text-slate-600">Notes</label>
                    <textarea name="notes" rows="3" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" placeholder="Add any extra details">{{ old('notes') }}</textarea>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Transfer receipt image</label>
                    <input name="receipt" type="file" accept="image/*" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                    <p class="mt-2 text-xs text-slate-500">Upload a clear screenshot or photo of the transfer.</p>
                </div>
                <button type="submit" class="w-full rounded-full bg-teal-500 px-4 py-2 text-xs font-semibold text-white">Submit for verification</button>
            </form>
        </div>
    </div>

    @if($attempt->proofs->isNotEmpty())
        <div class="card mt-6 p-6">
            <div class="section-label">Previous Submissions</div>
            <div class="mt-4 space-y-4 text-sm text-slate-600">
                @foreach($attempt->proofs as $proof)
                    <div class="rounded-2xl border border-slate-200 bg-white/70 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <div class="font-semibold text-slate-900">Status: {{ ucfirst($proof->status) }}</div>
                                <div class="text-xs text-slate-500">Amount: {{ $invoice->currency }} {{ number_format((float) $proof->amount, 2) }}</div>
                                <div class="text-xs text-slate-500">Reference: {{ $proof->reference ?: '--' }}</div>
                            </div>
                            @if($proof->attachment_url)
                                <a href="{{ $proof->attachment_url }}" target="_blank" rel="noopener" class="text-xs font-semibold text-teal-600 hover:text-teal-500">View receipt</a>
                            @elseif($proof->attachment_path)
                                <span class="text-xs font-semibold text-slate-400">Receipt unavailable</span>
                            @endif
                        </div>
                        @if($proof->notes)
                            <div class="mt-2 text-xs text-slate-500">{{ $proof->notes }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif
@endsection

