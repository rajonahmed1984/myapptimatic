@extends('layouts.admin')

@section('title', 'Create Invoice')
@section('page-title', 'Create Invoice')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Invoices</div>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900">Create Invoice</h1>
            <p class="mt-2 text-sm text-slate-600">Create a manual invoice for a customer.</p>
        </div>
        <a href="{{ route('admin.invoices.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Back to invoices</a>
    </div>

    <div class="card p-6">
        <form method="POST" action="{{ route('admin.invoices.store') }}" class="space-y-6">
            @csrf

            <div class="grid gap-4 md:grid-cols-3">
                <div class="md:col-span-2">
                    <label class="text-sm text-slate-600">Customer</label>
                    <select name="customer_id" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                        <option value="">Select customer</option>
                        @foreach($customers as $customer)
                            @php
                                $label = $customer->name;
                                if ($customer->company_name) {
                                    $label .= ' — ' . $customer->company_name;
                                }
                                if ($customer->email) {
                                    $label .= ' (' . $customer->email . ')';
                                }
                            @endphp
                            <option value="{{ $customer->id }}" @selected((string) old('customer_id', $selectedCustomerId ?? '') === (string) $customer->id)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    @error('customer_id')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="text-sm text-slate-600">Issue Date</label>
                    <input name="issue_date" type="date" value="{{ old('issue_date', $issueDate ?? now()->toDateString()) }}" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                    @error('issue_date')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="text-sm text-slate-600">Due Date</label>
                    <input name="due_date" type="date" value="{{ old('due_date', $dueDate ?? now()->toDateString()) }}" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                    @error('due_date')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
                <div class="md:col-span-3">
                    <label class="text-sm text-slate-600">Notes</label>
                    <textarea name="notes" rows="3" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">{{ old('notes') }}</textarea>
                </div>
            </div>

            <div>
                <div class="flex items-center justify-between">
                    <div class="text-sm font-semibold text-slate-700">Invoice Items</div>
                    <button type="button" id="addInvoiceItem" class="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Add item</button>
                </div>
                @error('items')
                    <p class="mt-2 text-xs text-rose-600">{{ $message }}</p>
                @enderror
                <div id="invoiceItems" class="mt-4 space-y-3">
                    @php
                        $items = old('items', [['description' => '', 'quantity' => 1, 'unit_price' => 0]]);
                    @endphp
                    @foreach($items as $index => $item)
                        <div class="invoice-item grid gap-3 md:grid-cols-12 items-start">
                            <div class="md:col-span-7">
                                <label class="text-xs text-slate-500">Description</label>
                                <input name="items[{{ $index }}][description]" value="{{ $item['description'] ?? '' }}" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" />
                            </div>
                            <div class="md:col-span-2">
                                <label class="text-xs text-slate-500">Qty</label>
                                <input name="items[{{ $index }}][quantity]" type="number" min="1" value="{{ $item['quantity'] ?? 1 }}" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" />
                            </div>
                            <div class="md:col-span-2">
                                <label class="text-xs text-slate-500">Unit Price</label>
                                <input name="items[{{ $index }}][unit_price]" type="number" min="0" step="0.01" value="{{ $item['unit_price'] ?? 0 }}" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" />
                            </div>
                            <div class="md:col-span-1 flex items-end">
                                <button type="button" class="removeInvoiceItem rounded-full border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-600 hover:border-rose-300">Remove</button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-full bg-teal-500 px-5 py-2 text-sm font-semibold text-white">Create invoice</button>
                <a href="{{ route('admin.invoices.index') }}" class="text-sm text-slate-600 hover:text-teal-600">Cancel</a>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const container = document.getElementById('invoiceItems');
            const addBtn = document.getElementById('addInvoiceItem');

            if (!container || !addBtn) return;

            const renumber = () => {
                container.querySelectorAll('.invoice-item').forEach((row, index) => {
                    row.querySelectorAll('input').forEach((input) => {
                        const name = input.getAttribute('name') || '';
                        const updated = name.replace(/items\[\d+\]/, `items[${index}]`);
                        input.setAttribute('name', updated);
                    });
                });
            };

            const addRow = () => {
                const template = container.querySelector('.invoice-item');
                if (!template) return;
                const clone = template.cloneNode(true);
                clone.querySelectorAll('input').forEach((input) => {
                    if (input.type === 'number') {
                        input.value = input.name.includes('[quantity]') ? '1' : '0';
                    } else {
                        input.value = '';
                    }
                });
                container.appendChild(clone);
                renumber();
            };

            addBtn.addEventListener('click', addRow);
            container.addEventListener('click', (event) => {
                const btn = event.target.closest('.removeInvoiceItem');
                if (!btn) return;
                const row = btn.closest('.invoice-item');
                if (!row) return;
                if (container.querySelectorAll('.invoice-item').length === 1) {
                    row.querySelectorAll('input').forEach((input) => {
                        input.value = input.type === 'number' && input.name.includes('[quantity]') ? '1' : '';
                    });
                    return;
                }
                row.remove();
                renumber();
            });
        });
    </script>
@endpush
