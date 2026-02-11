@extends('layouts.admin')

@section('title', 'Open Support Ticket')
@section('page-title', 'Open Support Ticket')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Support Tickets</div>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900">Open Ticket</h1>
            <p class="mt-2 text-sm text-slate-600">Create a support ticket on behalf of a customer.</p>
        </div>
        <a href="{{ route('admin.support-tickets.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Back to tickets</a>
    </div>

    <div class="card p-6">
        <form method="POST" action="{{ route('admin.support-tickets.store') }}" class="space-y-5" enctype="multipart/form-data">
            @csrf
            <div>
                <label class="text-sm text-slate-600">Customer</label>
                <select name="customer_id" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                    <option value="">Select customer</option>
                    @foreach($customers as $customer)
                        @php
                            $label = $customer->name;
                            if ($customer->company_name) {
                                $label .= ' - ' . $customer->company_name;
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
                <label class="text-sm text-slate-600">Subject</label>
                <input name="subject" value="{{ old('subject') }}" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700" />
                @error('subject')
                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm text-slate-600">Priority</label>
                <select name="priority" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                    <option value="low" @selected(old('priority') === 'low')>Low</option>
                    <option value="medium" @selected(old('priority', 'medium') === 'medium')>Medium</option>
                    <option value="high" @selected(old('priority') === 'high')>High</option>
                </select>
                @error('priority')
                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm text-slate-600">Message</label>
                <textarea name="message" rows="6" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">{{ old('message') }}</textarea>
                @error('message')
                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm text-slate-600">Attachment (image/PDF)</label>
                <input name="attachment" type="file" accept="image/*,.pdf" class="mt-2 block w-full text-sm text-slate-600" />
                @error('attachment')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>
            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-full bg-teal-500 px-6 py-2 text-sm font-semibold text-white">Open ticket</button>
                <a href="{{ route('admin.support-tickets.index') }}" class="text-sm text-slate-600 hover:text-teal-600">Cancel</a>
            </div>
        </form>
    </div>
@endsection
