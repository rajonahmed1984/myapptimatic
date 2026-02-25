@php
    $entry = $entry ?? null;
    $selectedType = old('type', $entry?->type ?? $type);
    $selectedInvoiceId = old('invoice_id', $entry?->invoice_id ?? optional($selectedInvoice)->id);
    $selectedCustomerId = old('customer_id', $entry?->customer_id ?? optional($selectedInvoice)->customer_id);
    $selectedGatewayId = old('payment_gateway_id', $entry?->payment_gateway_id ?? null);
    $dueAmount = $dueAmount ?? null;
@endphp

<div class="grid gap-4 md:grid-cols-2">
    <div>
        <label class="text-sm text-slate-600">Entry type</label>
        <select name="type" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
            <option value="payment" @selected($selectedType === 'payment')>Payment</option>
            <option value="refund" @selected($selectedType === 'refund')>Refund</option>
            <option value="credit" @selected($selectedType === 'credit')>Credit</option>
            <option value="expense" @selected($selectedType === 'expense')>Expense</option>
        </select>
    </div>

    <div>
        <label class="text-sm text-slate-600">Entry date</label>
        <input name="entry_date" type="text" placeholder="DD-MM-YYYY" inputMode="numeric" value="{{ old('entry_date', optional($entry?->entry_date)->format(config('app.date_format', 'd-m-Y')) ?? now()->format(config('app.date_format', 'd-m-Y'))) }}" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
    </div>

    <div>
        <label class="text-sm text-slate-600">Amount
            @if($dueAmount !== null)
                <span class="text-xs font-normal text-amber-600">(Due: {{ $currency }} {{ number_format($dueAmount, 2) }})</span>
            @endif
        </label>
        <input name="amount" type="number" step="0.01" min="0" value="{{ old('amount', $entry?->amount ?? $dueAmount ?? '') }}" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
    </div>

    <div>
        <label class="text-sm text-slate-600">Currency</label>
        <input name="currency" type="text" value="{{ old('currency', $currency) }}" readonly class="mt-2 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-500" />
    </div>

    <div>
        <label class="text-sm text-slate-600">Customer</label>
        <select name="customer_id" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
            <option value="">Select customer</option>
            @foreach($customers as $customer)
                <option value="{{ $customer->id }}" @selected((string) $selectedCustomerId === (string) $customer->id)>
                    {{ $customer->name }} ({{ $customer->email }})
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="text-sm text-slate-600">Invoice</label>
        <select name="invoice_id" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
            <option value="">Select invoice</option>
            @foreach($invoices as $invoiceOption)
                <option value="{{ $invoiceOption->id }}" @selected((string) $selectedInvoiceId === (string) $invoiceOption->id)>
                    {{ $invoiceOption->number }} - {{ $invoiceOption->customer?->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="text-sm text-slate-600">Payment gateway</label>
        <select name="payment_gateway_id" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
            <option value="">Select gateway</option>
            @foreach($gateways as $gateway)
                <option value="{{ $gateway->id }}" @selected((string) $selectedGatewayId === (string) $gateway->id)>
                    {{ $gateway->name }}
                </option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-slate-500">Use gateway for payments or refunds.</p>
    </div>

    <div>
        <label class="text-sm text-slate-600">Reference</label>
        <input name="reference" value="{{ old('reference', $entry?->reference ?? '') }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
    </div>

    <div class="md:col-span-2">
        <label class="text-sm text-slate-600">Description</label>
        <textarea name="description" rows="2" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">{{ old('description', $entry?->description ?? '') }}</textarea>
        <p class="mt-2 text-xs text-slate-500">Payments require a customer and invoice. Credits require a customer.</p>
    </div>
</div>
