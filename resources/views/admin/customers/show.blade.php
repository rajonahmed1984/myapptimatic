@extends('layouts.admin')

@section('title', 'Customer Details')
@section('page-title', 'Customer Details')

@section('content')
    @php
        $currencySymbol = $currencySymbol ?? '';
        $currencyCode = $currencyCode ?? '';
        $formatCurrency = function ($amount) use ($currencySymbol, $currencyCode) {
            $formatted = number_format((float) ($amount ?? 0), 2);
            return "{$currencySymbol}{$formatted}{$currencyCode}";
        };
    @endphp
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $customer->name }}</div>
            <div class="mt-1 text-sm text-slate-500">
                Client ID: {{ $customer->id }} | Created: {{ $customer->created_at?->format($globalDateFormat) ?? '--' }} | Status: {{ ucfirst($effectiveStatus ?? $customer->status) }}
            </div>
        </div>
        <div class="text-sm text-slate-600">
            <div class="mt-3 flex flex-wrap items-center gap-3">
                <a href="{{ route('admin.customers.index') }}" class="rounded-full border border-slate-300 px-5 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600" hx-boost="false">Back to Customers</a>
                <a href="{{ route('admin.invoices.create', ['customer_id' => $customer->id]) }}" class="rounded-full bg-slate-900 px-5 py-2 text-sm font-semibold text-white hover:bg-slate-800">Create Invoice</a>
                <a href="{{ route('admin.support-tickets.create', ['customer_id' => $customer->id]) }}" class="rounded-full border border-slate-300 px-5 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Open Ticket</a>
                <form method="POST" action="{{ route('admin.customers.impersonate', $customer) }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600" title="Login as client">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                            <path d="M15 3h4a2 2 0 0 1 2 2v4"></path>
                            <path d="M10 14L21 3"></path>
                            <path d="M21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h9"></path>
                        </svg>
                        Login as client
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="card p-6">

        @include('admin.customers.partials.tabs', ['customer' => $customer, 'activeTab' => $tab])

        @if($tab === 'summary')
            <div class="mt-6 grid gap-4 md:grid-cols-3 text-sm text-slate-600">
                <div class="rounded-2xl border border-slate-300 bg-white/70 p-4">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Profile</div>
                    <div class="mt-2">Company: {{ $customer->company_name ?: '--' }}</div>
                    <div class="mt-1">Email: {{ $customer->email ?: '--' }}</div>
                    <div class="mt-1">Mobile: {{ $customer->phone ?: '--' }}</div>
                    <div class="mt-1">Address: {{ $customer->address ?: '--' }}</div>
                </div>
                <div class="rounded-2xl border border-slate-300 bg-white/70 p-4">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Summary</div>
                    <div class="mt-2">Services: {{ $customer->subscriptions->count() }}</div>
                    <div class="mt-1">Active services: {{ $customer->subscriptions->where('status', 'active')->count() }}</div>
                    <div class="mt-1">Projects: {{ $customer->projects->count() }}</div>
                    <div class="mt-1">Invoices: {{ $customer->invoices->count() }}</div>
                    <div class="mt-1">Tickets: {{ $customer->supportTickets->count() }}</div>
                </div>
                <div class="rounded-2xl border border-slate-300 bg-white/70 p-4">
                    <div>
                        <div class="text-[11px] uppercase tracking-[0.2em] text-slate-400">Sales Representatives</div>
                        @if(!empty($salesRepSummaries) && $salesRepSummaries->isNotEmpty())
                            <div class="mt-3 space-y-3 text-sm">
                                @foreach($salesRepSummaries as $rep)
                                    <div class="rounded-xl border border-slate-300 bg-white p-3">
                                        <div class="font-semibold text-slate-900">{{ $rep['name'] ?? 'Sales Rep' }}</div>
                                        <div class="text-xs text-slate-500">{{ $rep['phone'] ?? '--' }}</div>
                                        <div class="mt-2 text-[11px] uppercase tracking-[0.2em] text-slate-400">Projects & Maintenance</div>
                                        <div class="mt-2 space-y-1 text-xs text-slate-600">
                                            @forelse(($rep['projects'] ?? []) as $project)
                                                <div class="flex flex-wrap items-center justify-between gap-2">
                                                    <span class="font-medium text-slate-700">{{ $project['name'] ?? 'Project' }}</span>
                                                    <span>
                                                        Project: {{ $currencySymbol }}{{ number_format((float) ($project['project_amount'] ?? 0), 2) }}
                                                        · Maintenance: {{ $currencySymbol }}{{ number_format((float) ($project['maintenance_amount'] ?? 0), 2) }}
                                                    </span>
                                                </div>
                                            @empty
                                                <div class="text-xs text-slate-500">No projects linked.</div>
                                            @endforelse
                                        </div>
                                        <div class="mt-2 text-xs text-slate-500">
                                            Total: {{ $currencySymbol }}{{ number_format((float) ($rep['total_project_amount'] ?? 0), 2) }}
                                            · Maintenance: {{ $currencySymbol }}{{ number_format((float) ($rep['total_maintenance_amount'] ?? 0), 2) }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="mt-2 text-sm text-slate-500">No sales representatives linked to this customer.</div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-2">                
                <div class="rounded-2xl border border-slate-300 bg-white/70 p-4">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Invoice status & Income</div>
                    <div class="mt-4 pb-4 space-y-3 text-sm text-slate-600">
                        @foreach($invoiceStatusSummary as $status)
                            <div class="flex items-center justify-between">
                                <div>{{ $status['label'] }} ( {{ $status['count'] }} )</div>
                                <div class="font-semibold text-slate-900">{{ $formatCurrency($status['amount']) }}</div>
                            </div>
                        @endforeach
                    </div>
                    <hr>
                    <div class="mt-4 space-y-3 text-sm text-slate-600">
                        <div class="flex items-center justify-between">
                            <div>Gross Revenue</div>
                            <div class="font-semibold text-slate-900">{{ $formatCurrency($grossRevenue) }}</div>
                        </div>
                        <div class="flex items-center justify-between">
                            <div>Client Expenses</div>
                            <div class="font-semibold text-slate-900">{{ $formatCurrency($clientExpenses) }}</div>
                        </div>
                        <div class="flex items-center justify-between">
                            <div>Net Income</div>
                            <div class="font-semibold text-emerald-600">{{ $formatCurrency($netIncome) }}</div>
                        </div>
                        <div class="flex items-center justify-between">
                            <div>Credit Balance</div>
                            <div class="font-semibold text-slate-900">{{ $formatCurrency($creditBalance) }}</div>
                        </div>
                    </div>
                </div>
                <div class="rounded-2xl border border-slate-300 bg-white/70 p-4">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Recent Tickets</div>
                    <div class="mt-3 space-y-2 text-sm">
                        @forelse($customer->supportTickets->take(5) as $ticket)
                            <div class="flex items-center justify-between border-b border-slate-300 pb-2">
                                <div>
                                    <div class="font-semibold text-slate-900">
                                        <a href="{{ route('admin.support-tickets.show', $ticket) }}" class="hover:text-teal-600">
                                            {{ $ticket->subject }}
                                        </a>
                                    </div>
                                    <div class="text-xs text-slate-500">
                                        <a href="{{ route('admin.support-tickets.show', $ticket) }}" class="hover:text-teal-600">
                                            TKT-{{ str_pad($ticket->id, 5, '0', STR_PAD_LEFT) }}
                                        </a>
                                        <span class="mx-1">•</span>
                                        {{ $ticket->created_at?->format($globalDateFormat) ?? '--' }}
                                    </div>
                                </div>
                                @php
                                    $statusLabel = ucfirst(str_replace('_', ' ', $ticket->status));
                                    $statusClasses = match ($ticket->status) {
                                        'open' => 'bg-amber-100 text-amber-700',
                                        'answered' => 'bg-emerald-100 text-emerald-700',
                                        'customer_reply' => 'bg-blue-100 text-blue-700',
                                        'closed' => 'bg-slate-100 text-slate-600',
                                        default => 'bg-slate-100 text-slate-600',
                                    };
                                @endphp
                                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $statusClasses }}">
                                    {{ $statusLabel }}
                                </span>
                            </div>
                        @empty
                            <div class="text-sm text-slate-500">No support tickets yet.</div>
                        @endforelse
                    </div>
                </div>                
            </div>

            <div class="mt-6 grid gap-4 lg:grid-cols-1">
                

                
            </div>
        @elseif($tab === 'project-specific')
            <div class="mt-6">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <div class="section-label">Project clients</div>
                        <h2 class="text-xl font-semibold text-slate-900">Project-specific logins</h2>
                        <p class="text-sm text-slate-500">Assign a dedicated login that can only view and update one project.</p>
                    </div>
                </div>

                @if($projectClients->isNotEmpty())
                    <div class="mt-4 overflow-x-auto text-sm">
                        <table class="min-w-full text-left text-slate-700">
                            <thead>
                                <tr class="text-xs uppercase tracking-[0.2em] text-slate-500">
                                    <th class="py-2">Name</th>
                                    <th class="py-2">Email</th>
                                    <th class="py-2">Status</th>
                                    <th class="py-2">Project</th>
                                    <th class="py-2">Created</th>
                                    <th class="py-2 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($projectClients as $clientUser)
                                    <tr class="border-b border-slate-100" data-project-user-row="{{ $clientUser->id }}">
                                        <td class="py-2" data-user-field="name">{{ $clientUser->name }}</td>
                                        <td class="py-2" data-user-field="email">{{ $clientUser->email }}</td>
                                        <td class="py-2" data-user-field="status">
                                            <x-status-badge :status="$clientUser->status ?? 'active'" />
                                        </td>
                                        <td class="py-2" data-user-field="project">{{ $clientUser->project?->name ?? 'ƒ?"' }}</td>
                                        <td class="py-2" data-user-field="created">{{ $clientUser->created_at?->format($globalDateFormat) ?? '--' }}</td>
                                        <td class="py-2 text-right">
                                            <button
                                                type="button"
                                                data-project-user-edit
                                                data-user-id="{{ $clientUser->id }}"
                                                data-user-name="{{ $clientUser->name }}"
                                                data-user-email="{{ $clientUser->email }}"
                                                data-user-status="{{ $clientUser->status ?? 'active' }}"
                                                data-project-id="{{ $clientUser->project_id }}"
                                                data-project-name="{{ $clientUser->project?->name ?? '' }}"
                                                data-created-at="{{ $clientUser->created_at?->format($globalDateFormat) ?? '--' }}"
                                                class="text-teal-600 hover:text-teal-500 mr-3"
                                            >
                                                Edit
                                            </button>
                                            <form
                                                method="POST"
                                                action="{{ route('admin.customers.project-users.destroy', [$customer, $clientUser]) }}"
                                                class="inline"
                                                data-delete-confirm
                                                data-confirm-name="{{ $clientUser->name ?: $clientUser->email }}"
                                                data-confirm-title="Delete project login {{ $clientUser->name ?: $clientUser->email }}?"
                                                data-confirm-description="This action cannot be undone."
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-rose-600 hover:text-rose-500">
                                                    Delete
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="mt-3 text-sm text-slate-500">No project-specific logins have been created yet.</p>
                @endif

                <form method="POST" action="{{ route('admin.customers.project-users.store', $customer) }}" class="mt-6 grid gap-4 md:grid-cols-2 text-sm">
                    @csrf
                    <div>
                        <label class="text-sm text-slate-600">Project</label>
                        <select name="project_id" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm">
                            <option value="">Select a project</option>
                            @foreach($projects as $projectOption)
                                <option value="{{ $projectOption->id }}" @selected(old('project_id') == $projectOption->id)>{{ $projectOption->name }}</option>
                            @endforeach
                        </select>
                        @error('project_id')
                            <p class="mt-1 text-xs text-rose-500">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-600">Name</label>
                        <input name="name" value="{{ old('name') }}" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm" />
                        @error('name')
                            <p class="mt-1 text-xs text-rose-500">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-600">Email</label>
                        <input name="email" type="email" value="{{ old('email') }}" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm" />
                        @error('email')
                            <p class="mt-1 text-xs text-rose-500">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-600">Password</label>
                        <input name="password" type="password" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm" />
                        @error('password')
                            <p class="mt-1 text-xs text-rose-500">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-sm text-slate-600">Confirm Password</label>
                        <input name="password_confirmation" type="password" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm" />
                    </div>
                    <div class="md:col-span-2 flex justify-end">
                        <button type="submit" class="rounded-full bg-teal-500 px-5 py-2 text-sm font-semibold text-white">Create project login</button>
                    </div>
                </form>

                <div id="project-user-edit-panel" class="mt-8 hidden">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <div class="section-label">Edit project login</div>
                            <h3 class="text-lg font-semibold text-slate-900">Update project-specific login</h3>
                            <p class="text-sm text-slate-500">Changes are saved without leaving this page.</p>
                        </div>
                    </div>

                    <form id="project-user-edit-form" method="POST" hx-boost="false" data-fetch-url-template="{{ route('admin.customers.project-users.show', [$customer, '__USER_ID__']) }}" data-update-url-template="{{ route('admin.customers.project-users.update', [$customer, '__USER_ID__']) }}" class="mt-4 grid gap-4 md:grid-cols-2 text-sm">
                        @csrf
                        @method('PUT')

                        <div>
                            <label class="text-sm text-slate-600">Project</label>
                            <select name="project_id" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm">
                                <option value="">Select a project</option>
                                @foreach($projects as $projectOption)
                                    <option value="{{ $projectOption->id }}">{{ $projectOption->name }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-rose-500 hidden" data-error-for="project_id"></p>
                        </div>
                        <div>
                            <label class="text-sm text-slate-600">Name</label>
                            <input name="name" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm" />
                            <p class="mt-1 text-xs text-rose-500 hidden" data-error-for="name"></p>
                        </div>
                        <div>
                            <label class="text-sm text-slate-600">Email</label>
                            <input name="email" type="email" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm" />
                            <p class="mt-1 text-xs text-rose-500 hidden" data-error-for="email"></p>
                        </div>
                        <div>
                            <label class="text-sm text-slate-600">Status</label>
                            <select name="status" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                            <p class="mt-1 text-xs text-rose-500 hidden" data-error-for="status"></p>
                        </div>
                        <div>
                            <label class="text-sm text-slate-600">Password</label>
                            <input name="password" type="password" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm" />
                            <p class="mt-1 text-xs text-slate-500">Leave blank to keep current password</p>
                            <p class="mt-1 text-xs text-rose-500 hidden" data-error-for="password"></p>
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-sm text-slate-600">Confirm Password</label>
                            <input name="password_confirmation" type="password" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm" />
                        </div>
                        <div class="md:col-span-2 flex items-center justify-end gap-3">
                            <button type="button" class="text-sm text-slate-600 hover:text-teal-600" data-edit-cancel>Cancel</button>
                            <button type="submit" class="rounded-full bg-teal-500 px-5 py-2 text-sm font-semibold text-white">Update login</button>
                        </div>
                    </form>
                    <p id="project-user-edit-status" class="mt-3 text-sm text-slate-500 hidden"></p>
                </div>

                <div id="project-user-details" class="mt-6 hidden rounded-2xl border border-slate-300 bg-slate-50 p-5">
                    <div class="section-label">Updated login details</div>
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-6">
                        <div>
                            <div class="text-lg font-semibold text-slate-900" data-detail="name">--</div>
                            <div class="mt-1 text-sm text-slate-600">Email: <span data-detail="email">--</span></div>
                            <div class="text-sm text-slate-600">Project: <span data-detail="project">--</span></div>
                            <div class="mt-1 text-sm text-slate-600 flex items-center gap-2">Status: <span data-detail="status">--</span></div>
                        </div>
                        <div class="text-sm text-slate-500">
                            <div>Created: <span data-detail="created">--</span></div>
                            <div>Updated: <span data-detail="updated">--</span></div>
                        </div>
                    </div>
                </div>
            </div>
        @elseif($tab === 'services')
            <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-500">Need a new product or service? Start here.</p>
                <a href="{{ route('admin.products.create') }}" class="rounded-full bg-teal-500 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-400">Create product/service</a>
            </div>

            <div class="mt-4 overflow-x-auto rounded-2xl border border-slate-300">
                <table class="w-full min-w-[800px] text-left text-sm">
                    <thead class="border-b border-slate-300 text-xs uppercase tracking-[0.25em] text-slate-500">
                        <tr>
                            <th class="px-4 py-3">SL</th>
                            <th class="px-4 py-3">Product</th>
                            <th class="px-4 py-3">Plan</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Order number</th>
                            <th class="px-4 py-3">Next invoice</th>
                            <th class="px-4 py-3">Period end</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($customer->subscriptions as $subscription)
                            <tr class="border-b border-slate-100">
                                <td class="px-4 py-3 text-slate-500">{{ $loop->iteration }}</td>
                                <td class="px-4 py-3 text-slate-900">{{ $subscription->plan?->product?->name ?? '--' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $subscription->plan?->name ?? '--' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ ucfirst($subscription->status) }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ $subscription->latestOrder?->order_number ?? '--' }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ $subscription->next_invoice_at?->format($globalDateFormat) ?? '--' }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ $subscription->current_period_end?->format($globalDateFormat) ?? '--' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.subscriptions.edit', $subscription) }}" class="text-teal-600 hover:text-teal-500">Manage</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-6 text-center text-slate-500">No services yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @elseif($tab === 'projects')
            <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-500">Start a new project for this customer.</p>
                <a href="{{ route('admin.projects.create') }}" class="rounded-full bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Create project</a>
            </div>

            <div class="mt-4 overflow-x-auto rounded-2xl border border-slate-300">
                <table class="w-full min-w-[900px] text-left text-sm">
                    <thead class="border-b border-slate-300 text-xs uppercase tracking-[0.25em] text-slate-500">
                        <tr>
                            <th class="px-4 py-3">SL</th>
                            <th class="px-4 py-3">Project name</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Start date</th>
                            <th class="px-4 py-3">Due date</th>
                            <th class="px-4 py-3">Budget</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($customer->projects as $project)
                            @php
                                $statusClasses = match ($project->status) {
                                    'ongoing' => 'bg-emerald-100 text-emerald-700',
                                    'complete' => 'bg-blue-100 text-blue-700',
                                    'hold' => 'bg-amber-100 text-amber-700',
                                    'cancel' => 'bg-rose-100 text-rose-700',
                                    default => 'bg-slate-100 text-slate-600',
                                };
                            @endphp
                            <tr class="border-b border-slate-100">
                                <td class="px-4 py-3 text-slate-500">{{ $loop->iteration }}</td>
                                <td class="px-4 py-3 font-medium text-slate-900">
                                    <a href="{{ route('admin.projects.show', $project) }}" class="text-teal-700 hover:text-teal-500">
                                        {{ $project->name }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ ucfirst($project->type) }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $statusClasses }}">{{ ucfirst(str_replace('_', ' ', $project->status)) }}</span>
                                </td>
                                <td class="px-4 py-3 text-slate-500">{{ $project->start_date?->format($globalDateFormat) ?? '--' }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ $project->due_date?->format($globalDateFormat) ?? '--' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $project->currency }} {{ number_format($project->total_budget, 2) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.projects.show', $project) }}" class="text-teal-600 hover:text-teal-500">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-6 text-center text-slate-500">No projects yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @elseif($tab === 'invoices')
            <div class="mt-6 overflow-x-auto rounded-2xl border border-slate-300">
                <table class="w-full min-w-[800px] text-left text-sm">
                    <thead class="border-b border-slate-300 text-xs uppercase tracking-[0.25em] text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Invoice</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Total</th>
                            <th class="px-4 py-3">Issue date</th>
                            <th class="px-4 py-3">Due date</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($customer->invoices as $invoice)
                            <tr class="border-b border-slate-100">
                                <td class="px-4 py-3 font-medium text-slate-900">{{ is_numeric($invoice->number) ? $invoice->number : $invoice->id }}</td>
                                <td class="px-4 py-3">
                                    <x-status-badge :status="$invoice->status" :label="ucfirst($invoice->status)" />
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $invoice->currency }} {{ $invoice->total }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ $invoice->issue_date?->format($globalDateFormat) ?? '--' }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ $invoice->due_date?->format($globalDateFormat) ?? '--' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.invoices.show', $invoice) }}" class="text-slate-500 hover:text-teal-600">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-slate-500">No invoices yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @elseif($tab === 'tickets')
            <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                <div class="text-sm text-slate-600">Support tickets for this customer.</div>
                <a href="{{ route('admin.support-tickets.create', ['customer_id' => $customer->id]) }}" class="rounded-full border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Open Ticket</a>
            </div>
            <div class="mt-4 overflow-x-auto rounded-2xl border border-slate-300">
                <table class="w-full min-w-[800px] text-left text-sm">
                    <thead class="border-b border-slate-300 text-xs uppercase tracking-[0.25em] text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Ticket</th>
                            <th class="px-4 py-3">Subject</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Last reply</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($customer->supportTickets as $ticket)
                            @php
                                $label = ucfirst(str_replace('_', ' ', $ticket->status));
                                $statusClasses = match ($ticket->status) {
                                    'open' => 'bg-amber-100 text-amber-700',
                                    'answered' => 'bg-emerald-100 text-emerald-700',
                                    'customer_reply' => 'bg-blue-100 text-blue-700',
                                    'closed' => 'bg-slate-100 text-slate-600',
                                    default => 'bg-slate-100 text-slate-600',
                                };
                            @endphp
                            <tr class="border-b border-slate-100">
                                <td class="px-4 py-3 font-medium text-slate-900">TKT-{{ str_pad($ticket->id, 5, '0', STR_PAD_LEFT) }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $ticket->subject }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $statusClasses }}">{{ $label }}</span>
                                </td>
                                <td class="px-4 py-3 text-slate-500">{{ $ticket->last_reply_at?->format($globalDateFormat . ' H:i') ?? '--' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.support-tickets.show', $ticket) }}" class="text-slate-500 hover:text-teal-600">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-slate-500">No support tickets yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @elseif($tab === 'emails')
            <div class="mt-6 rounded-2xl border border-slate-300 bg-white/70 p-5 text-sm text-slate-600">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Client Email Log</div>
                <div class="mt-4 overflow-x-auto rounded-2xl border border-slate-300 bg-white">
                    @if($emailLogs->isEmpty())
                        <div class="px-4 py-6 text-sm text-slate-500">No emails sent to this client yet.</div>
                    @else
                        <table class="w-full min-w-[700px] text-left text-sm">
                            <thead class="border-b border-slate-300 text-xs uppercase tracking-[0.25em] text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Date</th>
                                    <th class="px-4 py-3">Subject</th>
                                    <th class="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($emailLogs as $log)
                                    <tr class="border-b border-slate-100">
                                        <td class="px-4 py-3 text-slate-500">{{ $log->created_at?->format($globalDateFormat.' H:i') ?? '--' }}</td>
                                        <td class="px-4 py-3 text-slate-700">{{ $log->context['subject'] ?? $log->message }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <div class="flex flex-wrap items-center justify-end gap-2">
                                                <form method="POST" action="{{ route('admin.logs.email.resend', $log) }}">
                                                    @csrf
                                                    <button type="submit" class="rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Resend Email</button>
                                                </form>
                                                <form
                                                    method="POST"
                                                    action="{{ route('admin.logs.email.delete', $log) }}"
                                                    data-delete-confirm
                                                    data-confirm-name="LOG-{{ $log->id }}"
                                                    data-confirm-title="Delete email log LOG-{{ $log->id }}?"
                                                    data-confirm-description="This will permanently delete the email log."
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="rounded-full border border-rose-200 px-3 py-1 text-xs font-semibold text-rose-600 hover:border-rose-300 hover:text-rose-700">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        @elseif($tab === 'log')
            <div class="mt-6 rounded-2xl border border-slate-300 bg-white/70 p-5 text-sm text-slate-600">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Client Activity Log</div>
                <div class="mt-4 overflow-hidden rounded-2xl border border-slate-300 bg-white">
                    @if($activityLogs->isEmpty())
                        <div class="px-4 py-6 text-sm text-slate-500">No activity recorded yet.</div>
                    @else
                        <table class="w-full min-w-[700px] text-left text-sm">
                            <thead class="border-b border-slate-300 text-xs uppercase tracking-[0.25em] text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Date</th>
                                    <th class="px-4 py-3">Category</th>
                                    <th class="px-4 py-3">Level</th>
                                    <th class="px-4 py-3">Message</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($activityLogs as $log)
                                    @php
                                        $level = strtolower((string) $log->level);
                                        $levelClasses = match ($level) {
                                            'error' => 'bg-rose-100 text-rose-700',
                                            'warning' => 'bg-amber-100 text-amber-700',
                                            'info' => 'bg-blue-100 text-blue-700',
                                            default => 'bg-slate-100 text-slate-600',
                                        };
                                    @endphp
                                    <tr class="border-b border-slate-100">
                                        <td class="px-4 py-3 text-slate-500">{{ $log->created_at?->format($globalDateFormat.' H:i') ?? '--' }}</td>
                                        <td class="px-4 py-3 text-slate-600">{{ ucfirst($log->category) }}</td>
                                        <td class="px-4 py-3">
                                            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $levelClasses }}">{{ strtoupper($level) }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-slate-600">
                                            <div class="font-semibold text-slate-800">{{ $log->message }}</div>
                                            @if(!empty($log->context))
                                                <div class="mt-1 text-xs text-slate-500">{{ json_encode($log->context) }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        @endif
    </div>
@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const editPanel = document.getElementById('project-user-edit-panel');
            const editForm = document.getElementById('project-user-edit-form');
            const statusEl = document.getElementById('project-user-edit-status');
            const detailsPanel = document.getElementById('project-user-details');

            if (!editPanel || !editForm) {
                return;
            }

            const fetchUrlTemplate = editForm.dataset.fetchUrlTemplate || '';
            const updateUrlTemplate = editForm.dataset.updateUrlTemplate || '';
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const detailMap = {
                name: detailsPanel?.querySelector('[data-detail="name"]'),
                email: detailsPanel?.querySelector('[data-detail="email"]'),
                project: detailsPanel?.querySelector('[data-detail="project"]'),
                status: detailsPanel?.querySelector('[data-detail="status"]'),
                created: detailsPanel?.querySelector('[data-detail="created"]'),
                updated: detailsPanel?.querySelector('[data-detail="updated"]'),
            };

            const setStatus = (message, isError = false) => {
                if (!statusEl) {
                    return;
                }

                if (!message) {
                    statusEl.textContent = '';
                    statusEl.classList.add('hidden');
                    statusEl.classList.remove('text-rose-600', 'text-emerald-600');
                    return;
                }

                statusEl.textContent = message;
                statusEl.classList.remove('hidden');
                statusEl.classList.toggle('text-rose-600', isError);
                statusEl.classList.toggle('text-emerald-600', !isError);
            };

            const clearErrors = () => {
                editForm.querySelectorAll('[data-error-for]').forEach((el) => {
                    el.textContent = '';
                    el.classList.add('hidden');
                });
            };

            const showErrors = (errors) => {
                Object.entries(errors || {}).forEach(([field, messages]) => {
                    const el = editForm.querySelector(`[data-error-for="${field}"]`);
                    if (!el) {
                        return;
                    }
                    const text = Array.isArray(messages) ? messages.join(' ') : String(messages);
                    el.textContent = text;
                    el.classList.remove('hidden');
                });
            };

            const renderStatusBadge = (data) => {
                const label = data?.status_label || '--';
                const classes = data?.status_classes || '';
                if (!label || label === '--') {
                    return null;
                }

                const badge = document.createElement('span');
                badge.className = `inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ${classes}`.trim();
                badge.textContent = label;
                return badge;
            };

            const updateStatusBadge = (target, data) => {
                if (!target) {
                    return;
                }

                target.innerHTML = '';
                const badge = renderStatusBadge(data);
                if (!badge) {
                    target.textContent = '--';
                    return;
                }
                target.appendChild(badge);
            };

            const updateRow = (data) => {
                if (!data || !data.id) {
                    return;
                }

                const row = document.querySelector(`[data-project-user-row="${data.id}"]`);
                if (!row) {
                    return;
                }

                const nameCell = row.querySelector('[data-user-field="name"]');
                const emailCell = row.querySelector('[data-user-field="email"]');
                const statusCell = row.querySelector('[data-user-field="status"]');
                const projectCell = row.querySelector('[data-user-field="project"]');

                if (nameCell) {
                    nameCell.textContent = data.name || '--';
                }
                if (emailCell) {
                    emailCell.textContent = data.email || '--';
                }
                if (statusCell) {
                    updateStatusBadge(statusCell, data);
                }
                if (projectCell) {
                    projectCell.textContent = data.project_name || '--';
                }

                const editBtn = row.querySelector('[data-project-user-edit]');
                if (editBtn) {
                    editBtn.dataset.userName = data.name || '';
                    editBtn.dataset.userEmail = data.email || '';
                    editBtn.dataset.userStatus = data.status || '';
                    editBtn.dataset.projectId = data.project_id || '';
                    editBtn.dataset.projectName = data.project_name || '';
                    if (data.created_at) {
                        editBtn.dataset.createdAt = data.created_at;
                    }
                }
            };

            const updateDetails = (data) => {
                if (!detailsPanel || !data) {
                    return;
                }

                if (detailMap.name) {
                    detailMap.name.textContent = data.name || '--';
                }
                if (detailMap.email) {
                    detailMap.email.textContent = data.email || '--';
                }
                if (detailMap.project) {
                    detailMap.project.textContent = data.project_name || '--';
                }
                if (detailMap.status) {
                    updateStatusBadge(detailMap.status, data);
                }
                if (detailMap.created) {
                    detailMap.created.textContent = data.created_at || '--';
                }
                if (detailMap.updated) {
                    detailMap.updated.textContent = data.updated_at || '--';
                }

                detailsPanel.classList.remove('hidden');
            };

            const setFormUser = (data) => {
                if (!data || !data.id) {
                    return;
                }

                if (!updateUrlTemplate) {
                    return;
                }

                editForm.dataset.userId = data.id;
                editForm.action = updateUrlTemplate.replace('__USER_ID__', data.id);
                editForm.querySelector('[name="name"]').value = data.name || '';
                editForm.querySelector('[name="email"]').value = data.email || '';
                editForm.querySelector('[name="project_id"]').value = data.project_id || data.projectId || '';
                editForm.querySelector('[name="status"]').value = data.status || data.userStatus || 'active';
                editForm.querySelector('[name="password"]').value = '';
                editForm.querySelector('[name="password_confirmation"]').value = '';
            };

            const loadUser = async (payload) => {
                if (!payload?.id) {
                    return;
                }

                if (!window.fetch || !fetchUrlTemplate) {
                    setFormUser(payload);
                    return;
                }

                setStatus('Loading login details...');

                try {
                    const response = await fetch(fetchUrlTemplate.replace('__USER_ID__', payload.id), {
                        headers: { 'Accept': 'application/json' },
                    });

                    const responsePayload = await response.json().catch(() => null);
                    if (!response.ok || !responsePayload?.ok) {
                        setStatus(responsePayload?.message || 'Unable to load project login.', true);
                        setFormUser(payload);
                        return;
                    }

                    setFormUser(responsePayload.data || {});
                    setStatus('');
                } catch (error) {
                    setStatus('Unable to load project login.', true);
                    setFormUser(payload);
                }
            };

            document.addEventListener('click', (event) => {
                const btn = event.target.closest('[data-project-user-edit]');
                if (btn) {
                    const payload = {
                        id: btn.dataset.userId,
                        name: btn.dataset.userName || '',
                        email: btn.dataset.userEmail || '',
                        status: btn.dataset.userStatus || 'active',
                        projectId: btn.dataset.projectId || '',
                    };

                    clearErrors();
                    setStatus('');
                    editPanel.classList.remove('hidden');
                    editPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    loadUser(payload);
                    return;
                }

                if (event.target.closest('[data-edit-cancel]')) {
                    editPanel.classList.add('hidden');
                    setStatus('');
                }
            });

            editForm.addEventListener('submit', async (event) => {
                if (!window.fetch) {
                    return;
                }

                event.preventDefault();
                clearErrors();
                setStatus('Saving changes...');

                try {
                    const response = await fetch(editForm.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: new FormData(editForm),
                    });

                    const payload = await response.json().catch(() => null);

                    if (!response.ok) {
                        if (response.status === 422 && payload?.errors) {
                            showErrors(payload.errors);
                            setStatus('Please fix the highlighted fields.', true);
                            return;
                        }
                        setStatus(payload?.message || 'Unable to update project login.', true);
                        return;
                    }

                    if (!payload?.ok) {
                        setStatus(payload?.message || 'Unable to update project login.', true);
                        return;
                    }

                    updateRow(payload.data || {});
                    updateDetails(payload.data || {});
                    editForm.querySelector('[name="password"]').value = '';
                    editForm.querySelector('[name="password_confirmation"]').value = '';
                    setStatus(payload.message || 'Project login updated.');
                } catch (error) {
                    setStatus('Unable to update project login.', true);
                }
            });
        });
    </script>
@endpush
@endsection
