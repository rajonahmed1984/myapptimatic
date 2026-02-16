@extends('layouts.admin')

@section('title', $rep->name)
@section('page-title', $rep->name)

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Sales Representative</div>
            <div class="text-sm text-slate-500">{{ $rep->email ?? 'No email on file' }}</div>
        </div>
        <div class="flex flex-wrap gap-3">
            <form action="{{ route('admin.sales-reps.impersonate', $rep) }}" method="POST">
                @csrf
                <button type="submit" class="rounded-full border border-teal-200 px-4 py-2 text-sm font-semibold text-teal-700 hover:border-teal-300 hover:text-teal-800">
                    Login as Sales Representative
                </button>
            </form>
            <a href="{{ route('admin.sales-reps.edit', $rep) }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Edit</a>
            <a href="{{ route('admin.sales-reps.index') }}" hx-boost="false" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Back to list</a>
        </div>
    </div>

    <div class="mb-6 flex flex-wrap gap-3 text-sm font-semibold text-slate-700">
        @php
            $tabs = [
                'profile' => 'Profile',
                'services' => 'Products / Services',
                'projects' => 'Projects',
                'invoices' => 'Invoices',
                'earnings' => 'Recent Earnings',
                'payouts' => 'Recent Payouts',
                'emails' => 'Emails',
                'log' => 'Log',
            ];
        @endphp
        @foreach($tabs as $key => $label)
            <a href="{{ route('admin.sales-reps.show', ['sales_rep' => $rep->id, 'tab' => $key]) }}"
               class="rounded-full border px-3 py-1 {{ $tab === $key ? 'border-teal-500 bg-teal-50 text-teal-700' : 'border-slate-300 text-slate-700 hover:border-teal-300 hover:text-teal-700' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    @if($tab === 'profile')
        @php
            $payableNet = (float) ($summary['payable'] ?? 0);
            $payableClass = $payableNet > 0
                ? 'text-amber-700'
                : ($payableNet < 0 ? 'text-rose-700' : 'text-slate-900');
        @endphp
        <div class="grid gap-4 md:grid-cols-4">
            <div class="card p-4">
                <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Total Earned</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($summary['total_earned'] ?? 0, 2) }}</div>
                <div class="text-xs text-slate-500">
                    Projects: {{ number_format($summary['project_earned'] ?? 0, 2) }}
                    | Maintenances: {{ number_format($summary['maintenance_earned'] ?? 0, 2) }}
                </div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Payable (Net)</div>
                <div class="mt-2 text-2xl font-semibold {{ $payableClass }}">{{ number_format($summary['payable'] ?? 0, 2) }}</div>
                <div class="text-xs text-slate-500">{{ $summary['payable_label'] ?? 'Settled' }}</div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Paid (Incl. Advance)</div>
                <div class="mt-2 text-2xl font-semibold text-emerald-700">{{ number_format($summary['paid'] ?? 0, 2) }}</div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Advance Paid</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($summary['advance_paid'] ?? 0, 2) }}</div>
                @if(($summary['overpaid'] ?? 0) > 0)
                    <div class="text-xs text-rose-600">Overpaid: {{ number_format($summary['overpaid'] ?? 0, 2) }}</div>
                @endif
            </div>
        </div>
        <div class="mt-6 grid gap-4 md:grid-cols-4">
            <div class="card p-4 md:col-span-2">
                <div class="mb-3 flex items-center justify-between">
                    <div class="text-sm font-semibold text-slate-800">Profile</div>
                </div>
                <dl class="grid grid-cols-2 gap-3 text-sm text-slate-700">
                    <div>
                        <dt class="text-xs uppercase tracking-[0.2em] text-slate-500">Status</dt>
                        <dd class="mt-1">
                            <span class="rounded-full border px-2 py-0.5 text-xs font-semibold {{ $rep->status === 'active' ? 'border-emerald-200 text-emerald-700 bg-emerald-50' : 'border-slate-300 text-slate-600 bg-slate-50' }}">
                                {{ ucfirst($rep->status) }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-[0.2em] text-slate-500">User</dt>
                        <dd class="mt-1 text-slate-800">{{ $rep->user?->name ?? '--' }} <span class="text-slate-500">{{ $rep->user?->email }}</span></dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-[0.2em] text-slate-500">Employee</dt>
                        <dd class="mt-1 text-slate-800">{{ $rep->employee?->name ?? 'Not linked' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-[0.2em] text-slate-500">Phone</dt>
                        <dd class="mt-1 text-slate-800">{{ $rep->phone ?? '--' }}</dd>
                    </div>
                </dl>
            </div>
            <div class="card p-4 md:col-span-2">
                <div class="mb-3 flex items-center justify-between">
                    <div class="text-sm font-semibold text-slate-800">Documents</div>
                </div>
                <div class="grid gap-4 md:grid-cols-3 text-sm text-slate-700">
                    <div>
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Avatar</div>
                        <div class="mt-2">
                            <x-avatar :path="$rep->avatar_path" :name="$rep->name" size="h-16 w-16" textSize="text-sm" />
                        </div>
                    </div>
                    @if($rep->nid_path)
                        @php
                            $nidIsImage = \Illuminate\Support\Str::endsWith(strtolower($rep->nid_path), ['.jpg', '.jpeg', '.png', '.webp']);
                            $nidUrl = route('admin.user-documents.show', ['type' => 'sales-rep', 'id' => $rep->id, 'doc' => 'nid'], false);
                        @endphp
                        <div>
                            <div class="text-xs uppercase tracking-[0.2em] text-slate-500">NID</div>
                            <div class="mt-2 flex items-center gap-3">
                                @if($nidIsImage)
                                    <img src="{{ $nidUrl }}" alt="NID" class="h-16 w-20 rounded-lg object-cover border border-slate-300">
                                @else
                                    <div class="flex h-16 w-20 items-center justify-center rounded-lg border border-slate-300 bg-slate-50 text-xs font-semibold text-slate-500">PDF</div>
                                @endif
                                <a href="{{ $nidUrl }}" class="text-sm text-teal-600 hover:text-teal-500">View/Download</a>
                            </div>
                        </div>
                    @endif
                    @if($rep->cv_path)
                        @php
                            $cvUrl = route('admin.user-documents.show', ['type' => 'sales-rep', 'id' => $rep->id, 'doc' => 'cv'], false);
                        @endphp
                        <div>
                            <div class="text-xs uppercase tracking-[0.2em] text-slate-500">CV</div>
                            <div class="mt-2 flex items-center gap-3">
                                <div class="flex h-16 w-20 items-center justify-center rounded-lg border border-slate-300 bg-slate-50 text-xs font-semibold text-slate-500">PDF</div>
                                <a href="{{ $cvUrl }}" class="text-sm text-teal-600 hover:text-teal-500">View/Download</a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="mt-4 card p-4">
            <div class="text-sm font-semibold text-slate-800">Record advance payment</div>
            <div class="text-xs text-slate-500">Advance payments are deducted from future commissions.</div>
            <form method="POST" action="{{ route('admin.sales-reps.advance-payment', $rep) }}" class="mt-3 grid gap-3 md:grid-cols-8">
                @csrf
                <div class="md:col-span-2">
                    <label class="text-xs text-slate-500">Project filter</label>
                    <select id="advanceProjectFilter" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="all">All projects</option>
                        <option value="active">Active projects</option>
                        <option value="complete">Completed projects</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="text-xs text-slate-500">Project</label>
                    <select id="advanceProjectSelect" name="project_id" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="">Select project</option>
                        @foreach($advanceProjects ?? [] as $projectOption)
                            <option value="{{ $projectOption->id }}" data-status="{{ $projectOption->status ?? '' }}" @selected(old('project_id') == $projectOption->id)>
                                {{ $projectOption->name }} @if($projectOption->customer) ({{ $projectOption->customer->name }}) @endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Amount</label>
                    <input name="amount" type="number" step="0.01" min="0" required value="{{ old('amount') }}" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" placeholder="0.00">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Currency</label>
                    <input name="currency" value="{{ old('currency', 'BDT') }}" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" placeholder="BDT">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Method</label>
                    @php
                        $paymentMethods = \App\Models\PaymentMethod::commissionPayoutDropdownOptions();
                    @endphp
                    <select name="payout_method" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="">Select</option>
                        @foreach($paymentMethods as $method)
                            <option value="{{ $method->code }}" @selected(old('payout_method') === $method->code)>{{ $method->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Reference</label>
                    <input name="reference" value="{{ old('reference') }}" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" placeholder="Txn / Note">
                </div>
                <div class="md:col-span-8">
                    <label class="text-xs text-slate-500">Note</label>
                    <input name="note" value="{{ old('note') }}" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" placeholder="Optional note">
                </div>
                <div class="md:col-span-8 flex items-center gap-3">
                    <button type="submit" class="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Save advance payment</button>
                </div>
            </form>
        </div>
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const filter = document.getElementById('advanceProjectFilter');
                    const select = document.getElementById('advanceProjectSelect');

                    if (!filter || !select) return;

                    const applyFilter = () => {
                        const value = filter.value;
                        const activeStatuses = ['ongoing'];
                        const completeStatuses = ['complete'];

                        [...select.options].forEach((option) => {
                            const status = option.dataset.status || '';
                            if (!status) {
                                option.hidden = false;
                                option.disabled = false;
                                return;
                            }

                            const show = value === 'all'
                                || (value === 'active' && activeStatuses.includes(status))
                                || (value === 'complete' && completeStatuses.includes(status));

                            option.hidden = !show;
                            option.disabled = !show;
                        });

                        if (select.selectedOptions.length && select.selectedOptions[0].hidden) {
                            select.value = '';
                        }
                    };

                    filter.addEventListener('change', applyFilter);
                    applyFilter();
                });
            </script>
        @endpush
    @elseif($tab === 'services')
        <div class="card p-6">
            <div class="text-sm font-semibold text-slate-800 mb-3">Products / Services</div>
            @if($subscriptions->isEmpty())
                <div class="text-sm text-slate-600">No linked products or services for this rep.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="text-xs uppercase tracking-[0.2em] text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Subscription</th>
                            <th class="px-3 py-2">Customer</th>
                            <th class="px-3 py-2">Plan</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Next Invoice</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($subscriptions as $subscription)
                            <tr class="border-t border-slate-100">
                                <td class="px-3 py-2">#{{ $subscription->id }}</td>
                                <td class="px-3 py-2">{{ $subscription->customer?->name ?? '--' }}</td>
                                <td class="px-3 py-2">{{ $subscription->plan?->name ?? '--' }}</td>
                                <td class="px-3 py-2">{{ ucfirst($subscription->status ?? '--') }}</td>
                                <td class="px-3 py-2 text-xs text-slate-600">{{ $subscription->next_invoice_at?->format($globalDateFormat ?? 'Y-m-d') ?? '--' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @elseif($tab === 'invoices')
        <div class="card p-6">
            <div class="text-sm font-semibold text-slate-800 mb-3">Invoices</div>
            @if($invoiceEarnings->isEmpty())
                <div class="text-sm text-slate-600">No invoices linked to this rep.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="text-xs uppercase tracking-[0.2em] text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Invoice</th>
                            <th class="px-3 py-2">Customer</th>
                            <th class="px-3 py-2">Project</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Total</th>
                            <th class="px-3 py-2">Issued</th>
                            <th class="px-3 py-2">Due</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($invoiceEarnings as $earning)
                            @php $invoice = $earning->invoice; @endphp
                            <tr class="border-t border-slate-100">
                                <td class="px-3 py-2">
                                    @if($invoice)
                                        <a class="text-teal-700 hover:text-teal-600" href="{{ route('admin.invoices.show', $invoice) }}">
                                            #{{ $invoice->number ?? $invoice->id }}
                                        </a>
                                    @else
                                        --
                                    @endif
                                </td>
                                <td class="px-3 py-2">{{ $invoice?->customer?->name ?? '--' }}</td>
                                <td class="px-3 py-2">{{ $earning->project?->name ?? '--' }}</td>
                                <td class="px-3 py-2">{{ ucfirst($invoice->status ?? '--') }}</td>
                                <td class="px-3 py-2">{{ $invoice?->currency ?? '' }} {{ number_format($invoice?->total ?? 0, 2) }}</td>
                                <td class="px-3 py-2 text-xs text-slate-600">{{ $invoice?->issue_date?->format($globalDateFormat ?? 'Y-m-d') ?? '--' }}</td>
                                <td class="px-3 py-2 text-xs text-slate-600">{{ $invoice?->due_date?->format($globalDateFormat ?? 'Y-m-d') ?? '--' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @elseif($tab === 'emails')
        <div class="card p-6 text-sm text-slate-600">
            No email history available.
        </div>
    @elseif($tab === 'log')
        <div class="card p-6 text-sm text-slate-600">
            No activity log entries.
        </div>
    @elseif($tab === 'earnings')
        <div class="grid gap-4 md:grid-cols-3">
            <div class="card p-4">
                <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Earned Amount</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">
                    {{ number_format($summary['total_earned'] ?? 0, 2) }}
                </div>
                <div class="text-xs text-slate-500">Includes pending, payable, and paid commission.</div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Outstanding</div>
                <div class="mt-2 text-2xl font-semibold text-amber-700">
                    {{ number_format($summary['outstanding'] ?? (($summary['total_earned'] ?? 0) - ($summary['paid'] ?? 0)), 2) }}
                </div>
                <div class="text-xs text-slate-500">Amount yet to be paid (total minus paid).</div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Payable (Net)</div>
                <div class="mt-2 text-2xl font-semibold text-emerald-700">
                    {{ number_format($summary['payable'] ?? 0, 2) }}
                </div>
                <div class="text-xs text-slate-500">Ready for payout after advances.</div>
            </div>
        </div>
        <div class="mt-4 card p-4">
            <div class="mb-3 flex items-center justify-between">
                <div class="text-sm font-semibold text-slate-800">Recent Earnings</div>
                <a href="{{ route('admin.commission-payouts.create', ['sales_rep_id' => $rep->id]) }}" class="text-xs font-semibold text-teal-700 hover:text-teal-600">
                    Pay payable ({{ number_format($summary['payable'] ?? 0, 2) }})
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-sm text-slate-700">
                    <thead class="border-b border-slate-300 text-xs uppercase tracking-[0.2em] text-slate-500">
                        <tr>
                            <th class="py-2 text-left">Date</th>
                            <th class="py-2 text-left">Status</th>
                            <th class="py-2 text-left">Source</th>
                            <th class="py-2 text-left">Details</th>
                            <th class="py-2 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentEarnings as $earning)
                            @php
                                $sourceLabel = match($earning->source_type) {
                                    'invoice' => 'Invoice',
                                    'subscription' => 'Subscription',
                                    'project' => 'Project',
                                    default => 'Commission',
                                };
                                $details = match($sourceLabel) {
                                    'Invoice' => 'Inv #' . ($earning->invoice?->number ?? $earning->invoice?->id ?? '—') . ' / ' . ($earning->invoice?->customer?->name ?? '--'),
                                    'Subscription' => $earning->subscription?->plan?->name ?? '--',
                                    'Project' => $earning->project?->name ?? '--',
                                    default => $earning->metadata['description'] ?? '--',
                                };
                            @endphp
                            <tr class="border-b border-slate-100">
                                <td class="py-2">{{ $earning->created_at?->format($globalDateFormat ?? 'Y-m-d') ?? '--' }}</td>
                                <td class="py-2">{{ ucfirst($earning->status) }}</td>
                                <td class="py-2">{{ $sourceLabel }}</td>
                                <td class="py-2 text-xs text-slate-600">{{ $details }}</td>
                                <td class="py-2 text-right">
                                    {{ $earning->currency ?? '' }} {{ number_format($earning->commission_amount, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-4 text-center text-slate-500">No earnings yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @elseif($tab === 'payouts')
        <div class="card p-4">
            <div class="mb-3 flex items-center justify-between">
                <div class="text-sm font-semibold text-slate-800">Recent Payouts</div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[500px] text-sm text-slate-700">
                    <thead class="border-b border-slate-300 text-xs uppercase tracking-[0.2em] text-slate-500">
                        <tr>
                            <th class="py-2 text-left">Date</th>
                            <th class="py-2 text-left">Type</th>
                            <th class="py-2 text-left">Method</th>
                            <th class="py-2 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentPayouts as $payout)
                            <tr class="border-b border-slate-100">
                                <td class="py-2">{{ $payout->created_at?->format($globalDateFormat ?? 'Y-m-d') }}</td>
                                <td class="py-2">{{ ucfirst($payout->type ?? 'regular') }}</td>
                                <td class="py-2">{{ ucfirst($payout->payout_method ?? 'manual') }}</td>
                                <td class="py-2 text-right">{{ number_format($payout->total_amount ?? 0, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-4 text-center text-slate-500">No payouts yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @elseif($tab === 'projects')
        @php
            $projectStatusLabels = [
                'ongoing' => 'Ongoing',
                'hold' => 'On hold',
                'complete' => 'Completed',
                'cancel' => 'Cancelled',
            ];
            $taskStatusOrder = ['pending', 'in_progress', 'blocked', 'completed'];
        @endphp
        <div class="grid gap-4 md:grid-cols-4">
            <div class="card p-4 md:col-span-4">
                <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Assigned Projects</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $projects->count() }}</div>
                <div class="mt-3 flex flex-wrap gap-2 text-xs text-slate-600">
                    @foreach($projectStatusLabels as $status => $label)
                        <span class="rounded-full border border-slate-300 bg-white px-2 py-1">
                            {{ $label }}: {{ $projectStatusCounts[$status] ?? 0 }}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="mt-4 card p-6">
            <div class="text-sm font-semibold text-slate-800 mb-3">Projects</div>
            @if($projects->isEmpty())
                <div class="text-sm text-slate-500">No projects assigned to this sales rep.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                        <tr class="text-xs uppercase tracking-[0.2em] text-slate-500">
                            <th class="px-3 py-2">Project</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Customer</th>
                            <th class="px-3 py-2">Assigned Tasks</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($projects as $project)
                            @php
                                $taskCounts = $projectTaskStatusCounts->get($project->id, collect());
                                $taskTotal = $taskCounts->sum();
                                $extraTaskCounts = $taskCounts->except($taskStatusOrder);
                                $projectStatus = strtolower((string) ($project->status ?? ''));
                                $projectStatusClasses = match ($projectStatus) {
                                    'complete' => 'border-emerald-200 text-emerald-700 bg-emerald-50',
                                    'ongoing' => 'border-amber-200 text-amber-700 bg-amber-50',
                                    'hold' => 'border-slate-300 text-slate-700 bg-slate-100',
                                    'cancel', 'cancelled' => 'border-rose-200 text-rose-700 bg-rose-50',
                                    default => 'border-slate-300 text-slate-700 bg-slate-50',
                                };
                            @endphp
                            <tr class="border-t border-slate-100 align-top">
                                <td class="px-3 py-2">
                                    <div class="font-semibold text-slate-900">
                                        <a class="text-teal-700 hover:text-teal-600" href="{{ route('admin.projects.show', $project) }}">
                                            {{ $project->name }}
                                        </a>
                                    </div>
                                    <div class="text-xs text-slate-500">Project ID: {{ $project->id }}</div>
                                </td>
                                <td class="px-3 py-2">
                                    <span class="rounded-full border px-2 py-0.5 text-xs font-semibold {{ $projectStatusClasses }}">
                                        {{ ucfirst(str_replace('_', ' ', $project->status)) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-sm text-slate-700">
                                    {{ $project->customer?->name ?? '--' }}
                                </td>
                                <td class="px-3 py-2 text-xs text-slate-600">
                                    <div class="font-semibold text-slate-700">Assigned tasks: {{ $taskTotal }}</div>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach($taskStatusOrder as $status)
                                            <span class="rounded-full border border-slate-300 bg-white px-2 py-1">
                                                {{ ucfirst(str_replace('_', ' ', $status)) }}: {{ $taskCounts[$status] ?? 0 }}
                                            </span>
                                        @endforeach
                                        @foreach($extraTaskCounts as $status => $count)
                                            <span class="rounded-full border border-slate-300 bg-white px-2 py-1">
                                                {{ ucfirst(str_replace('_', ' ', $status)) }}: {{ $count }}
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
@endsection
