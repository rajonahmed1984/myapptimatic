@extends('layouts.admin')

@section('title', 'Tax Settings')
@section('page-title', 'Tax Settings')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Finance</div>
            <div class="text-2xl font-semibold text-slate-900">Tax settings</div>
            <div class="mt-1 text-sm text-slate-500">Configure tax mode, default rates, and invoice notes.</div>
        </div>
        <a href="{{ route('admin.finance.reports.index') }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">View Reports</a>
    </div>

    <div class="grid gap-6 lg:grid-cols-[2fr_1fr]">
        <div class="card p-6">
            <div class="section-label">Settings</div>
            <form method="POST" action="{{ route('admin.finance.tax.update') }}" class="mt-4 grid gap-4 text-sm">
                @csrf
                @method('PUT')
                <div class="flex items-center gap-2">
                    <input type="hidden" name="enabled" value="0">
                    <input type="checkbox" name="enabled" value="1" class="h-4 w-4 rounded border-slate-300 text-emerald-600" @checked(old('enabled', $settings->enabled))>
                    <span class="text-xs text-slate-600">Enable tax system</span>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Default tax mode</label>
                    <select name="tax_mode_default" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="exclusive" @selected(old('tax_mode_default', $settings->tax_mode_default) === 'exclusive')>Exclusive (add tax on top)</option>
                        <option value="inclusive" @selected(old('tax_mode_default', $settings->tax_mode_default) === 'inclusive')>Inclusive (included in total)</option>
                    </select>
                    @error('tax_mode_default')
                        <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                    @enderror
                </div>
                <div>
                    <label class="text-xs text-slate-500">Default tax rate</label>
                    <select name="default_tax_rate_id" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="">No default</option>
                        @foreach($rates as $rate)
                            <option value="{{ $rate->id }}" @selected((string) old('default_tax_rate_id', $settings->default_tax_rate_id) === (string) $rate->id)>
                                {{ $rate->name }} ({{ rtrim(rtrim(number_format((float) $rate->rate_percent, 2, '.', ''), '0'), '.') }}%)
                            </option>
                        @endforeach
                    </select>
                    @error('default_tax_rate_id')
                        <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                    @enderror
                </div>
                <div>
                    <label class="text-xs text-slate-500">Invoice tax label</label>
                    <input name="invoice_tax_label" value="{{ old('invoice_tax_label', $settings->invoice_tax_label) }}" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                    @error('invoice_tax_label')
                        <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                    @enderror
                </div>
                <div>
                    <label class="text-xs text-slate-500">Invoice tax note template</label>
                    <textarea name="invoice_tax_note_template" rows="3" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">{{ old('invoice_tax_note_template', $settings->invoice_tax_note_template) }}</textarea>
                    <div class="mt-1 text-xs text-slate-500">Use {rate} to display the applied percentage.</div>
                    @error('invoice_tax_note_template')
                        <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                    @enderror
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Save Settings</button>
                </div>
            </form>
        </div>

        <div class="card p-6">
            <div class="section-label">Quick reference</div>
            <div class="mt-3 space-y-2 text-sm text-slate-600">
                <div>Default mode: <span class="font-semibold text-slate-900">{{ ucfirst($settings->tax_mode_default) }}</span></div>
                <div>Default rate: <span class="font-semibold text-slate-900">{{ $settings->defaultRate?->name ?? 'None' }}</span></div>
                <div>Invoices label: <span class="font-semibold text-slate-900">{{ $settings->invoice_tax_label }}</span></div>
            </div>
        </div>
    </div>

    <div class="mt-6 card p-6">
        <div class="section-label">Tax rates</div>
        <form method="POST" action="{{ route('admin.finance.tax.rates.store') }}" class="mt-4 grid gap-3 text-sm md:grid-cols-6">
            @csrf
            <div class="md:col-span-2">
                <input name="name" value="{{ old('name') }}" placeholder="Rate name" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                @error('name')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>
            <div class="md:col-span-1">
                <input type="number" step="0.01" min="0" max="100" name="rate_percent" value="{{ old('rate_percent') }}" placeholder="Rate %" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                @error('rate_percent')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>
            <div class="md:col-span-1">
                <input type="date" name="effective_from" value="{{ old('effective_from') }}" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                @error('effective_from')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>
            <div class="md:col-span-1">
                <input type="date" name="effective_to" value="{{ old('effective_to') }}" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                @error('effective_to')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>
            <div class="md:col-span-1 flex items-center gap-2">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" class="h-4 w-4 rounded border-slate-300 text-emerald-600" @checked(old('is_active', true))>
                <span class="text-xs text-slate-600">Active</span>
            </div>
            <div class="md:col-span-6">
                <button type="submit" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Add Rate</button>
            </div>
        </form>

        <div class="mt-6 overflow-x-auto rounded-2xl border border-slate-300 bg-white/80">
            <table class="min-w-full text-sm text-slate-700">
                <thead>
                    <tr class="text-xs uppercase tracking-[0.2em] text-slate-500">
                        <th class="py-2 px-3">Name</th>
                        <th class="py-2 px-3">Rate</th>
                        <th class="py-2 px-3">Effective</th>
                        <th class="py-2 px-3">Status</th>
                        <th class="py-2 px-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rates as $rate)
                        <tr class="border-b border-slate-100">
                            <td class="py-2 px-3 font-semibold text-slate-900">{{ $rate->name }}</td>
                            <td class="py-2 px-3">{{ rtrim(rtrim(number_format((float) $rate->rate_percent, 2, '.', ''), '0'), '.') }}%</td>
                            <td class="py-2 px-3">
                                <div>{{ $rate->effective_from?->format($globalDateFormat) ?? '--' }}</div>
                                <div class="text-xs text-slate-500">to {{ $rate->effective_to?->format($globalDateFormat) ?? 'Open' }}</div>
                            </td>
                            <td class="py-2 px-3">
                                <span class="rounded-full border px-2 py-0.5 text-xs font-semibold {{ $rate->is_active ? 'border-emerald-200 text-emerald-700 bg-emerald-50' : 'border-slate-300 text-slate-600 bg-slate-50' }}">
                                    {{ $rate->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="py-2 px-3 text-right">
                                <div class="flex justify-end gap-3 text-xs font-semibold">
                                    <a href="{{ route('admin.finance.tax.rates.edit', $rate) }}" class="text-teal-600 hover:text-teal-500">Edit</a>
                                    <form
                                        method="POST"
                                        action="{{ route('admin.finance.tax.rates.destroy', $rate) }}"
                                        data-delete-confirm
                                        data-confirm-name="{{ $rate->label ?? ('Rate #' . $rate->id) }}"
                                        data-confirm-title="Delete tax rate {{ $rate->label ?? ('Rate #' . $rate->id) }}?"
                                        data-confirm-description="This will permanently delete the tax rate."
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-rose-600 hover:text-rose-500">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-4 px-3 text-center text-slate-500">No tax rates yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
