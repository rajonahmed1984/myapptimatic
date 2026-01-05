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
                    <label class="text-xs text-slate-500">Subscription (optional)</label>
                    <select name="subscription_id" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="">-- none --</option>
                        @foreach($subscriptions as $subscription)
                            <option value="{{ $subscription->id }}" @selected(old('subscription_id', $project->subscription_id) == $subscription->id)>Subscription #{{ $subscription->id }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Advance invoice (optional)</label>
                    <select name="advance_invoice_id" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="">-- none --</option>
                        @foreach($invoices as $invoice)
                            <option value="{{ $invoice->id }}" @selected(old('advance_invoice_id', $project->advance_invoice_id) == $invoice->id)>
                                {{ is_numeric($invoice->number) ? $invoice->number : $invoice->id }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label class="text-xs text-slate-500">Final invoice (optional)</label>
                    <select name="final_invoice_id" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="">-- none --</option>
                        @foreach($invoices as $invoice)
                            <option value="{{ $invoice->id }}" @selected(old('final_invoice_id', $project->final_invoice_id) == $invoice->id)>
                                {{ is_numeric($invoice->number) ? $invoice->number : $invoice->id }}
                            </option>
                        @endforeach
                    </select>
                </div>
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
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-xs text-slate-500">Due date</label>
                    <input name="due_date" type="date" value="{{ old('due_date', optional($project->due_date)->format('Y-m-d')) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Notes</label>
                    <textarea name="notes" rows="2" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">{{ old('notes', $project->notes) }}</textarea>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-4">
                <div>
                    <label class="text-xs text-slate-500">Budget amount</label>
                    <input name="budget_amount" type="number" step="0.01" value="{{ old('budget_amount', $project->budget_amount) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
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
