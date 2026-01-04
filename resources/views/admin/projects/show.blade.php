@extends('layouts.admin')

@section('title', 'Project #'.$project->id)
@section('page-title', 'Project')

@section('content')
    <div class="card p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="section-label">Delivery</div>
                <div class="text-2xl font-semibold text-slate-900">{{ $project->name }}</div>
                <div class="text-sm text-slate-500">Status: {{ ucfirst(str_replace('_', ' ', $project->status)) }}</div>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.projects.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Back</a>
                <form method="POST" action="{{ route('admin.projects.destroy', $project) }}" onsubmit="return confirm('Delete this project?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-full border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-600 hover:border-rose-300">Delete</button>
                </form>
            </div>
        </div>

        <div class="mt-6 grid gap-4 md:grid-cols-3 text-sm text-slate-700">
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Customer</div>
                <div class="mt-2 font-semibold text-slate-900">{{ $project->customer?->name ?? '--' }}</div>
                <div class="text-xs text-slate-500">Project ID: {{ $project->id }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Links</div>
                <div class="mt-2 text-sm text-slate-700">Order: {{ $project->order ? '#'.$project->order->order_number : '—' }}</div>
                <div class="text-sm text-slate-700">Subscription: {{ $project->subscription ? '#'.$project->subscription->id : '—' }}</div>
                <div class="text-sm text-slate-700">Advance invoice: {{ $project->advanceInvoice ? '#'.($project->advanceInvoice->number ?? $project->advanceInvoice->id) : '—' }}</div>
                <div class="text-sm text-slate-700">Final invoice: {{ $project->finalInvoice ? '#'.($project->finalInvoice->number ?? $project->finalInvoice->id) : '—' }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Dates</div>
                <div class="mt-2 font-semibold text-slate-900">Due: {{ $project->due_date ? $project->due_date->format($globalDateFormat) : '—' }}</div>
                <div class="text-xs text-slate-500">Created: {{ $project->created_at?->format($globalDateFormat.' H:i') }}</div>
                <div class="text-xs text-slate-500">Updated: {{ $project->updated_at?->format($globalDateFormat.' H:i') }}</div>
            </div>
        </div>

        <div class="mt-6 grid gap-4 lg:grid-cols-2">
            <form method="POST" action="{{ route('admin.projects.update', $project) }}" class="rounded-2xl border border-slate-200 bg-white/80 p-5 text-sm text-slate-700">
                @csrf
                @method('PUT')
                <div class="text-lg font-semibold text-slate-900">Project details</div>
                <div class="mt-2 text-xs text-slate-500">Update status, due date, notes, and invoice links.</div>

                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="text-xs text-slate-500">Name</label>
                        <input name="name" value="{{ old('name', $project->name) }}" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Type</label>
                        <select name="type" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                            @foreach($types as $type)
                                <option value="{{ $type }}" @selected(old('type', $project->type) === $type)>{{ ucfirst($type) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mt-3 grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="text-xs text-slate-500">Status</label>
                        <select name="status" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                            @foreach($statuses as $status)
                                <option value="{{ $status }}" @selected(old('status', $project->status) === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Due date</label>
                        <input type="date" name="due_date" value="{{ old('due_date', optional($project->due_date)->toDateString()) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                    </div>
                </div>

                <div class="mt-3 grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="text-xs text-slate-500">Advance invoice</label>
                        <input type="number" name="advance_invoice_id" value="{{ old('advance_invoice_id', $project->advance_invoice_id) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="Invoice ID">
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Final invoice</label>
                        <input type="number" name="final_invoice_id" value="{{ old('final_invoice_id', $project->final_invoice_id) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="Invoice ID">
                    </div>
                </div>

                <div class="mt-3">
                    <label class="text-xs text-slate-500">Notes</label>
                    <textarea name="notes" rows="4" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">{{ old('notes', $project->notes) }}</textarea>
                </div>

                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Financials</div>
                        <div class="mt-2 grid gap-2">
                            <label class="text-xs text-slate-500">Budget amount</label>
                            <input type="number" step="0.01" name="budget_amount" value="{{ old('budget_amount', $project->budget_amount) }}" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                            <label class="text-xs text-slate-500">Planned hours</label>
                            <input type="number" step="0.01" name="planned_hours" value="{{ old('planned_hours', $project->planned_hours) }}" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                            <label class="text-xs text-slate-500">Hourly cost</label>
                            <input type="number" step="0.01" name="hourly_cost" value="{{ old('hourly_cost', $project->hourly_cost) }}" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                            <label class="text-xs text-slate-500">Actual hours (optional)</label>
                            <input type="number" step="0.01" name="actual_hours" value="{{ old('actual_hours', $project->actual_hours) }}" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        </div>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Profit check</div>
                        <div class="mt-2 text-lg font-semibold {{ $financials['profitable'] ? 'text-emerald-700' : 'text-rose-700' }}">
                            {{ $financials['profitable'] ? 'Profitable' : 'Loss risk' }}
                        </div>
                        <div class="mt-1 text-xs text-slate-600">Budget: {{ number_format($financials['budget'], 2) }}</div>
                        <div class="text-xs text-slate-600">Planned cost: {{ number_format($financials['planned_cost'], 2) }}</div>
                        <div class="text-xs text-slate-600">Actual cost: {{ number_format($financials['actual_cost'], 2) }}</div>
                        <div class="text-xs font-semibold {{ $financials['profitable'] ? 'text-emerald-700' : 'text-rose-700' }}">
                            Profit: {{ number_format($financials['profit'], 2) }}
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex items-center gap-3">
                    <button type="submit" class="rounded-full bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Save changes</button>
                    <div class="text-xs text-slate-500">Edits apply immediately.</div>
                </div>
            </form>

            <div class="rounded-2xl border border-slate-200 bg-white/80 p-5 text-sm text-slate-700">
                <div class="text-lg font-semibold text-slate-900">Add task</div>
                <div class="text-xs text-slate-500">Keep track of milestones, development, and handover items.</div>

                <form method="POST" action="{{ route('admin.projects.tasks.store', $project) }}" class="mt-4 grid gap-3">
                    @csrf
                    <div>
                        <label class="text-xs text-slate-500">Title</label>
                        <input name="title" value="{{ old('title') }}" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                    </div>
                    <div class="grid gap-3 md:grid-cols-3">
                        <div>
                            <label class="text-xs text-slate-500">Status</label>
                            <select name="status" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                                @foreach($taskStatuses as $status)
                                    <option value="{{ $status }}" @selected(old('status') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-slate-500">Assignee</label>
                            <select name="assignee_id" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                                <option value="">Unassigned</option>
                                @foreach($assignees as $assignee)
                                    <option value="{{ $assignee->id }}" @selected(old('assignee_id') == $assignee->id)>{{ $assignee->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-slate-500">Due date</label>
                            <input type="date" name="due_date" value="{{ old('due_date') }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Notes</label>
                        <textarea name="notes" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">{{ old('notes') }}</textarea>
                    </div>
                    <div>
                        <button type="submit" class="rounded-full bg-slate-900 px-5 py-2 text-sm font-semibold text-white hover:bg-slate-800">Add task</button>
                    </div>
                </form>
            </div>
        </div>

            <div class="mt-6 rounded-2xl border border-slate-200 bg-white/80 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-lg font-semibold text-slate-900">Tasks</div>
                    <div class="text-xs text-slate-500">Update status as you progress.</div>
                </div>
            </div>

            <div class="mt-4 space-y-3">
                @forelse($project->tasks as $task)
                    <div class="rounded-2xl border border-slate-200 bg-white/70 p-4 text-sm text-slate-700">
                        <form method="POST" action="{{ route('admin.projects.tasks.update', [$project, $task]) }}" class="space-y-3">
                            @csrf
                            @method('PATCH')
                            <input name="title" value="{{ old("title_{$task->id}", $task->title) }}" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-900">
                            <div class="grid gap-3 md:grid-cols-3">
                                <div>
                                    <label class="text-xs text-slate-500">Status</label>
                                    <select name="status" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                                        @foreach($taskStatuses as $status)
                                            <option value="{{ $status }}" @selected($task->status === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs text-slate-500">Assignee</label>
                                    <select name="assignee_id" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                                        <option value="">Unassigned</option>
                                        @foreach($assignees as $assignee)
                                            <option value="{{ $assignee->id }}" @selected($task->assignee_id === $assignee->id)>{{ $assignee->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs text-slate-500">Due date</label>
                                    <input type="date" name="due_date" value="{{ optional($task->due_date)->toDateString() }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                                </div>
                            </div>
                            <div>
                                <label class="text-xs text-slate-500">Notes</label>
                                <textarea name="notes" rows="2" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">{{ $task->notes }}</textarea>
                            </div>
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="text-xs text-slate-500">
                                    Created: {{ $task->created_at?->format($globalDateFormat.' H:i') }},
                                    Updated: {{ $task->updated_at?->format($globalDateFormat.' H:i') }},
                                    Completed: {{ $task->completed_at?->format($globalDateFormat.' H:i') ?? '—' }}
                                </div>
                                <button type="submit" class="rounded-full bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-500">Save</button>
                            </div>
                        </form>
                        <div class="mt-2 text-right">
                            <form method="POST" action="{{ route('admin.projects.tasks.destroy', [$project, $task]) }}" onsubmit="return confirm('Delete this task?');" class="inline-block">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-full border border-rose-200 px-4 py-2 text-xs font-semibold text-rose-600 hover:border-rose-300">Delete</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-slate-200 bg-white/70 p-4 text-sm text-slate-500">No tasks yet.</div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
