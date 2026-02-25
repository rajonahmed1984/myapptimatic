@php
    $isEdit = isset($task) && $task;
    $taskStatusFilter = old('task_status_filter', request()->query('status'));
    $taskStatusFilter = in_array($taskStatusFilter, ['pending', 'in_progress', 'blocked', 'completed'], true) ? $taskStatusFilter : null;
    $ajaxForm = $ajaxForm ?? true;
    $returnToUrl = $returnToUrl ?? route('admin.projects.tasks.index', array_filter([
        'project' => $project,
        'status' => $taskStatusFilter,
    ], fn ($value) => $value !== null && $value !== ''));
    $selectedAssignees = collect(old('assignees', $isEdit ? $task->assignments->map(fn ($assignment) => $assignment->assignee_type . ':' . $assignment->assignee_id)->all() : []))
        ->filter()
        ->values()
        ->all();
@endphp

<div>
    @if($errors->any())
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs text-rose-700">
            <ul class="space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form
        method="POST"
        action="{{ $isEdit ? route('admin.projects.tasks.update', [$project, $task]) : route('admin.projects.tasks.store', $project) }}"
        @if($ajaxForm) data-ajax-form="true" @endif
        enctype="multipart/form-data"
        class="space-y-4"
    >
        @csrf
        @if($isEdit)
            @method('PATCH')
        @endif
        <input type="hidden" name="task_status_filter" value="{{ $taskStatusFilter }}">
        @if(! $ajaxForm)
            <input type="hidden" name="return_to" value="{{ $returnToUrl }}">
        @endif

        <div class="grid gap-4 md:grid-cols-2">
            @if(! $isEdit)
                <div class="md:col-span-2">
                    <label class="text-xs uppercase tracking-[0.2em] text-slate-500">Title</label>
                    <input name="title" value="{{ old('title') }}" required class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-xs uppercase tracking-[0.2em] text-slate-500">Start date</label>
                    <input type="text" placeholder="DD-MM-YYYY" inputMode="numeric" name="start_date" value="{{ old('start_date', now()->format(config('app.date_format', 'd-m-Y'))) }}" required class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-xs uppercase tracking-[0.2em] text-slate-500">Due date</label>
                    <input type="text" placeholder="DD-MM-YYYY" inputMode="numeric" name="due_date" value="{{ old('due_date') }}" required class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />
                </div>
            @endif

            @if($isEdit)
                <div>
                    <label class="text-xs uppercase tracking-[0.2em] text-slate-500">Status</label>
                    <select name="status" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" required>
                        @foreach($statusOptions as $status)
                            <option value="{{ $status }}" @selected(old('status', $task->status) === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div>
                <label class="text-xs uppercase tracking-[0.2em] text-slate-500">Task type</label>
                <select name="task_type" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" required>
                    @foreach($taskTypeOptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('task_type', $task->task_type ?? null) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="text-xs uppercase tracking-[0.2em] text-slate-500">Priority</label>
                <select name="priority" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                    @foreach($priorityOptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('priority', $task->priority ?? 'medium') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            @if($isEdit)
                <div>
                    <label class="text-xs uppercase tracking-[0.2em] text-slate-500">Progress (%)</label>
                    <input type="number" min="0" max="100" name="progress" value="{{ old('progress', (int) ($task->progress ?? 0)) }}" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-xs uppercase tracking-[0.2em] text-slate-500">Estimate (minutes)</label>
                    <input type="number" min="0" name="time_estimate_minutes" value="{{ old('time_estimate_minutes', $task->time_estimate_minutes) }}" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />
                </div>
            @endif

            <div class="md:col-span-2">
                <label class="text-xs uppercase tracking-[0.2em] text-slate-500">Assignees</label>
                <select name="assignees[]" multiple size="6" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                    @foreach($employees as $employee)
                        @php $value = 'employee:' . $employee->id; @endphp
                        <option value="{{ $value }}" @selected(in_array($value, $selectedAssignees, true))>
                            Employee: {{ $employee->name }}
                        </option>
                    @endforeach
                    @foreach($salesReps as $rep)
                        @php $value = 'sales_rep:' . $rep->id; @endphp
                        <option value="{{ $value }}" @selected(in_array($value, $selectedAssignees, true))>
                            Sales rep: {{ $rep->name }}
                        </option>
                    @endforeach
                </select>
                @if(! $isEdit)
                    <p class="mt-1 text-xs text-slate-500">Select at least one assignee.</p>
                @endif
            </div>

            @if(! $isEdit)
                <div class="md:col-span-2">
                    <label class="text-xs uppercase tracking-[0.2em] text-slate-500">Attachment (required for Upload type)</label>
                    <input type="file" name="attachment" accept=".png,.jpg,.jpeg,.webp,.pdf,.docx,.xlsx" class="mt-1 w-full text-xs text-slate-600" />
                </div>
            @endif

            <div class="md:col-span-2">
                <label class="text-xs uppercase tracking-[0.2em] text-slate-500">Description</label>
                <textarea name="description" rows="3" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">{{ old('description', $task->description ?? '') }}</textarea>
            </div>

            @if($isEdit)
                <div>
                    <label class="text-xs uppercase tracking-[0.2em] text-slate-500">Tags</label>
                    <input name="tags" value="{{ old('tags', $task->tags ? implode(', ', $task->tags) : '') }}" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-xs uppercase tracking-[0.2em] text-slate-500">Relationships (IDs)</label>
                    <input name="relationship_ids" value="{{ old('relationship_ids', $task->relationship_ids ? implode(', ', $task->relationship_ids) : '') }}" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />
                </div>
                <div class="md:col-span-2">
                    <label class="text-xs uppercase tracking-[0.2em] text-slate-500">Notes</label>
                    <textarea name="notes" rows="2" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">{{ old('notes', $task->notes) }}</textarea>
                </div>
            @endif
        </div>

        <div class="flex items-center justify-between border-t border-slate-200 pt-4">
            <label class="inline-flex items-center gap-2 text-xs text-slate-600">
                <input type="hidden" name="customer_visible" value="0">
                <input type="checkbox" name="customer_visible" value="1" @checked(old('customer_visible', $task->customer_visible ?? false)) class="rounded border-slate-300 text-teal-600">
                <span>Customer visible</span>
            </label>

            <div class="flex items-center gap-2">
                @if($ajaxForm)
                    <button type="button" data-ajax-modal-close="true" class="rounded-full border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-600 hover:border-slate-400">Cancel</button>
                @else
                    <a href="{{ $returnToUrl }}" class="rounded-full border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-600 hover:border-slate-400">Cancel</a>
                @endif
                <button type="submit" class="rounded-full bg-slate-900 px-5 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                    {{ $isEdit ? 'Update task' : 'Add task' }}
                </button>
            </div>
        </div>
    </form>
</div>
