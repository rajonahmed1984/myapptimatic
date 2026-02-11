@extends('layouts.admin')

@section('title', 'Project #'.$project->id.' Tasks')
@section('page-title', 'Project Tasks')

@section('content')
    @php
        $errors = $errors ?? new \Illuminate\Support\ViewErrorBag();
    @endphp

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Delivery</div>
            <div class="text-2xl font-semibold text-slate-900">{{ $project->name }}</div>
            <div class="text-sm text-slate-500">Status: {{ ucfirst(str_replace('_', ' ', $project->status)) }}</div>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.projects.show', $project) }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600" hx-boost="false">Back</a>
            <a href="{{ route('admin.projects.chat', $project) }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">
                Chat
                @php $projectChatUnreadCount = (int) ($projectChatUnreadCount ?? 0); @endphp
                <span class="ml-2 inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $projectChatUnreadCount > 0 ? 'bg-rose-600 text-white' : 'bg-slate-200 text-slate-600' }}">
                    {{ $projectChatUnreadCount }}
                </span>
            </a>
            <a href="{{ route('admin.projects.edit', $project) }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Edit</a>
        </div>
    </div>

    <div class="card p-6 space-y-6">
        @can('createTask', $project)
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Add Task</div>
                        <div class="text-xs text-slate-500">Dates are locked after creation.</div>
                    </div>
                </div>
                <form method="POST" action="{{ route('admin.projects.tasks.store', $project) }}" class="mt-4 grid gap-3" id="addTaskForm" enctype="multipart/form-data">
                    @csrf
                    <div class="grid gap-3 md:grid-cols-4">
                        <div class="md:col-span-2">
                            <label class="text-xs text-slate-500">Title</label>
                            <input name="title" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="text-xs text-slate-500">Start date</label>
                            <input type="date" name="start_date" value="{{ old('start_date', now()->toDateString()) }}" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="text-xs text-slate-500">Due date</label>
                            <input type="date" name="due_date" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-4">
                        <div>
                            <label class="text-xs text-slate-500">Task type</label>
                            <select name="task_type" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" required>
                                @foreach($taskTypeOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-slate-500">Priority</label>
                            <select name="priority" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                                @foreach($priorityOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-xs text-slate-500">Attachment (required for Upload type)</label>
                            <input type="file" name="attachment" accept=".png,.jpg,.jpeg,.webp,.pdf,.docx,.xlsx" class="mt-1 w-full text-xs text-slate-600">
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-3">
                        <div class="md:col-span-2">
                            <label class="text-xs text-slate-500">Assign to</label>
                            <select name="assignee" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                                <option value="">Select assignee</option>
                                @foreach($employees as $employee)
                                    <option value="employee:{{ $employee->id }}">Employee: {{ $employee->name }}</option>
                                @endforeach
                                @foreach($salesReps as $rep)
                                    <option value="sales_rep:{{ $rep->id }}">Sales Rep: {{ $rep->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex items-end gap-2">
                            <input type="hidden" name="customer_visible" value="0">
                            <label class="flex items-center gap-2 text-xs text-slate-600 cursor-pointer">
                                <input type="checkbox" name="customer_visible" value="1">
                                <span>Customer visible</span>
                            </label>
                        </div>
                    </div>

                    <div id="descriptionsContainer">
                        <div class="description-group grid gap-3 md:grid-cols-4 mb-3">
                            <div class="md:col-span-3">
                                <label class="text-xs text-slate-500">Description</label>
                                <input type="text" name="descriptions[]" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="Add description">
                            </div>
                            <div class="flex items-end">
                                <button type="button" class="add-description-btn rounded-full bg-teal-500 px-3 py-2 text-xs font-semibold text-white hover:bg-teal-600 w-full">+ Add description</button>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" id="clearFormBtn" class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 hover:border-slate-300">Clear</button>
                        <button type="submit" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Add task</button>
                    </div>
                </form>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const container = document.getElementById('descriptionsContainer');
                    const form = document.getElementById('addTaskForm');
                    const clearBtn = document.getElementById('clearFormBtn');

                    function addDescriptionField() {
                        const group = document.createElement('div');
                        group.className = 'description-group grid gap-3 md:grid-cols-4 mb-3';
                        group.innerHTML = `
                            <div class="md:col-span-3">
                                <input type="text" name="descriptions[]" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="Add description">
                            </div>
                            <div class="flex items-start gap-2">
                                <button type="button" class="add-description-btn rounded-full bg-teal-500 px-3 py-2 text-xs font-semibold text-white hover:bg-teal-600 flex-1">+ Add</button>
                                <button type="button" class="remove-description-btn rounded-full bg-rose-500 px-3 py-2 text-xs font-semibold text-white hover:bg-rose-600 flex-1">Remove</button>
                            </div>
                        `;
                        container.appendChild(group);
                    }

                    container.addEventListener('click', function(e) {
                        if (e.target.classList.contains('add-description-btn')) {
                            e.preventDefault();
                            addDescriptionField();
                        }
                        if (e.target.classList.contains('remove-description-btn')) {
                            e.preventDefault();
                            e.target.closest('.description-group').remove();
                        }
                    });

                    clearBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        form.reset();
                        const groups = container.querySelectorAll('.description-group');
                        if (groups.length > 1) {
                            groups.forEach((group, index) => {
                                if (index > 0) group.remove();
                            });
                        }
                    });
                });
            </script>
        @endcan

        <div class="rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400 mb-2">Tasks</div>
            @if($tasks->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                        <tr class="text-xs uppercase tracking-[0.2em] text-slate-500">
                            <th class="px-3 py-2">Task</th>
                            <th class="px-3 py-2">Dates</th>
                            <th class="px-3 py-2">Assignee</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Progress</th>
                            <th class="px-3 py-2 text-right">Actions</th>
                        </tr>
                        </thead>
                        <tbody id="tasksTableBody">
                        @foreach($tasks as $task)
                            @include('admin.projects.partials.task-row', [
                                'task' => $task,
                                'project' => $project,
                                'taskTypeOptions' => $taskTypeOptions,
                            ])
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @if($tasks->hasPages())
                    <div class="mt-4">
                        {{ $tasks->links() }}
                    </div>
                @endif
            @else
                <div class="text-xs text-slate-500">No tasks created yet.</div>
            @endif
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const addTaskForm = document.getElementById('addTaskForm');
            const tasksBody = document.getElementById('tasksTableBody');
            const defaultStartDate = @json(now()->toDateString());

            const parseError = async (response) => {
                try {
                    const data = await response.json();
                    if (data?.message) {
                        return data.message;
                    }
                    if (data?.errors) {
                        const first = Object.values(data.errors).flat()[0];
                        if (first) return first;
                    }
                } catch (error) {
                    return null;
                }
                return null;
            };

            const submitTaskForm = async (form, onSuccess) => {
                const formData = new FormData(form);
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });

                if (!response.ok) {
                    const message = await parseError(response);
                    alert(message || 'Task update failed.');
                    return;
                }

                const payload = await response.json();
                if (!payload?.ok) {
                    alert(payload?.message || 'Task update failed.');
                    return;
                }

                onSuccess(payload);
            };

            if (addTaskForm) {
                addTaskForm.addEventListener('submit', (event) => {
                    event.preventDefault();

                    submitTaskForm(addTaskForm, (payload) => {
                        const rowHtml = payload?.data?.row_html;
                        if (!rowHtml) {
                            location.reload();
                            return;
                        }

                        if (!tasksBody) {
                            location.reload();
                            return;
                        }

                        tasksBody.insertAdjacentHTML('afterbegin', rowHtml);
                        addTaskForm.reset();
                        const startDateInput = addTaskForm.querySelector('input[name="start_date"]');
                        if (startDateInput) {
                            startDateInput.value = defaultStartDate;
                        }

                        const groups = document.querySelectorAll('#descriptionsContainer .description-group');
                        if (groups.length > 1) {
                            groups.forEach((group, index) => {
                                if (index > 0) group.remove();
                            });
                        }
                    });
                });
            }

            if (tasksBody) {
                tasksBody.addEventListener('submit', (event) => {
                    const form = event.target;
                    if (!form.classList.contains('task-update-form')) {
                        return;
                    }

                    event.preventDefault();
                    submitTaskForm(form, (payload) => {
                        const rowHtml = payload?.data?.row_html;
                        const taskId = payload?.data?.task_id;
                        if (!rowHtml || !taskId) {
                            location.reload();
                            return;
                        }

                        const row = document.getElementById(`task-row-${taskId}`);
                        if (!row) {
                            location.reload();
                            return;
                        }

                        row.outerHTML = rowHtml;
                    });
                });
            }
        });
    </script>
@endsection
