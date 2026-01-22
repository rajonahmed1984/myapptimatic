@extends('layouts.client')

@section('title', 'Project #'.$project->id)
@section('page-title', 'Project')

@section('content')
    @php
        $financials = $financials ?? [
            'budget' => $project->total_budget ?? $project->budget_amount,
            'initial_payment' => $project->initial_payment_amount ?? null,
            'overhead_total' => (float) ($project->overhead_total ?? 0),
            'budget_with_overhead' => null,
        ];
        if ($financials['budget_with_overhead'] === null && $financials['budget'] !== null) {
            $financials['budget_with_overhead'] = (float) $financials['budget'] + (float) ($financials['overhead_total'] ?? 0);
        }
        $currencyCode = $project->currency ?? '';
    @endphp
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Project</div>
            <div class="text-2xl font-semibold text-slate-900">{{ $project->name }}</div>
            <div class="text-sm text-slate-500">Status: {{ ucfirst(str_replace('_', ' ', $project->status)) }}</div>
        </div>
    </div>

    <div class="grid gap-6">
        <div class="card p-6">
            <div class="grid gap-4 md:grid-cols-2 text-sm text-slate-700">
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Project ID & Dates</div>
                <div class="mt-2 font-semibold text-slate-900">#{{ $project->id }}</div>
                <div class="mt-2 text-sm text-slate-700">
                    Start: {{ $project->start_date?->format($globalDateFormat) ?? '--' }}<br>
                    Expected end: {{ $project->expected_end_date?->format($globalDateFormat) ?? '--' }}<br>
                    Due: {{ $project->due_date?->format($globalDateFormat) ?? '--' }}
                </div>
            </div>            
            @if(!$isProjectSpecificUser)
                <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Financials</div>
                    <div class="mt-2 text-sm text-slate-700">
                        Budget: {{ $financials['budget'] !== null ? $currencyCode.' '.number_format((float) $financials['budget'], 2) : '--' }}<br>
                        Initial payment: {{ $financials['initial_payment'] !== null ? $currencyCode.' '.number_format((float) $financials['initial_payment'], 2) : '--' }}<br>
                        Total overhead: {{ $currencyCode }} {{ number_format((float) ($financials['overhead_total'] ?? 0), 2) }}<br>
                        Budget with overhead: {{ $financials['budget_with_overhead'] !== null ? $currencyCode.' '.number_format((float) $financials['budget_with_overhead'], 2) : '--' }}
                    </div>
                @if(!empty($initialInvoice))
                    <div class="mt-2 text-xs text-slate-500">
                        Initial invoice: #{{ $initialInvoice->number ?? $initialInvoice->id }} ({{ ucfirst($initialInvoice->status) }})
                        <a href="{{ route('client.invoices.show', $initialInvoice) }}" class="text-teal-700 hover:text-teal-600">View invoice</a>
                    </div>
                @endif
                </div>
            @endif
        </div>

        @if(!empty($maintenances) && $maintenances->isNotEmpty() && !$isProjectSpecificUser)
            <div class="mt-6 rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400 mb-2">Maintenance</div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                        <tr class="text-xs uppercase tracking-[0.2em] text-slate-500">
                            <th class="px-3 py-2">Title</th>
                            <th class="px-3 py-2">Cycle</th>
                            <th class="px-3 py-2">Next Billing</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2 text-right">Amount</th>
                            <th class="px-3 py-2 text-right">Invoices</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($maintenances as $maintenance)
                            @php $latestInvoice = $maintenance->invoices->first(); @endphp
                            <tr class="border-t border-slate-100">
                                <td class="px-3 py-2">{{ $maintenance->title }}</td>
                                <td class="px-3 py-2">{{ ucfirst($maintenance->billing_cycle) }}</td>
                                <td class="px-3 py-2 text-xs text-slate-600">{{ $maintenance->next_billing_date?->format($globalDateFormat) ?? '--' }}</td>
                                <td class="px-3 py-2">
                                    <span class="rounded-full border px-2 py-0.5 text-xs font-semibold {{ $maintenance->status === 'active' ? 'border-emerald-200 text-emerald-700 bg-emerald-50' : ($maintenance->status === 'paused' ? 'border-amber-200 text-amber-700 bg-amber-50' : 'border-slate-200 text-slate-600 bg-slate-50') }}">
                                        {{ ucfirst($maintenance->status) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-right">{{ $maintenance->currency }} {{ number_format((float) $maintenance->amount, 2) }}</td>
                                <td class="px-3 py-2 text-right text-xs text-slate-600">
                                    {{ $maintenance->invoices?->count() ?? 0 }}
                                    @if($latestInvoice)
                                        <div>
                                            <a href="{{ route('client.invoices.show', $latestInvoice) }}" class="text-teal-700 hover:text-teal-600">Latest #{{ $latestInvoice->number ?? $latestInvoice->id }}</a>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="mt-6 rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Add Task</div>
                    <div class="text-xs text-slate-500">Dates and assignment are managed internally and locked after creation.</div>
                </div>
            </div>
            <form method="POST" action="{{ route('client.projects.tasks.store', $project) }}" class="mt-4 grid gap-3 md:grid-cols-3" enctype="multipart/form-data">
                @csrf
                <div class="md:col-span-2">
                    <label class="text-xs text-slate-500">Title</label>
                    <input name="title" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
                <div class="md:col-span-1">
                    <label class="text-xs text-slate-500">Task type</label>
                    <select name="task_type" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" required>
                        @foreach($taskTypeOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>                
                <div class="md:col-span-3">
                    <label class="text-xs text-slate-500">Description</label>
                    <input name="description" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
                <div class="md:col-span-1">
                    <label class="text-xs text-slate-500">Priority</label>
                    <select name="priority" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        @foreach($priorityOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-1">
                    <label class="text-xs text-slate-500">Attachment (required for Upload type)</label>
                    <input type="file" name="attachment" accept=".png,.jpg,.jpeg,.webp,.pdf,.docx,.xlsx" class="mt-1 w-full text-xs text-slate-600">
                </div>
                <div class="md:col-span-3 flex justify-end">
                    <button type="submit" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Add task</button>
                </div>
            </form>
        </div>

        @if($tasks && $tasks->isNotEmpty())
            <div class="mt-6 rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400 mb-2">Tasks (customer-visible)</div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                        <tr class="text-xs uppercase tracking-[0.2em] text-slate-500">
                            <th class="px-3 py-2">Task</th>
                            <th class="px-3 py-2">Dates</th>
                            <th class="px-3 py-2 text-right">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($tasks as $task)
                            @php
                                $currentUser = auth()->user();
                                $canEditTask = $currentUser?->isMasterAdmin()
                                    || ($task->created_by
                                        && $currentUser
                                        && $task->created_by === $currentUser->id
                                        && ! $task->creatorEditWindowExpired($currentUser->id));
                            @endphp
                            <tr class="border-t border-slate-100 align-top">
                                <td class="px-3 py-2">
                                    <div class="font-semibold text-slate-900">
                                        <a href="{{ route('client.projects.tasks.show', [$project, $task]) }}" class="text-xs font-semibold text-teal-600 hover:text-teal-500"> {{ $task->title }}</a>                                       
                                    </div>
                                    <div class="mt-1 text-[11px] uppercase tracking-[0.18em] text-slate-400">
                                        {{ $taskTypeOptions[$task->task_type] ?? ucfirst($task->task_type ?? 'Task') }}
                                    </div>
                                    @if($task->description)
                                        <div class="text-xs text-slate-500">{{ $task->description }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-xs text-slate-600">
                                    Start: {{ $task->start_date?->format($globalDateFormat) ?? '--' }}<br>
                                    Due: {{ $task->due_date?->format($globalDateFormat) ?? '--' }}
                                </td>
                                <td class="px-3 py-2 text-right align-top">
                                    <a href="{{ route('client.projects.tasks.show', [$project, $task]) }}" class="text-xs font-semibold text-teal-600 hover:text-teal-500">Open Task</a>
                                    @if($canEditTask)
                                        <span class="mx-2 text-slate-300">|</span>
                                        <a href="{{ route('client.projects.tasks.show', [$project, $task]) }}#task-edit" class="text-xs font-semibold text-teal-600 hover:text-teal-500">Edit</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
    </div>

@endsection
