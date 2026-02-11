@extends('layouts.admin')

@section('title', 'Add Maintenance')
@section('page-title', 'Add Maintenance')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Projects</div>
            <div class="text-2xl font-semibold text-slate-900">Create maintenance</div>
            <div class="text-sm text-slate-500">Set up recurring billing for a project.</div>
        </div>
        <a href="{{ route('admin.project-maintenances.index') }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600" hx-boost="false">Back to maintenance</a>
    </div>

    <div class="card p-6 max-w-full">
        <form method="POST" action="{{ route('admin.project-maintenances.store') }}" class="space-y-4">
            @csrf
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-xs text-slate-500">Project</label>
                    <select name="project_id" id="project_id" required class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="">Select project</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" data-currency="{{ $project->currency }}" @selected(old('project_id', $selectedProjectId) == $project->id)>
                                {{ $project->name }} ({{ $project->customer?->name ?? 'No customer' }})
                            </option>
                        @endforeach
                    </select>
                    <div class="mt-2 text-xs text-slate-500">Currency: <span id="projectCurrency">{{ old('project_id', $selectedProjectId) ? ($projects->firstWhere('id', (int) old('project_id', $selectedProjectId))?->currency ?? '--') : '--' }}</span></div>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Title</label>
                    <input name="title" value="{{ old('title') }}" required class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" placeholder="Annual Hosting & Support">
                </div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-xs text-slate-500">Amount</label>
                    <input name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount') }}" required class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Billing cycle</label>
                    <select name="billing_cycle" required class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="monthly" @selected(old('billing_cycle') === 'monthly')>Monthly</option>
                        <option value="yearly" @selected(old('billing_cycle') === 'yearly')>Yearly</option>
                    </select>
                </div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-xs text-slate-500">Sales representatives</label>
                    <div class="mt-2 space-y-2 rounded-2xl border border-slate-300 bg-white/80 p-3">
                        @if($salesReps->isEmpty())
                            <div class="text-xs text-slate-500">No active sales representatives found.</div>
                        @else
                            @foreach($salesReps as $rep)
                                @php
                                    $selectedSalesReps = collect(old('sales_rep_ids', []));
                                    $repAmount = old('sales_rep_amounts.'.$rep->id, 0);
                                @endphp
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <label class="flex items-center gap-2 text-xs text-slate-600">
                                        <input type="checkbox" name="sales_rep_ids[]" value="{{ $rep->id }}" @checked($selectedSalesReps->contains($rep->id))>
                                        <span>{{ $rep->name }} ({{ $rep->email }})</span>
                                    </label>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs text-slate-500">Amount</span>
                                        <input type="number" min="0" step="0.01" name="sales_rep_amounts[{{ $rep->id }}]" value="{{ $repAmount }}" class="w-28 rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs">
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                    <p class="mt-1 text-xs text-slate-500">Amounts apply only to selected sales reps.</p>
                </div>
                <div class="flex items-end">
                    <label class="flex items-center gap-2 text-xs text-slate-600 cursor-pointer">
                        <input type="hidden" name="auto_invoice" value="0">
                        <input type="checkbox" name="auto_invoice" value="1" @checked(old('auto_invoice', true))>
                        <span>Auto-generate invoice</span>
                    </label>
                    <label class="flex items-center gap-2 text-xs text-slate-600 cursor-pointer ml-6">
                        <input type="hidden" name="sales_rep_visible" value="0">
                        <input type="checkbox" name="sales_rep_visible" value="1" @checked(old('sales_rep_visible'))>
                        <span>Visible to sales reps</span>
                    </label>
                </div>                
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-xs text-slate-500">Start date</label>
                    <input name="start_date" type="date" value="{{ old('start_date') }}" required class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Status</label>
                    <select name="status" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                        <option value="paused" @selected(old('status') === 'paused')>Paused</option>
                        <option value="cancelled" @selected(old('status') === 'cancelled')>Cancelled</option>
                    </select>
                </div>
            </div>
            
            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('admin.project-maintenances.index') }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Cancel</a>
                <button type="submit" class="rounded-full bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Create maintenance</button>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const projectSelect = document.getElementById('project_id');
            const currencyLabel = document.getElementById('projectCurrency');
            const updateCurrency = () => {
                const selected = projectSelect?.selectedOptions?.[0];
                const currency = selected?.dataset?.currency || '--';
                if (currencyLabel) {
                    currencyLabel.textContent = currency;
                }
            };
            projectSelect?.addEventListener('change', updateCurrency);
            updateCurrency();
        });
    </script>
@endsection
