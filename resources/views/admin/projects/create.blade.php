@extends('layouts.admin')

@section('title', 'New Project')
@section('page-title', 'New Project')

@section('content')
    <div class="card p-6">
        <div class="flex items-center justify-between">
            <div>
                <div class="section-label">Delivery</div>
                <div class="text-2xl font-semibold text-slate-900">Create project</div>
                <div class="text-sm text-slate-500">Link to an order/invoice and start tracking tasks.</div>
            </div>
            <a href="{{ route('admin.projects.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Back to projects</a>
        </div>

        <form method="POST" action="{{ route('admin.projects.store') }}" class="mt-6 grid gap-4 rounded-2xl border border-slate-200 bg-white/80 p-5">
            @csrf
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-xs text-slate-500">Project name</label>
                    <input name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Customer</label>
                    <select name="customer_id" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="">Select customer</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-xs text-slate-500">Order (optional)</label>
                    <select name="order_id" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="">-- none --</option>
                        @foreach($orders as $order)
                            <option value="{{ $order->id }}" @selected(old('order_id') == $order->id)>#{{ $order->order_number ?? $order->id }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Subscription (optional)</label>
                    <select name="subscription_id" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="">-- none --</option>
                        @foreach($subscriptions as $subscription)
                            <option value="{{ $subscription->id }}" @selected(old('subscription_id') == $subscription->id)>Subscription #{{ $subscription->id }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-xs text-slate-500">Advance invoice (optional)</label>
                    <select name="advance_invoice_id" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="">-- none --</option>
                        @foreach($invoices as $invoice)
                            <option value="{{ $invoice->id }}" @selected(old('advance_invoice_id') == $invoice->id)>
                                #{{ is_numeric($invoice->number) ? $invoice->number : $invoice->id }} — {{ number_format((float) $invoice->total, 2) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Final invoice (optional)</label>
                    <select name="final_invoice_id" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="">-- none --</option>
                        @foreach($invoices as $invoice)
                            <option value="{{ $invoice->id }}" @selected(old('final_invoice_id') == $invoice->id)>
                                #{{ is_numeric($invoice->number) ? $invoice->number : $invoice->id }} — {{ number_format((float) $invoice->total, 2) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label class="text-xs text-slate-500">Type</label>
                    <select name="type" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        @foreach($types as $type)
                            <option value="{{ $type }}" @selected(old('type', 'software') == $type)>{{ ucfirst($type) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Status</label>
                    <select name="status" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        @foreach($statuses as $status)
                            <option value="{{ $status }}" @selected(old('status', 'active') == $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Timeline end date</label>
                    <input type="date" name="due_date" value="{{ old('due_date') }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Budget & timeline</div>
                    <div class="mt-3 space-y-3">
                        <div>
                            <label class="text-xs text-slate-500">Project budget (total)</label>
                            <input type="number" step="0.01" name="budget_amount" value="{{ old('budget_amount') }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="text-xs text-slate-500">Planned hours/days</label>
                            <input type="number" step="0.01" name="planned_hours" value="{{ old('planned_hours') }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="e.g. 80 for hours or 10 for days">
                        </div>
                        <div>
                            <label class="text-xs text-slate-500">Hourly cost (optional)</label>
                            <input type="number" step="0.01" name="hourly_cost" value="{{ old('hourly_cost') }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="Derived from employee salary">
                        </div>
                        <div>
                            <label class="text-xs text-slate-500">Team members (cost basis)</label>
                            <select name="employee_ids[]" multiple class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                                @foreach($employees as $employee)
                                    <option value="{{ $employee->id }}" @selected(collect(old('employee_ids', []))->contains($employee->id))>
                                        {{ $employee->name }}{{ $employee->designation ? ' — '.$employee->designation : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-slate-500">Select all team members contributing to this project to help estimate hourly cost.</p>
                        </div>
                    </div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Payments guidance</div>
                    <p class="mt-2 text-sm text-slate-600">
                        Use the Order page milestone form to generate the first (advance) invoice and the remaining (final) invoice.
                        Budget here is for internal profit tracking; invoices are created separately via advance/final percentages.
                    </p>
                </div>
            </div>

            <div>
                <label class="text-xs text-slate-500">Notes</label>
                <textarea name="notes" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">{{ old('notes') }}</textarea>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-full bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Create project</button>
                <a href="{{ route('admin.projects.index') }}" class="text-sm font-semibold text-slate-600 hover:text-slate-800">Cancel</a>
            </div>
        </form>
    </div>
@endsection
