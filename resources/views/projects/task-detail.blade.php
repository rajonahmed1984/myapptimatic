@extends($layout ?? 'layouts.admin')

@section('title', 'Task')
@section('page-title', 'Task')

@php
    $routePrefix = $routePrefix ?? (function () {
        $routeName = request()->route()?->getName();
        if (! is_string($routeName) || $routeName === '') {
            return 'admin';
        }

        return explode('.', $routeName)[0] ?: 'admin';
    })();
@endphp

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Project Task</div>
            <div class="flex flex-wrap items-center gap-3">
                <div class="text-2xl font-semibold text-slate-900">{{ $task->title }}</div>
                <span class="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600">
                    {{ $taskTypeOptions[$task->task_type] ?? ucfirst($task->task_type ?? 'Task') }}
                </span>
            </div>
            <div class="mt-1 text-sm text-slate-500">Project: {{ $project->name }}</div>
        </div>
        <a href="{{ $backRoute }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600" hx-boost="false">Back to project</a>
    </div>

    @if($task->task_type === 'upload' && $uploadActivities->isNotEmpty())
        <div class="mb-6 rounded-2xl border border-slate-200 bg-white/80 p-4">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Latest upload</div>
            <div class="mt-3 flex flex-wrap items-center gap-4">
                @php $latestUpload = $uploadActivities->last(); @endphp
                @if($latestUpload?->isImageAttachment())
                    <img src="{{ route($attachmentRouteName, [$project, $task, $latestUpload]) }}" alt="Attachment" class="h-24 w-24 rounded-xl border border-slate-200 object-cover" />
                @else
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                        {{ $latestUpload?->attachmentName() ?? 'Attachment' }}
                    </div>
                @endif
            </div>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <div class="space-y-6">
            <div class="card p-6">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Task Details</div>

                @if($canEdit)
                    <form method="POST" action="{{ $updateRoute }}" class="mt-4 space-y-4">
                        @csrf
                        @method('PATCH')

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="text-xs text-slate-500">Status</label>
                                <select name="status" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                                    @foreach(['pending','in_progress','blocked','completed'] as $status)
                                        <option value="{{ $status }}" @selected($task->status === $status)>{{ ucfirst(str_replace('_',' ', $status)) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-xs text-slate-500">Task type</label>
                                <select name="task_type" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                                    @foreach($taskTypeOptions as $value => $label)
                                        <option value="{{ $value }}" @selected($task->task_type === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-xs text-slate-500">Priority</label>
                                <select name="priority" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                                    @foreach($priorityOptions as $value => $label)
                                        <option value="{{ $value }}" @selected($task->priority === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-xs text-slate-500">Time estimate (minutes)</label>
                                <input name="time_estimate_minutes" type="number" min="0" value="{{ old('time_estimate_minutes', $task->time_estimate_minutes) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" />
                            </div>
                        </div>

                        <div>
                            <label class="text-xs text-slate-500 block mb-2">Assignees</label>
                            <div class="space-y-2 rounded-xl border border-slate-200 bg-white p-3">
                                @if($employees->isNotEmpty())
                                    <div class="text-xs font-semibold text-slate-600 mb-2">Employees</div>
                                    @foreach($employees as $employee)
                                        @php $value = 'employee:'.$employee->id; @endphp
                                        <label class="flex items-center gap-2 text-xs text-slate-600 cursor-pointer">
                                            <input type="checkbox" name="assignees[]" value="{{ $value }}" @checked(in_array($value, $assignees, true)) class="rounded border-slate-200" />
                                            <span>{{ $employee->name }}</span>
                                        </label>
                                    @endforeach
                                @endif
                                @if($salesReps->isNotEmpty())
                                    <div class="text-xs font-semibold text-slate-600 mb-2 mt-3">Sales Representatives</div>
                                    @foreach($salesReps as $rep)
                                        @php $value = 'sales_rep:'.$rep->id; @endphp
                                        <label class="flex items-center gap-2 text-xs text-slate-600 cursor-pointer">
                                            <input type="checkbox" name="assignees[]" value="{{ $value }}" @checked(in_array($value, $assignees, true)) class="rounded border-slate-200" />
                                            <span>{{ $rep->name }}</span>
                                        </label>
                                    @endforeach
                                @endif
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="text-xs text-slate-500">Start date</label>
                                <input value="{{ $task->start_date?->format('Y-m-d') }}" disabled class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500" />
                            </div>
                            <div>
                                <label class="text-xs text-slate-500">Due date</label>
                                <input value="{{ $task->due_date?->format('Y-m-d') }}" disabled class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500" />
                            </div>
                        </div>

                        <div>
                            <label class="text-xs text-slate-500">Tags (comma separated)</label>
                            <input name="tags" value="{{ old('tags', $task->tags ? implode(', ', $task->tags) : '') }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" />
                        </div>

                        <div>
                            <label class="text-xs text-slate-500">Relationships (task IDs, comma separated)</label>
                            <input name="relationship_ids" value="{{ old('relationship_ids', $task->relationship_ids ? implode(', ', $task->relationship_ids) : '') }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="e.g. 12, 34" />
                            <p class="mt-1 text-xs text-slate-500">Future-ready field for linking related tasks.</p>
                        </div>

                        <div class="mt-6 pt-6 border-t border-slate-200">
                            <div class="flex items-center justify-between mb-4">
                                <label class="text-xs uppercase tracking-[0.2em] text-slate-400">Subtasks</label>
                                <button type="button" id="addSubtaskBtn" class="text-xs text-teal-600 hover:text-teal-700 font-semibold">+ Add subtask</button>
                            </div>
                            
                            @if($task->subtasks->isNotEmpty())
                                <div class="space-y-2 mb-4">
                                    @foreach($task->subtasks as $subtask)
                                        <div class="flex items-center gap-3 p-3 rounded-lg border border-slate-200 bg-white/50">
                                            <input type="checkbox" data-subtask-id="{{ $subtask->id }}" @checked($subtask->is_completed) class="subtask-checkbox rounded" />
                                            <div class="flex-1">
                                                <div class="text-sm font-medium text-slate-900">{{ $subtask->title }}</div>
                                                <div class="text-xs text-slate-500 space-y-1">
                                                    @if($subtask->due_date)
                                                        <div>
                                                            Due: {{ $subtask->due_date->format($globalDateFormat) }}
                                                            @if($subtask->due_time)
                                                                at {{ $subtask->due_time }}
                                                            @endif
                                                        </div>
                                                    @endif
                                                    <div>Created: {{ $subtask->created_at->format($globalDateFormat . ' H:i') }} @if($subtask->is_completed)| Completed: {{ $subtask->completed_at->format($globalDateFormat . ' H:i') }}@endif</div>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <button type="button" class="subtask-status-btn rounded-full border border-emerald-200 px-3 py-1 text-xs font-semibold text-emerald-700 hover:border-emerald-300" data-subtask-id="{{ $subtask->id }}" data-status="complete">
                                                    Complete
                                                </button>
                                                <button type="button" class="subtask-status-btn rounded-full border border-amber-200 px-3 py-1 text-xs font-semibold text-amber-700 hover:border-amber-300" data-subtask-id="{{ $subtask->id }}" data-status="in_progress">
                                                    Inprogress
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <div id="subtaskForm" style="display: none;" class="mt-4 p-4 rounded-lg border border-slate-200 bg-slate-50 space-y-3">
                                <div>
                                    <label class="text-xs text-slate-500">Subtask title</label>
                                    <input type="text" id="subtaskTitle" placeholder="Enter subtask..." class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" />
                                </div>
                                <div class="grid gap-3 md:grid-cols-2">
                                    <div>
                                        <label class="text-xs text-slate-500">Due date</label>
                                        <input type="date" id="subtaskDate" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" />
                                    </div>
                                    <div>
                                        <label class="text-xs text-slate-500">Due time</label>
                                        <input type="time" id="subtaskTime" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" />
                                    </div>
                                </div>
                                <div class="flex gap-2 justify-end">
                                    <button type="button" id="cancelSubtaskBtn" class="px-3 py-2 text-xs rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-100">Cancel</button>
                                    <button type="button" id="saveSubtaskBtn" class="px-3 py-2 text-xs rounded-lg bg-slate-900 text-white hover:bg-slate-800">Add subtask</button>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <label class="flex items-center gap-2 text-xs text-slate-600">
                                <input type="hidden" name="customer_visible" value="0" />
                                <input type="checkbox" name="customer_visible" value="1" @checked($task->customer_visible) />
                                <span>Customer visible</span>
                            </label>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Save changes</button>
                        </div>
                    </form>
                @else
                    <div class="mt-4 space-y-3 text-sm text-slate-700">
                        <div>
                            <span class="text-xs uppercase tracking-[0.2em] text-slate-400">Status</span>
                            <div class="mt-1 font-semibold text-slate-900">{{ ucfirst(str_replace('_',' ', $task->status)) }}</div>
                        </div>
                        <div>
                            <span class="text-xs uppercase tracking-[0.2em] text-slate-400">Assignees</span>
                            @php
                                $assigneeNames = $task->assignments->map(fn ($assignment) => $assignment->assigneeName())->filter()->implode(', ');
                                if ($assigneeNames === '' && $task->assigned_type && $task->assigned_id) {
                                    $assigneeNames = ucfirst(str_replace('_', ' ', $task->assigned_type)) . ' #' . $task->assigned_id;
                                }
                            @endphp
                            <div class="mt-1 text-slate-700">{{ $assigneeNames ?: '--' }}</div>
                        </div>
                        <div>
                            <span class="text-xs uppercase tracking-[0.2em] text-slate-400">Priority</span>
                            <div class="mt-1 text-slate-700">{{ $priorityOptions[$task->priority] ?? ucfirst($task->priority ?? 'medium') }}</div>
                        </div>
                        <div>
                            <span class="text-xs uppercase tracking-[0.2em] text-slate-400">Time estimate</span>
                            <div class="mt-1 text-slate-700">
                                {{ $task->time_estimate_minutes ? $task->time_estimate_minutes.' min' : '--' }}
                            </div>
                        </div>
                        <div>
                            <span class="text-xs uppercase tracking-[0.2em] text-slate-400">Tags</span>
                            <div class="mt-1 text-slate-700">{{ $task->tags ? implode(', ', $task->tags) : '--' }}</div>
                        </div>
                        <div>
                            <span class="text-xs uppercase tracking-[0.2em] text-slate-400">Dates</span>
                            <div class="mt-1 text-slate-700">Start: {{ $task->start_date?->format($globalDateFormat) ?? '--' }} | Due: {{ $task->due_date?->format($globalDateFormat) ?? '--' }}</div>
                        </div>
                        <div>
                            <span class="text-xs uppercase tracking-[0.2em] text-slate-400">Relationships</span>
                            <div class="mt-1 text-slate-700">{{ $task->relationship_ids ? implode(', ', $task->relationship_ids) : '--' }}</div>
                        </div>
                        <div>
                            <span class="text-xs uppercase tracking-[0.2em] text-slate-400">Created & Completed</span>
                            <div class="mt-1 text-slate-700">Created: {{ $task->created_at->format($globalDateFormat . ' H:i') }} @if($task->status === 'completed')| Completed: {{ $task->updated_at->format($globalDateFormat . ' H:i') }}@endif</div>
                        </div>
                        
                        @if($task->subtasks->isNotEmpty())
                            <div class="mt-6 pt-6 border-t border-slate-200">
                                <div class="flex items-center justify-between mb-3">
                                    <span class="text-xs uppercase tracking-[0.2em] text-slate-400">Subtasks</span>
                                    <span class="text-xs text-slate-600">{{ $task->subtasks->where('is_completed', true)->count() }}/{{ $task->subtasks->count() }} completed</span>
                                </div>
                                <div class="w-full bg-slate-200 rounded-full h-2 mb-4">
                                    <div class="bg-teal-500 h-2 rounded-full transition-all" style="width: {{ $task->progress }}%"></div>
                                </div>
                                <div class="space-y-2">
                                    @foreach($task->subtasks as $subtask)
                                        <div class="flex items-center gap-3 p-3 rounded-lg border border-slate-200 bg-white/50">
                                            <input type="checkbox" data-subtask-id="{{ $subtask->id }}" @checked($subtask->is_completed) class="subtask-checkbox rounded" />
                                            <div class="flex-1">
                                                <div class="text-sm font-medium {{ $subtask->is_completed ? 'line-through text-slate-400' : 'text-slate-900' }}">{{ $subtask->title }}</div>
                                                <div class="text-xs text-slate-500 space-y-1">
                                                    @if($subtask->due_date)
                                                        <div>
                                                            Due: {{ $subtask->due_date->format($globalDateFormat) }}
                                                            @if($subtask->due_time)
                                                                at {{ $subtask->due_time }}
                                                            @endif
                                                        </div>
                                                    @endif
                                                    <div>Created: {{ $subtask->created_at->format($globalDateFormat . ' H:i') }} @if($subtask->is_completed)| Completed: {{ $subtask->completed_at->format($globalDateFormat . ' H:i') }}@endif</div>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <button type="button" class="subtask-status-btn rounded-full border border-emerald-200 px-3 py-1 text-xs font-semibold text-emerald-700 hover:border-emerald-300" data-subtask-id="{{ $subtask->id }}" data-status="complete">
                                                    Complete
                                                </button>
                                                <button type="button" class="subtask-status-btn rounded-full border border-amber-200 px-3 py-1 text-xs font-semibold text-amber-700 hover:border-amber-300" data-subtask-id="{{ $subtask->id }}" data-status="in_progress">
                                                    Inprogress
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            @if($uploadActivities->isNotEmpty())
                <div class="card p-6">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Attachments</div>
                    <div class="mt-4 space-y-3">
                        @foreach($uploadActivities as $upload)
                            <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-3 text-sm">
                                <div class="flex items-center gap-3">
                                    @if($upload->isImageAttachment())
                                        <img src="{{ route($attachmentRouteName, [$project, $task, $upload]) }}" alt="Attachment" class="h-12 w-12 rounded-lg border border-slate-200 object-cover" />
                                    @else
                                        <div class="h-12 w-12 rounded-lg border border-slate-200 bg-slate-50 flex items-center justify-center text-xs text-slate-500">DOC</div>
                                    @endif
                                    <div>
                                        <div class="text-sm font-semibold text-slate-700">{{ $upload->attachmentName() ?? 'Attachment' }}</div>
                                        <div class="text-xs text-slate-500">{{ $upload->created_at?->format('M d, Y H:i') }}</div>
                                    </div>
                                </div>
                                <a href="{{ route($attachmentRouteName, [$project, $task, $upload]) }}" class="text-xs font-semibold text-teal-600 hover:text-teal-500">Download</a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="space-y-6">
            <div class="card p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Activity</div>
                        <div class="text-xs text-slate-500">Comments, uploads, and updates in real time.</div>
                    </div>
                </div>
                @php
                    $lastActivityId = $activities->last()?->id ?? 0;
                    $oldestActivityId = $activities->first()?->id ?? 0;
                @endphp
                <div id="task-activity-feed"
                     data-activity-url="{{ $activityItemsUrl ?? '' }}"
                     data-last-id="{{ $lastActivityId }}"
                     data-oldest-id="{{ $oldestActivityId }}"
                     class="mt-4 max-h-[65vh] space-y-4 overflow-y-auto pr-1">
                    @include('projects.partials.task-activity-feed', [
                        'activities' => $activities,
                        'project' => $project,
                        'task' => $task,
                        'attachmentRouteName' => $attachmentRouteName,
                        'currentActorType' => $currentActorType,
                        'currentActorId' => $currentActorId,
                    ])
                </div>
                @if(isset($activitiesPaginator) && $activitiesPaginator->hasPages())
                    <div class="mt-4">
                        {{ $activitiesPaginator->links() }}
                    </div>
                @endif
            </div>

            @if($canPost)
                <div class="card p-6 space-y-4">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Add comment</div>
                    <form method="POST" action="{{ $activityPostRoute }}" class="space-y-3" id="activityPostForm">
                        @csrf
                        <textarea name="message" rows="3" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="Share an update..."></textarea>
                        @error('message')
                            <div class="text-xs text-rose-600">{{ $message }}</div>
                        @enderror
                        <div class="flex justify-end">
                            <button type="submit" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Post comment</button>
                        </div>
                    </form>

                    <div class="border-t border-slate-200 pt-4">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Upload file</div>
                        <form method="POST" action="{{ $uploadRoute }}" enctype="multipart/form-data" class="mt-3 space-y-3">
                            @csrf
                            <input type="file" name="attachment" accept=".png,.jpg,.jpeg,.webp,.pdf,.docx,.xlsx" class="block w-full text-sm text-slate-600" />
                            <input type="text" name="message" placeholder="Optional note" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" />
                            <p class="text-xs text-slate-500">Max {{ $uploadMaxMb }}MB per upload.</p>
                            @error('attachment')
                                <div class="text-xs text-rose-600">{{ $message }}</div>
                            @enderror
                            <div class="flex justify-end">
                                <button type="submit" class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Upload</button>
                            </div>
                        </form>
                    </div>
                </div>
            @else
                <div class="card p-6 text-sm text-slate-500">
                    You have read-only access to this task. Commenting is disabled.
                </div>
            @endif
        </div>
    </div>

    <script data-script-key="{{ $routePrefix }}-task-detail">
        // Subtask management
        (() => {
        const pageKey = @json($routePrefix . '.projects.tasks.show');
        window.PageInit = window.PageInit || {};
        window.PageInit[pageKey] = () => {
            if (typeof window.__taskDetailCleanup === 'function') {
                window.__taskDetailCleanup();
            }
            const addSubtaskBtn = document.getElementById('addSubtaskBtn');
            const subtaskForm = document.getElementById('subtaskForm');
            const cancelSubtaskBtn = document.getElementById('cancelSubtaskBtn');
            const saveSubtaskBtn = document.getElementById('saveSubtaskBtn');
            const subtaskCheckboxes = document.querySelectorAll('.subtask-checkbox');
            const subtaskStatusButtons = document.querySelectorAll('.subtask-status-btn');

            if (addSubtaskBtn && subtaskForm) {
                addSubtaskBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    subtaskForm.style.display = 'block';
                    document.getElementById('subtaskTitle').focus();
                });

                cancelSubtaskBtn.addEventListener('click', () => {
                    subtaskForm.style.display = 'none';
                    document.getElementById('subtaskTitle').value = '';
                    document.getElementById('subtaskDate').value = '';
                    document.getElementById('subtaskTime').value = '';
                });

                saveSubtaskBtn.addEventListener('click', () => {
                    const title = document.getElementById('subtaskTitle').value.trim();
                    const date = document.getElementById('subtaskDate').value;
                    const time = document.getElementById('subtaskTime').value;

                    if (!title) {
                        alert('Please enter a subtask title');
                        return;
                    }

                    // Send to server - you'll need to create an endpoint for this
                    const formData = new FormData();
                    formData.append('title', title);
                    formData.append('due_date', date || null);
                    formData.append('due_time', time || null);
                    formData.append('_token', document.querySelector('[name="_token"]').value);

                    fetch(`{{ route($routePrefix . '.projects.tasks.subtasks.store', [$project, $task]) }}`, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (response.ok) {
                            location.reload();
                        }
                    })
                    .catch(() => alert('Error adding subtask'));
                });
            }

            // Handle subtask completion
            subtaskCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', () => {
                    const subtaskId = checkbox.getAttribute('data-subtask-id');
                    const isCompleted = checkbox.checked;

                    const formData = new FormData();
                    formData.append('is_completed', isCompleted ? 1 : 0);
                    formData.append('_token', document.querySelector('[name="_token"]').value);

                    fetch(`{{ route($routePrefix . '.projects.tasks.subtasks.update', [$project, $task, ':id']) }}`.replace(':id', subtaskId), {
                        method: 'PATCH',
                        body: formData
                    })
                    .then(() => {
                        location.reload();
                    })
                    .catch(() => alert('Error updating subtask'));
                });
            });

            subtaskStatusButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const subtaskId = button.getAttribute('data-subtask-id');
                    const status = button.getAttribute('data-status');
                    const checkbox = document.querySelector(`.subtask-checkbox[data-subtask-id="${subtaskId}"]`);
                    if (!checkbox) {
                        return;
                    }
                    checkbox.checked = status === 'complete';
                    checkbox.dispatchEvent(new Event('change', { bubbles: true }));
                });
            });

            // Activity feed polling
            const container = document.getElementById('task-activity-feed');
            const activityUrl = container?.dataset?.activityUrl || @json($activityItemsUrl ?? '');
            const activityForm = document.getElementById('activityPostForm');
            const activityPostUrl = @json($activityItemsPostUrl ?? '');

            if (!container || !activityUrl) {
                return;
            }

            let lastId = Number(container.dataset.lastId || {{ $lastActivityId }} || 0);
            let oldestId = Number(container.dataset.oldestId || {{ $oldestActivityId }} || 0);
            let isLoadingOlder = false;
            let reachedStart = false;

            const scrollToBottom = () => {
                container.scrollTop = container.scrollHeight;
            };

            const isNearBottom = () => {
                const threshold = 120;
                return (container.scrollHeight - container.scrollTop - container.clientHeight) < threshold;
            };

            const appendItems = (items) => {
                if (!items || !items.length) {
                    return;
                }
                items.forEach((item) => {
                    if (!item?.html) return;
                    container.insertAdjacentHTML('beforeend', item.html);
                    if (item.id) {
                        lastId = Math.max(lastId, item.id);
                        if (!oldestId) {
                            oldestId = item.id;
                        }
                    }
                });
            };

            const prependItems = (items) => {
                if (!items || !items.length) {
                    return;
                }
                const previousHeight = container.scrollHeight;
                const previousTop = container.scrollTop;
                for (let i = items.length - 1; i >= 0; i -= 1) {
                    const item = items[i];
                    if (!item?.html) continue;
                    container.insertAdjacentHTML('afterbegin', item.html);
                }
                const oldestItemId = items[0]?.id;
                if (oldestItemId) {
                    oldestId = oldestId ? Math.min(oldestId, oldestItemId) : oldestItemId;
                }
                const heightDiff = container.scrollHeight - previousHeight;
                container.scrollTop = previousTop + heightDiff;
            };

            const fetchItems = async (params) => {
                const url = new URL(activityUrl, window.location.origin);
                Object.entries(params).forEach(([key, value]) => {
                    if (value !== null && value !== undefined) {
                        url.searchParams.set(key, value);
                    }
                });

                const response = await fetch(url.toString(), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!response.ok) {
                    return [];
                }
                const payload = await response.json();
                return payload?.data?.items || [];
            };

            const loadOlder = async () => {
                if (!oldestId || isLoadingOlder || reachedStart) {
                    return;
                }
                isLoadingOlder = true;
                const items = await fetchItems({ before_id: oldestId, limit: 30 });
                if (items.length === 0) {
                    reachedStart = true;
                } else {
                    prependItems(items);
                }
                isLoadingOlder = false;
            };

            if (activityForm && activityPostUrl) {
                activityForm.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    const formData = new FormData(activityForm);

                    const response = await fetch(activityPostUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    });

                    if (!response.ok) {
                        alert('Comment send failed.');
                        return;
                    }

                    const payload = await response.json();
                    const items = payload?.data?.items || [];
                    if (items.length) {
                        appendItems(items);
                        scrollToBottom();
                    }
                    activityForm.reset();
                });
            }

            scrollToBottom();

            container.addEventListener('scroll', async () => {
                if (container.scrollTop <= 80) {
                    loadOlder();
                }
            });

            let liveUpdateTimer = setInterval(async () => {
                const keepAtBottom = isNearBottom();
                const items = await fetchItems({ after_id: lastId, limit: 30 });
                if (items.length) {
                    appendItems(items);
                    if (keepAtBottom) {
                        scrollToBottom();
                    }
                }
            }, 5000);

            const cleanup = () => {
                if (liveUpdateTimer) {
                    clearInterval(liveUpdateTimer);
                    liveUpdateTimer = null;
                }
            };

            window.__taskDetailCleanup = cleanup;
            window.addEventListener('beforeunload', cleanup);
        };

        if (document.querySelector('#appContent')?.dataset?.pageKey === pageKey) {
            window.PageInit[pageKey]();
        }
        })();
    </script>
@endsection
