@extends('layouts.admin')

@section('title', $rep->name)
@section('page-title', $rep->name)

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Sales Representative</div>
            <div class="text-2xl font-semibold text-slate-900">{{ $rep->name }}</div>
            <div class="text-sm text-slate-500">{{ $rep->email ?? 'No email on file' }}</div>
        </div>
        <div class="flex flex-wrap gap-3">
            <form action="{{ route('admin.sales-reps.impersonate', $rep) }}" method="POST">
                @csrf
                <button type="submit" class="rounded-full border border-teal-200 px-4 py-2 text-sm font-semibold text-teal-700 hover:border-teal-300 hover:text-teal-800">
                    Login as Sales Representative
                </button>
            </form>
            <a href="{{ route('admin.sales-reps.edit', $rep) }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Edit</a>
            <a href="{{ route('admin.sales-reps.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Back to list</a>
        </div>
    </div>

    <div class="mb-6 flex flex-wrap gap-3 text-sm font-semibold text-slate-700">
        @php
            $tabs = [
                'profile' => 'Profile',
                'services' => 'Products / Services',
                'invoices' => 'Invoices',
                'emails' => 'Emails',
                'log' => 'Log',
                'earnings' => 'Recent Earnings',
                'payouts' => 'Recent Payouts',
                'projects' => 'Projects',
            ];
        @endphp
        @foreach($tabs as $key => $label)
            <a href="{{ route('admin.sales-reps.show', ['sales_rep' => $rep->id, 'tab' => $key]) }}"
               class="rounded-full border px-3 py-1 {{ $tab === $key ? 'border-teal-500 bg-teal-50 text-teal-700' : 'border-slate-200 text-slate-700 hover:border-teal-300 hover:text-teal-700' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    @if($tab === 'profile')
        <div class="grid gap-4 md:grid-cols-3">
            <div class="card p-4">
                <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Total Earned</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($summary['total_earned'] ?? 0, 2) }}</div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Payable</div>
                <div class="mt-2 text-2xl font-semibold text-amber-700">{{ number_format($summary['payable'] ?? 0, 2) }}</div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Paid</div>
                <div class="mt-2 text-2xl font-semibold text-emerald-700">{{ number_format($summary['paid'] ?? 0, 2) }}</div>
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
                            <span class="rounded-full border px-2 py-0.5 text-xs font-semibold {{ $rep->status === 'active' ? 'border-emerald-200 text-emerald-700 bg-emerald-50' : 'border-slate-200 text-slate-600 bg-slate-50' }}">
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
        </div>
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
        <div class="card p-4">
            <div class="mb-3 flex items-center justify-between">
                <div class="text-sm font-semibold text-slate-800">Recent Earnings</div>
                <a href="{{ route('admin.commission-payouts.create', ['sales_rep_id' => $rep->id]) }}" class="text-xs font-semibold text-teal-700 hover:text-teal-600">
                    Pay payable ({{ number_format($summary['payable'] ?? 0, 2) }})
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[500px] text-sm text-slate-700">
                    <thead class="border-b border-slate-200 text-xs uppercase tracking-[0.2em] text-slate-500">
                        <tr>
                            <th class="py-2 text-left">Date</th>
                            <th class="py-2 text-left">Status</th>
                            <th class="py-2 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentEarnings as $earning)
                            <tr class="border-b border-slate-100">
                                <td class="py-2">{{ $earning->created_at?->format($globalDateFormat ?? 'Y-m-d') }}</td>
                                <td class="py-2">{{ ucfirst($earning->status) }}</td>
                                <td class="py-2 text-right">{{ number_format($earning->commission_amount, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-4 text-center text-slate-500">No earnings yet.</td>
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
                    <thead class="border-b border-slate-200 text-xs uppercase tracking-[0.2em] text-slate-500">
                        <tr>
                            <th class="py-2 text-left">Date</th>
                            <th class="py-2 text-left">Method</th>
                            <th class="py-2 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentPayouts as $payout)
                            <tr class="border-b border-slate-100">
                                <td class="py-2">{{ $payout->created_at?->format($globalDateFormat ?? 'Y-m-d') }}</td>
                                <td class="py-2">{{ ucfirst($payout->method ?? 'manual') }}</td>
                                <td class="py-2 text-right">{{ number_format($payout->amount ?? 0, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-4 text-center text-slate-500">No payouts yet.</td>
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
                        <span class="rounded-full border border-slate-200 bg-white px-2 py-1">
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
                                    <span class="rounded-full border border-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-700 bg-slate-50">
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
                                            <span class="rounded-full border border-slate-200 bg-white px-2 py-1">
                                                {{ ucfirst(str_replace('_', ' ', $status)) }}: {{ $taskCounts[$status] ?? 0 }}
                                            </span>
                                        @endforeach
                                        @foreach($extraTaskCounts as $status => $count)
                                            <span class="rounded-full border border-slate-200 bg-white px-2 py-1">
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
