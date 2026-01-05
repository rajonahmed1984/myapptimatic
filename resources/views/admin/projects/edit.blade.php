@extends('layouts.admin')

@section('title', 'Edit Project #'.$project->id)
@section('page-title', 'Edit Project')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Delivery</div>
            <div class="text-2xl font-semibold text-slate-900">Edit project</div>
            <div class="text-sm text-slate-500">Update project details, links, and budget fields.</div>
        </div>
        <a href="{{ route('admin.projects.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Back to projects</a>
    </div>

    <div class="card p-6">
        <form method="POST" action="{{ route('admin.projects.update', $project) }}" class="mt-2 grid gap-4 rounded-2xl border border-slate-200 bg-white/80 p-5">
            @csrf
            @method('PUT')

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-xs text-slate-500">Project name</label>
                    <input name="name" value="{{ old('name', $project->name) }}" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Customer</label>
                    <select name="customer_id" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="">Select customer</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" @selected(old('customer_id', $project->customer_id) == $customer->id)>{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label class="text-xs text-slate-500">Order (optional)</label>
                    <select name="order_id" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="">-- none --</option>
                        @foreach($orders as $order)
                            <option value="{{ $order->id }}" @selected(old('order_id', $project->order_id) == $order->id)>{{ $order->order_number ?? $order->id }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Invoice (optional)</label>
                    <select name="invoice_id" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="">-- none --</option>
                        @foreach($invoices as $invoice)
                            <option value="{{ $invoice->id }}" @selected(old('invoice_id', $project->advance_invoice_id) == $invoice->id)>{{ is_numeric($invoice->number) ? $invoice->number : $invoice->id }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Subscription (optional)</label>
                    <select name="subscription_id" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="">-- none --</option>
                        @foreach($subscriptions as $subscription)
                            <option value="{{ $subscription->id }}" @selected(old('subscription_id', $project->subscription_id) == $subscription->id)>Subscription #{{ $subscription->id }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label class="text-xs text-slate-500">Type</label>
                    <select name="type" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        @foreach($types as $type)
                            <option value="{{ $type }}" @selected(old('type', $project->type) === $type)>{{ ucfirst($type) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Status</label>
                    <select name="status" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        @foreach($statuses as $status)
                            <option value="{{ $status }}" @selected(old('status', $project->status) === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Sales representatives</label>
                    <select name="sales_rep_ids[]" multiple class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        @foreach($salesReps as $rep)
                            <option value="{{ $rep->id }}" @selected(collect(old('sales_rep_ids', $project->salesRepresentatives->pluck('id')->toArray()))->contains($rep->id))>{{ $rep->name }} ({{ $rep->email }})</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-xs text-slate-500">Start date</label>
                    <input name="start_date" type="date" value="{{ old('start_date', optional($project->start_date)->format('Y-m-d')) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Expected end date</label>
                    <input name="expected_end_date" type="date" value="{{ old('expected_end_date', optional($project->expected_end_date)->format('Y-m-d')) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-xs text-slate-500">Due date (internal)</label>
                    <input name="due_date" type="date" value="{{ old('due_date', optional($project->due_date)->format('Y-m-d')) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Assign employees</label>
                    <select name="employee_ids[]" multiple class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}" @selected(collect(old('employee_ids', $project->employees->pluck('id')->toArray()))->contains($employee->id))>{{ $employee->name }} {{ $employee->designation ? "({$employee->designation})" : '' }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="text-xs text-slate-500">Description</label>
                <textarea name="description" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">{{ old('description', $project->description ?? '') }}</textarea>
            </div>

            <div>
                <label class="text-xs text-slate-500">Notes</label>
                <textarea name="notes" rows="2" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">{{ old('notes', $project->notes) }}</textarea>
            </div>

            <div class="grid gap-4 md:grid-cols-4">
                <div>
                    <label class="text-xs text-slate-500">Total budget</label>
                    <input name="total_budget" type="number" step="0.01" value="{{ old('total_budget', $project->total_budget) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" required>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Initial payment</label>
                    <input name="initial_payment_amount" type="number" step="0.01" value="{{ old('initial_payment_amount', $project->initial_payment_amount) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" required>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Currency</label>
                    <input name="currency" value="{{ old('currency', $project->currency) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" required>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Budget (legacy)</label>
                    <input name="budget_amount" type="number" step="0.01" value="{{ old('budget_amount', $project->budget_amount) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label class="text-xs text-slate-500">Planned hours</label>
                    <input name="planned_hours" type="number" step="0.01" value="{{ old('planned_hours', $project->planned_hours) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Hourly cost</label>
                    <input name="hourly_cost" type="number" step="0.01" value="{{ old('hourly_cost', $project->hourly_cost) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Actual hours</label>
                    <input name="actual_hours" type="number" step="0.01" value="{{ old('actual_hours', $project->actual_hours) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('admin.projects.show', $project) }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Cancel</a>
                <button type="submit" class="rounded-full bg-slate-900 px-5 py-2 text-sm font-semibold text-white hover:bg-slate-800">Update project</button>
            </div>
        </form>
    </div>
@endsection
