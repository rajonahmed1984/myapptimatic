@extends('layouts.admin')

@section('title', 'Project #'.$project->id)
@section('page-title', 'Project')

@section('content')
    @php
        $errors = $errors ?? new \Illuminate\Support\ViewErrorBag();
    @endphp

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="text-2xl font-semibold text-slate-900">{{ $project->name }}</div>
            <div class="text-sm text-slate-500">Status: {{ ucfirst(str_replace('_', ' ', $project->status)) }}</div>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.projects.index') }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600" hx-boost="false">Back</a>
            <a href="{{ route('admin.projects.invoices', $project) }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">All Invoices</a>
            <a href="{{ route('admin.projects.tasks.index', $project) }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Tasks</a>
            <a href="{{ route('admin.projects.chat', $project) }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">
                Chat
                @php $projectChatUnreadCount = (int) ($projectChatUnreadCount ?? 0); @endphp
                <span class="ml-2 inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $projectChatUnreadCount > 0 ? 'bg-rose-600 text-white' : 'bg-slate-200 text-slate-600' }}">
                    {{ $projectChatUnreadCount }}
                </span>
            </a>
            @if($project->status !== 'complete')
                <form method="POST" action="{{ route('admin.projects.complete', $project) }}" onsubmit="return confirm('Mark this project as complete?');">
                    @csrf
                    <button type="submit" class="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">
                        Project Complete
                    </button>
                </form>
            @endif
            <a href="{{ route('admin.projects.edit', $project) }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Edit</a>
            <form
                method="POST"
                action="{{ route('admin.projects.destroy', $project) }}"
                data-delete-confirm
                data-confirm-name="{{ $project->name }}"
                data-confirm-title="Delete project {{ $project->name }}?"
                data-confirm-description="This will permanently delete the project and related data."
            >
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-full border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-600 hover:border-rose-300">Delete</button>
            </form>
        </div>
    </div>

    @php
        $stats = $taskStats ?? ['total' => 0, 'in_progress' => 0, 'completed' => 0, 'unread' => 0];
    @endphp
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <div class="rounded-2xl border border-slate-300 bg-white/80 p-4">
            <div class="text-2xl font-semibold text-slate-900">{{ $stats['total'] }}</div>
            <div class="text-xs uppercase tracking-[0.25em] text-slate-500">Total Tasks</div>
        </div>
        <div class="rounded-2xl border border-slate-300 bg-white/80 p-4">
            <div class="text-2xl font-semibold text-slate-900">{{ $stats['in_progress'] }}</div>
            <div class="text-xs uppercase tracking-[0.25em] text-slate-500">In Progress</div>
        </div>
        <div class="rounded-2xl border border-slate-300 bg-white/80 p-4">
            <div class="text-2xl font-semibold text-slate-900">{{ $stats['completed'] }}</div>
            <div class="text-xs uppercase tracking-[0.25em] text-slate-500">Completed</div>
        </div>
        <div class="rounded-2xl border border-slate-300 bg-white/80 p-4">
            <div class="text-2xl font-semibold text-slate-900">{{ $stats['unread'] }}</div>
            <div class="text-xs uppercase tracking-[0.25em] text-slate-500">Unread</div>
        </div>
    </div>

    <div class="card p-6 mb-6" id="project-ai-summary">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="section-label">AI Project Summary</div>
                <div class="mt-1 text-sm text-slate-500">Quick project health, risks, and next steps.</div>
            </div>
            <div class="flex items-center gap-3">
                <span id="project-ai-status" class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">Ready</span>
                <button type="button" id="project-ai-generate" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800" @disabled(! $aiReady)>
                    Generate AI
                </button>
            </div>
        </div>

        @if(! $aiReady)
            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">
                GOOGLE_AI_API_KEY is missing. Add it to .env to enable AI summaries.
            </div>
        @endif

        <div class="mt-5 grid gap-4 md:grid-cols-2">
            <div class="rounded-2xl border border-slate-100 bg-white p-4 text-sm md:col-span-2">
                <div class="flex items-center justify-between">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Summary</div>
                    <span id="project-ai-health" class="rounded-full bg-slate-100 px-3 py-1 text-[11px] font-semibold text-slate-600">--</span>
                </div>
                <div id="project-ai-summary-text" class="mt-2 text-slate-700">Click Generate AI to analyze this project.</div>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white p-4 text-sm">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Highlights</div>
                <ul id="project-ai-highlights" class="mt-2 list-disc space-y-1 pl-4 text-slate-700">
                    <li>--</li>
                </ul>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white p-4 text-sm">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Risks</div>
                <ul id="project-ai-risks" class="mt-2 list-disc space-y-1 pl-4 text-slate-700">
                    <li>--</li>
                </ul>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white p-4 text-sm md:col-span-2">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Next steps</div>
                <ul id="project-ai-next-steps" class="mt-2 list-disc space-y-1 pl-4 text-slate-700">
                    <li>--</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="card p-6 space-y-6">
        <div>
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Project Info</div>
            <div class="mt-3 grid gap-4 md:grid-cols-3 text-sm text-slate-700">
                <div class="rounded-2xl border border-slate-300 bg-white/80 p-4">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Overview</div>
                    <div class="mt-2 font-semibold text-slate-900">{{ ucfirst($project->type) }}</div>
                    <div class="text-xs text-slate-500">
                        Project ID: {{ $project->id }}<br>
                        Status: {{ ucfirst(str_replace('_', ' ', $project->status)) }}
                    </div>
                </div>
                <div class="rounded-2xl border border-slate-300 bg-white/80 p-4">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Dates</div>
                    <div class="mt-2 text-xs text-slate-500">
                        Start: {{ $project->start_date?->format($globalDateFormat) ?? '--' }}<br>
                        Expected end: {{ $project->expected_end_date?->format($globalDateFormat) ?? '--' }}<br>
                        Due: {{ $project->due_date?->format($globalDateFormat) ?? '--' }}
                    </div>
                </div>
                <div class="rounded-2xl border border-slate-300 bg-white/80 p-4">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Description</div>
                    <div class="mt-2 text-xs text-slate-600 whitespace-pre-wrap">
                        {{ $project->description ?? 'No description provided.' }}
                    </div>
                </div>
            </div>
        </div>

        <div>
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">People</div>
            <div class="mt-3 grid gap-4 md:grid-cols-2 text-sm text-slate-700">
                <div class="rounded-2xl border border-slate-300 bg-white/80 p-4">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Customer</div>
                    <div class="mt-2 font-semibold text-slate-900">{{ $project->customer?->name ?? '--' }}</div>
                    <div class="text-xs text-slate-500">Client ID: {{ $project->customer_id ?? '--' }}</div>
                </div>
                <div class="rounded-2xl border border-slate-300 bg-white/80 p-4">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Team</div>
                    @php
                        $employeeNames = $project->employees->pluck('name')->filter()->implode(', ');
                        $salesRepNames = $project->salesRepresentatives
                            ->map(function ($rep) use ($project) {
                                $amount = $rep->pivot?->amount ?? 0;
                                $amountText = $amount > 0 ? ' ('.$project->currency.' '.number_format($amount, 2).')' : '';
                                return trim($rep->name . $amountText);
                            })
                            ->filter()
                            ->implode(', ');
                    @endphp
                    <div class="mt-2 text-xs text-slate-600">
                        Employees: {{ $employeeNames ?: '--' }}<br>
                        Sales reps: {{ $salesRepNames ?: '--' }}
                    </div>
                </div>
            </div>
        </div>

        <div>
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Documents</div>
            <div class="mt-3 rounded-2xl border border-slate-300 bg-white/80 p-4 text-sm text-slate-700">
                <div class="space-y-3">
                    @if($project->contract_file_path)
                        <div>
                            <div class="text-xs text-slate-500">Contract</div>
                            <a href="{{ route('admin.projects.download', ['project' => $project, 'type' => 'contract']) }}" class="text-sm font-semibold text-teal-700 hover:text-teal-600">
                                {{ $project->contract_original_name ?? 'Download contract' }}
                            </a>
                        </div>
                    @else
                        <div class="text-xs text-slate-500">No contract uploaded.</div>
                    @endif

                    @if($project->proposal_file_path)
                        <div>
                            <div class="text-xs text-slate-500">Proposal</div>
                            <a href="{{ route('admin.projects.download', ['project' => $project, 'type' => 'proposal']) }}" class="text-sm font-semibold text-teal-700 hover:text-teal-600">
                                {{ $project->proposal_original_name ?? 'Download proposal' }}
                            </a>
                        </div>
                    @else
                        <div class="text-xs text-slate-500">No proposal uploaded.</div>
                    @endif
                </div>
            </div>
        </div>

        @php
            $financials = $financials ?? [];
            $overheadTotal = $financials['overhead_total'] ?? $project->overhead_total;
            $budgetWithOverhead = $financials['budget_with_overhead'] ?? ((float) ($project->total_budget ?? 0) + $overheadTotal);
            $remainingBudget = $financials['remaining_budget'] ?? $project->remaining_budget;
            $remainingBudgetInvoiceable = (float) ($financials['remaining_budget_invoiceable'] ?? $remainingBudget);
            $paidPayment = (float) ($financials['paid_payment'] ?? 0);
            $employeeSalaryTotal = (float) ($financials['employee_salary_total'] ?? ($project->contract_amount ?? $project->contract_employee_total_earned ?? 0));
            $salesRepTotal = (float) ($financials['sales_rep_total'] ?? $project->sales_rep_total);
            $hasContractEmployees = $project->employees->contains(fn ($employee) => $employee->employment_type === 'contract');
        @endphp

        <div>
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Budget & Currency</div>
            <div class="mt-3 grid gap-4 md:grid-cols-2 text-sm text-slate-700">
                <div class="rounded-2xl border border-slate-300 bg-white/80 p-4">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Budget Summary</div>
                    <div class="mt-2 text-xs text-slate-600">
                        Total budget: {{ $project->total_budget !== null ? $project->currency.' '.number_format($project->total_budget, 2) : '--' }}<br>
                        Overhead total: {{ $project->currency.' '.number_format($overheadTotal, 2) }}<br>
                        Budget with overhead: {{ $project->currency.' '.number_format($budgetWithOverhead, 2) }}<br>
                        Initial payment: {{ $project->initial_payment_amount !== null ? $project->currency.' '.number_format($project->initial_payment_amount, 2) : '--' }}<br>
                        Paid payment: {{ $project->currency.' '.number_format($paidPayment, 2) }}<br>
                        Remaining budget: {{ $remainingBudget !== null ? $project->currency.' '.number_format($remainingBudget, 2) : '--' }}<br>
                        Budget (legacy): {{ $project->budget_amount !== null ? $project->currency.' '.number_format($project->budget_amount, 2) : '--' }}<br>
                        Currency: {{ $project->currency ?? '--' }}<br>
                        @if($hasContractEmployees || $employeeSalaryTotal > 0)
                            Employee salary total: {{ $project->currency.' '.number_format($employeeSalaryTotal, 2) }}<br>
                        @endif
                        Sales rep total: {{ $project->currency.' '.number_format($salesRepTotal, 2) }}<br>
                        Profit: {{ isset($financials['profit']) ? $project->currency.' '.number_format($financials['profit'], 2) : '--' }}
                    </div>
                </div>
                <div class="rounded-2xl border border-slate-300 bg-white/80 p-4">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Initial Invoice</div>
                    @if(!empty($initialInvoice))
                        <div class="mt-2 text-xs text-slate-600">
                            Number:
                            <a class="text-teal-700 hover:text-teal-600" href="{{ route('admin.invoices.show', $initialInvoice) }}">#{{ $initialInvoice->number ?? $initialInvoice->id }}</a><br>
                            Amount: {{ $initialInvoice->currency ?? $project->currency }} {{ $initialInvoice->total }}<br>
                            Status: {{ ucfirst($initialInvoice->status) }}
                        </div>
                    @else
                        <div class="mt-2 text-xs text-slate-500">No initial invoice linked.</div>
                    @endif
                </div>
            </div>
        </div>

        <div>
            <div class="flex items-center justify-between gap-3">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Overhead fees</div>
                <a href="{{ route('admin.projects.overheads.index', $project) }}" class="text-xs font-semibold text-teal-600 hover:text-teal-500">Manage overheads</a>
            </div>
            <div class="mt-3 rounded-2xl border border-slate-300 bg-white/80 p-4 text-sm text-slate-700">
                @if($project->overheads->isEmpty())
                    <div class="text-xs text-slate-500">No overhead line items added.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead>
                                <tr class="text-xs uppercase tracking-[0.2em] text-slate-500">
                                    <th class="px-3 py-2">Invoice</th>
                                    <th class="px-3 py-2">Details</th>
                                    <th class="px-3 py-2">Amount</th>
                                    <th class="px-3 py-2">Date</th>
                                    <th class="px-3 py-2">Status</th>
                                    <th class="px-3 py-2">View</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($project->overheads as $overhead)
                                    @php
                                        $overheadInvoice = $overhead->invoice;
                                        $overheadInvoiceId = $overheadInvoice?->id;
                                        $hasOverheadInvoice = ! empty($overheadInvoiceId);
                                    @endphp
                                    <tr class="border-t border-slate-100">
                                        <td class="px-3 py-2">
                                            @if($hasOverheadInvoice)
                                                <a href="{{ route('admin.invoices.show', ['invoice' => $overheadInvoiceId]) }}" class="text-teal-700 hover:text-teal-600">
                                                    #{{ is_numeric($overheadInvoice->number) ? $overheadInvoice->number : $overheadInvoiceId }}
                                                </a>
                                            @else
                                                --
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 w-2/5">{{ $overhead->short_details }}</td>
                                        <td class="px-3 py-2 text-right">{{ $project->currency }} {{ number_format((float) $overhead->amount, 2) }}</td>
                                        <td class="px-3 py-2">{{ $overhead->created_at?->format($globalDateFormat) ?? '--' }}</td>
                                        
                                        <td class="px-3 py-2">
                                            @php
                                                $overheadInvoiceStatus = strtolower((string) ($overheadInvoice->status ?? ''));
                                                $overheadStatusLabel = 'Unpaid';
                                                $overheadStatusClass = 'border-amber-200 text-amber-700 bg-amber-50';

                                                if (! $hasOverheadInvoice) {
                                                    $overheadStatusLabel = 'Not invoiced';
                                                    $overheadStatusClass = 'border-slate-300 text-slate-600 bg-slate-100';
                                                } elseif ($overheadInvoiceStatus === 'paid') {
                                                    $overheadStatusLabel = 'Paid';
                                                    $overheadStatusClass = 'border-emerald-200 text-emerald-700 bg-emerald-50';
                                                } elseif ($overheadInvoiceStatus === 'cancelled') {
                                                    $overheadStatusLabel = 'Cancelled';
                                                    $overheadStatusClass = 'border-slate-300 text-slate-600 bg-slate-100';
                                                }
                                            @endphp
                                            <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-semibold {{ $overheadStatusClass }}">
                                                {{ $overheadStatusLabel }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            @if($hasOverheadInvoice)
                                                <a href="{{ route('admin.invoices.show', ['invoice' => $overheadInvoiceId]) }}" class="text-xs font-semibold text-slate-700 hover:text-teal-600">View</a>
                                            @else
                                                <span class="text-xs text-slate-400">--</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.projects.overheads.store', $project) }}" class="mt-4 grid gap-3 md:grid-cols-3">
                    @csrf
                    <div class="md:col-span-2">
                        <label class="text-xs text-slate-500">Details</label>
                        <input name="short_details" required class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" placeholder="Feature fee or description">
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Amount</label>
                        <input name="amount" required type="number" step="0.01" min="0" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                    </div>
                    <div class="md:col-span-3 flex justify-end">
                        <button type="submit" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Add overhead fee</button>
                    </div>
                </form>
            </div>
        </div>

        <div>
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Remaining budget invoices</div>
            <div class="mt-3 rounded-2xl border border-slate-300 bg-white/80 p-4 text-sm text-slate-700 space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="text-sm font-semibold text-slate-800">Remaining budget invoices</div>
                    <div class="text-xs text-slate-500">
                        Remaining: {{ $project->currency.' '.number_format($remainingBudgetInvoiceable, 2) }}
                    </div>
                </div>

                @if($remainingBudgetInvoices->isEmpty())
                    <div class="text-xs text-slate-500">No invoices generated from the remaining budget yet.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead>
                                <tr class="text-xs uppercase tracking-[0.2em] text-slate-500">
                                    <th class="px-3 py-2">Invoice</th>
                                    <th class="px-3 py-2">Amount</th>
                                    <th class="px-3 py-2">Issue</th>
                                    <th class="px-3 py-2">Due</th>
                                    <th class="px-3 py-2">Paid at</th>
                                    <th class="px-3 py-2">Status</th>
                                    <th class="px-3 py-2 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($remainingBudgetInvoices as $invoice)
                                    <tr class="border-t border-slate-100">
                                        <td class="px-3 py-2 font-medium text-slate-900">
                                            <a href="{{ route('admin.invoices.show', $invoice) }}" class="text-teal-700 hover:text-teal-600">
                                                #{{ is_numeric($invoice->number) ? $invoice->number : $invoice->id }}
                                            </a>
                                        </td>
                                        <td class="px-3 py-2 text-slate-700">{{ $invoice->currency }} {{ $invoice->total }}</td>
                                        <td class="px-3 py-2 text-slate-500">{{ $invoice->issue_date?->format($globalDateFormat) ?? '--' }}</td>
                                        <td class="px-3 py-2 text-slate-500">{{ $invoice->due_date?->format($globalDateFormat) ?? '--' }}</td>
                                        <td class="px-3 py-2 text-slate-500">{{ $invoice->paid_at?->format($globalDateFormat) ?? '--' }}</td>
                                        <td class="px-3 py-2">
                                            <x-status-badge :status="$invoice->status" />
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <a href="{{ route('admin.invoices.show', $invoice) }}" class="text-xs font-semibold text-slate-700 hover:text-teal-600">View</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <div class="border-t border-slate-100 pt-4">
                    @if($remainingBudgetInvoiceable > 0)
                        <form method="POST" action="{{ route('admin.projects.invoice-remaining', $project) }}" class="space-y-3 text-xs text-slate-500">
                            @csrf
                            <div class="space-y-1">
                                <label class="text-[10px] uppercase tracking-[0.2em] text-slate-400 flex justify-between">
                                    <span>Amount</span>
                                    <span class="text-slate-500">Available: {{ $project->currency }} {{ number_format($remainingBudgetInvoiceable, 2) }}</span>
                                </label>
                                <input
                                    name="amount"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    max="{{ $remainingBudgetInvoiceable }}"
                                    value="{{ old('amount', $remainingBudgetInvoiceable) }}"
                                    class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-teal-400 focus:outline-none"
                                >
                                @error('amount')
                                    <p class="text-rose-500 text-[10px]">{{ $message }}</p>
                                @enderror
                                <p class="text-[10px] text-slate-400">Invoice the full remaining amount or enter a smaller partial amount.</p>
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Invoice remaining budget</button>
                            </div>
                        </form>
                    @else
                        <p class="text-[10px] text-slate-500">Remaining budget must be positive before you can generate an additional invoice.</p>
                    @endif
                </div>
            </div>
        </div>

        <div>
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Maintenance</div>
            <div class="mt-3 text-sm text-slate-700">
                <div class="rounded-2xl border border-slate-300 bg-white/80 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="text-sm font-semibold text-slate-800">Maintenance plans</div>
                        <a href="{{ route('admin.project-maintenances.create', ['project_id' => $project->id]) }}" class="rounded-full border border-teal-200 px-3 py-1 text-xs font-semibold text-teal-700 hover:border-teal-300 hover:text-teal-800">Add maintenance</a>
                    </div>
                    @php $maintenances = $project->maintenances?->sortBy('next_billing_date') ?? collect(); @endphp
                    @if($maintenances->isEmpty())
                        <div class="mt-3 text-xs text-slate-500">No maintenance plans for this project.</div>
                    @else
                        <div class="mt-3 overflow-x-auto">
                            <table class="min-w-full text-left text-sm">
                                <thead>
                                <tr class="text-xs uppercase tracking-[0.2em] text-slate-500">
                                    <th class="px-3 py-2">Title</th>
                                    <th class="px-3 py-2">Cycle</th>
                                    <th class="px-3 py-2">Next Billing</th>
                                    <th class="px-3 py-2">Status</th>
                                    <th class="px-3 py-2">Auto</th>
                                    <th class="px-3 py-2 text-right">Amount</th>
                                    <th class="px-3 py-2 text-right">Invoices</th>
                                    <th class="px-3 py-2 text-right">Actions</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($maintenances as $maintenance)
                                    <tr class="border-t border-slate-100">
                                        <td class="px-3 py-2">{{ $maintenance->title }}</td>
                                        <td class="px-3 py-2">{{ ucfirst($maintenance->billing_cycle) }}</td>
                                        <td class="px-3 py-2 text-xs text-slate-600">{{ $maintenance->next_billing_date?->format($globalDateFormat) ?? '--' }}</td>
                                        <td class="px-3 py-2">
                                            <span class="rounded-full border px-2 py-0.5 text-xs font-semibold {{ $maintenance->status === 'active' ? 'border-emerald-200 text-emerald-700 bg-emerald-50' : ($maintenance->status === 'paused' ? 'border-amber-200 text-amber-700 bg-amber-50' : 'border-slate-300 text-slate-600 bg-slate-50') }}">
                                                {{ ucfirst($maintenance->status) }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-xs text-slate-600">{{ $maintenance->auto_invoice ? 'Yes' : 'No' }}</td>
                                        <td class="px-3 py-2 text-right">{{ $maintenance->currency }} {{ number_format((float) $maintenance->amount, 2) }}</td>
                                        <td class="px-3 py-2 text-right">
                                            <a href="{{ route('admin.invoices.index', ['maintenance_id' => $maintenance->id]) }}" class="text-xs font-semibold text-slate-700 hover:text-teal-600">
                                                {{ $maintenance->invoices_count ?? 0 }}
                                            </a>
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <a href="{{ route('admin.project-maintenances.edit', $maintenance) }}" class="text-xs font-semibold text-teal-700 hover:text-teal-600">Edit</a>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @if($project->notes)
            <div class="rounded-2xl border border-slate-300 bg-white/80 p-4 text-sm text-slate-700">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Notes</div>
                <div class="mt-2 whitespace-pre-wrap">{{ $project->notes }}</div>
            </div>
        @endif

    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const button = document.getElementById('project-ai-generate');
                const status = document.getElementById('project-ai-status');
                const summary = document.getElementById('project-ai-summary-text');
                const health = document.getElementById('project-ai-health');
                const highlights = document.getElementById('project-ai-highlights');
                const risks = document.getElementById('project-ai-risks');
                const nextSteps = document.getElementById('project-ai-next-steps');

                const setStatus = (label, cls) => {
                    if (!status) return;
                    status.textContent = label;
                    status.className = `rounded-full px-3 py-1 text-xs font-semibold ${cls}`;
                };

                const setHealth = (value) => {
                    if (!health) return;
                    const normalized = (value || '').toLowerCase();
                    let cls = 'bg-slate-100 text-slate-600';
                    if (normalized === 'green') cls = 'bg-emerald-100 text-emerald-700';
                    if (normalized === 'yellow') cls = 'bg-amber-100 text-amber-700';
                    if (normalized === 'red') cls = 'bg-rose-100 text-rose-700';
                    health.textContent = value || '--';
                    health.className = `rounded-full px-3 py-1 text-[11px] font-semibold ${cls}`;
                };

                const renderList = (el, items) => {
                    if (!el) return;
                    el.innerHTML = '';
                    if (!items || !items.length) {
                        const li = document.createElement('li');
                        li.textContent = '--';
                        el.appendChild(li);
                        return;
                    }
                    items.forEach((item) => {
                        const li = document.createElement('li');
                        li.textContent = item;
                        el.appendChild(li);
                    });
                };

                if (!button) return;

                button.addEventListener('click', async () => {
                    setStatus('Generating...', 'bg-amber-100 text-amber-700');
                    if (summary) summary.textContent = 'Working on the AI summary...';
                    setHealth('--');
                    renderList(highlights, []);
                    renderList(risks, []);
                    renderList(nextSteps, []);

                    try {
                        const response = await fetch("{{ route('admin.projects.ai', $project) }}", {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]').getAttribute('content'),
                                'Accept': 'application/json',
                            },
                        });

                        const payload = await response.json();
                        if (!response.ok) {
                            throw new Error(payload.error || 'Failed to generate AI summary.');
                        }

                        if (payload.data) {
                            summary.textContent = payload.data.summary || payload.raw || '--';
                            setHealth(payload.data.health || '--');
                            renderList(highlights, Array.isArray(payload.data.highlights) ? payload.data.highlights : []);
                            renderList(risks, Array.isArray(payload.data.risks) ? payload.data.risks : []);
                            renderList(nextSteps, Array.isArray(payload.data.next_steps) ? payload.data.next_steps : []);
                        } else {
                            summary.textContent = payload.raw || '--';
                        }

                        setStatus('Updated', 'bg-emerald-100 text-emerald-700');
                    } catch (error) {
                        if (summary) summary.textContent = error.message;
                        setStatus('Error', 'bg-rose-100 text-rose-700');
                    }
                });
            });
        </script>
    @endpush
@endsection
