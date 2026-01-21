@extends($layout)

@section('title', $task->title)
@section('page-title', 'Task Details')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <a href="{{ $backRoute }}" class="text-teal-600 hover:text-teal-700 text-sm font-semibold">← Back to project</a>
        <div class="text-xs text-slate-500">Project: {{ $project->name }}</div>
    </div>

    <div class="space-y-6">
        <!-- Main Content -->
        <div class="space-y-6">
            <!-- Task Header -->
            <div class="card p-6">
                <div class="flex items-start justify-between gap-4 mb-4">
                    <div class="flex-1">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400 mb-2">{{ $task->title }}</div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold gap-2"
                                style="background-color: {{ $statusColors[$task->status]['bg'] ?? '#f1f5f9' }}; color: {{ $statusColors[$task->status]['text'] ?? '#64748b' }};">
                                {{ ucfirst(str_replace('_',' ', $task->status)) }}
                            </span>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-700 gap-1">
                                <span class="inline-block w-1.5 h-1.5 rounded-full"
                                    style="background-color: {{ $priorityColors[$task->priority]['color'] ?? '#94a3b8' }};"></span>
                                {{ $priorityOptions[$task->priority] ?? 'Medium' }}
                            </span>
                            <span class="px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700">
                                {{ $taskTypeOptions[$task->task_type] ?? 'Task' }}
                            </span>
                        </div>
                        @if($routePrefix === 'client' && $canEdit)
                            <div class="mt-3">
                                <a href="#task-edit" class="text-xs font-semibold text-teal-600 hover:text-teal-700">Edit task</a>
                            </div>
                        @endif
                        @if(!($routePrefix === 'admin' && $canEdit))
                            <div class="grid gap-4 md:grid-cols-3 pd-2 mt-4">
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">Start Date</div>
                                    <div class="text-sm font-medium text-slate-900">{{ $task->start_date?->format($globalDateFormat) ?? '—' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">Due Date</div>
                                    <div class="text-sm font-medium text-slate-900">{{ $task->due_date?->format($globalDateFormat) ?? '—' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">Time Estimate</div>
                                    <div class="text-sm font-medium text-slate-900">{{ $task->time_estimate_minutes ? $task->time_estimate_minutes . ' min' : '—' }}</div>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">Tags</div>
                                <div class="text-sm text-slate-700">{{ $task->tags ? implode(', ', $task->tags) : '—' }}</div>
                            </div>
                        @endif
                    </div>
                    <div>
                        <div class="mt-4 grid gap-6 md:grid-cols-2">
                            <!-- Assignees Card -->
                            <div class="card p-5">
                                <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-600 mb-3">Assignees</h3>
                                @php
                                    $assigneeList = $task->assignments->map(fn ($a) => ['name' => $a->assigneeName(), 'type' => $a->assignee_type])
                                        ->filter(fn ($a) => $a['name'])
                                        ->unique('name')
                                        ->values();
                                    if ($assigneeList->isEmpty() && $task->assigned_type && $task->assigned_id) {
                                        $assigneeList = collect([['name' => ucfirst(str_replace('_', ' ', $task->assigned_type)) . ' #' . $task->assigned_id, 'type' => $task->assigned_type]]);
                                    }
                                @endphp
                                
                                @if($assigneeList->isNotEmpty())
                                    <div class="space-y-2">
                                        @foreach($assigneeList as $assignee)
                                            <div class="flex items-center gap-2 p-2 rounded-lg bg-slate-50">
                                                <div class="w-8 h-8 rounded-full bg-teal-100 flex items-center justify-center text-xs font-bold text-teal-700">
                                                    {{ substr($assignee['name'], 0, 1) }}
                                                </div>
                                                <span class="text-xs font-medium text-slate-700 truncate">{{ $assignee['name'] }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="text-xs text-slate-500">No assignees</div>
                                @endif
                            </div>

                            <!-- Created & Completed Card -->
                            <div class="card p-5">
                                <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-600 mb-3">Timeline</h3>
                                <div class="space-y-3 text-xs text-slate-600">
                                    <div>
                                        <div class="text-slate-500 mb-1">Created</div>
                                        <div class="font-medium text-slate-900">{{ $task->created_at->format($globalDateFormat . ' H:i') }}</div>
                                    </div>
                                    @if($task->status === 'completed')
                                        <div class="pt-3 border-t border-slate-200">
                                            <div class="text-slate-500 mb-1">Completed</div>
                                            <div class="font-medium text-slate-900">{{ $task->updated_at->format($globalDateFormat . ' H:i') }}</div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Task Upload Section -->
            @if($task->task_type === 'upload' && $uploadActivities->isNotEmpty())
                <div class="card p-6">
                    <div class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-3">Latest Upload</div>
                    <div class="flex items-center gap-4">
                        @php $latestUpload = $uploadActivities->last(); @endphp
                        <a href="{{ route($attachmentRouteName, [$project, $task, $latestUpload]) }}" target="_blank" rel="noopener" class="block">
                            @if($latestUpload?->isImageAttachment())
                                <img src="{{ route($attachmentRouteName, [$project, $task, $latestUpload]) }}" alt="Attachment" class="h-20 w-20 rounded-lg border border-slate-200 object-cover" />
                            @else
                                <div class="h-20 w-20 rounded-lg border border-slate-200 bg-slate-100 flex items-center justify-center">
                                    <span class="text-xs font-bold text-slate-500">FILE</span>
                                </div>
                            @endif
                        </a>
                        <div>
                            <div class="font-semibold text-slate-900">{{ $latestUpload?->attachmentName() ?? 'Attachment' }}</div>
                            <div class="text-xs text-slate-500">{{ $latestUpload?->created_at->format($globalDateFormat . ' H:i') }}</div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Edit Form / Read Only Details -->
            @if($routePrefix === 'admin' && $canEdit)
                <form method="POST" action="{{ $updateRoute }}" class="card p-6 space-y-6">
                    @csrf
                    @method('PATCH')

                    <!-- Status & Priority -->
                    <div class="grid gap-4 md:grid-cols-3">
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wider text-slate-500 block mb-2">Status</label>
                            <select name="status" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-900">
                                <option value="pending" @selected($task->status === 'pending')>🔵 Pending</option>
                                <option value="in_progress" @selected($task->status === 'in_progress')>⚡ In Progress</option>
                                <option value="blocked" @selected($task->status === 'blocked')>🚫 Blocked</option>
                                <option value="completed" @selected($task->status === 'completed')>✓ Completed</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wider text-slate-500 block mb-2">Priority</label>
                            <select name="priority" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-900">
                                @foreach($priorityOptions as $value => $label)
                                    <option value="{{ $value }}" @selected($task->priority === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wider text-slate-500 block mb-2">Type</label>
                            <select name="task_type" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-900">
                                @foreach($taskTypeOptions as $value => $label)
                                    <option value="{{ $value }}" @selected($task->task_type === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Dates & Time Estimate -->
                    <div class="grid gap-4 md:grid-cols-3">
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wider text-slate-500 block mb-2">Start Date</label>
                            <input value="{{ $task->start_date?->format('Y-m-d') }}" disabled class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500" />
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wider text-slate-500 block mb-2">Due Date</label>
                            <input value="{{ $task->due_date?->format('Y-m-d') }}" disabled class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500" />
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wider text-slate-500 block mb-2">Time Estimate</label>
                            <div class="flex items-center gap-2">
                                <input name="time_estimate_minutes" type="number" min="0" value="{{ old('time_estimate_minutes', $task->time_estimate_minutes) }}" class="flex-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" />
                                <span class="text-xs text-slate-600 font-medium">min</span>
                            </div>
                        </div>
                    </div>

                    <!-- Tags -->
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wider text-slate-500 block mb-2">Tags</label>
                        <input name="tags" value="{{ old('tags', $task->tags ? implode(', ', $task->tags) : '') }}" placeholder="Add tags separated by comma" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" />
                    </div>

                    <!-- Customer Visible -->
                    <div class="flex items-center gap-3 pt-4 border-t border-slate-200">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="customer_visible" value="0" />
                            <input type="checkbox" name="customer_visible" value="1" @checked($task->customer_visible) class="rounded border-slate-300" />
                            <span class="text-sm text-slate-700">Visible to customer</span>
                        </label>
                    </div>

                    <!-- Save Button -->
                    <div class="flex justify-end pt-4 border-t border-slate-200">
                        <button type="submit" class="px-6 py-2 bg-teal-600 text-white font-semibold rounded-lg hover:bg-teal-700 transition">
                            Update Task
                        </button>
                    </div>
                </form>
            @endif

            @if($routePrefix === 'client' && $canEdit)
                <form method="POST" action="{{ $updateRoute }}" class="card p-6 space-y-6" id="task-edit">
                    @csrf
                    @method('PATCH')

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wider text-slate-500 block mb-2">Status</label>
                            <select name="status" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-900">
                                <option value="pending" @selected($task->status === 'pending')>Pending</option>
                                <option value="in_progress" @selected($task->status === 'in_progress')>In Progress</option>
                                <option value="blocked" @selected($task->status === 'blocked')>Blocked</option>
                                <option value="completed" @selected($task->status === 'completed')>Completed</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wider text-slate-500 block mb-2">Description</label>
                            <textarea name="description" rows="3" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900">{{ old('description', $task->description) }}</textarea>
                        </div>
                    </div>

                    <div class="flex justify-end pt-4 border-t border-slate-200">
                        <button type="submit" class="px-6 py-2 bg-teal-600 text-white font-semibold rounded-lg hover:bg-teal-700 transition">
                            Update Task
                        </button>
                    </div>
                </form>
            @endif

            <!-- Subtasks Section -->
            <div class="card p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">Subtasks</h2>
                        @if($task->subtasks->isNotEmpty())
                            <div class="text-xs text-slate-500 mt-1">
                                {{ $task->subtasks->where('is_completed', true)->count() }} of {{ $task->subtasks->count() }} completed
                            </div>
                        @endif
                    </div>
                    @if($canAddSubtask)
                        <button type="button" id="addSubtaskBtn" class="text-sm font-semibold text-teal-600 hover:text-teal-700">+ Add subtask</button>
                    @endif
                </div>

                <!-- Progress Bar -->
                @if($task->subtasks->isNotEmpty())
                    <div class="mb-6">
                        <div class="w-full bg-slate-200 rounded-full h-2">
                            <div class="bg-teal-500 h-2 rounded-full transition-all" style="width: {{ $task->progress }}%"></div>
                        </div>
                    </div>
                @endif

                <!-- Subtasks List -->
                @if($task->subtasks->isNotEmpty())
                    <div class="space-y-2 mb-6">
                        @foreach($task->subtasks as $subtask)
                            @php
                                $canEditSubtask = in_array($subtask->id, $editableSubtaskIds, true);
                                $canInlineEdit = $canEditSubtask && in_array($routePrefix, ['client', 'employee'], true);
                            @endphp
                            <div class="flex items-start gap-3 p-3 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100 transition group">
                                @if($routePrefix !== 'employee' && $canEditSubtask && $routePrefix !== 'client')
                                    <input type="checkbox" data-subtask-id="{{ $subtask->id }}" @checked($subtask->is_completed) class="subtask-checkbox mt-1 rounded cursor-pointer" />
                                @endif
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="subtask-title text-sm {{ $subtask->is_completed ? 'line-through text-slate-400' : 'font-medium text-slate-900' }}" data-subtask-id="{{ $subtask->id }}">
                                            {{ $subtask->title }}
                                        </span>
                                        @php $incompleteLabel = $routePrefix === 'client' ? 'Open' : 'In progress'; @endphp
                                        <span class="rounded-full border px-2 py-0.5 text-[10px] font-semibold {{ $subtask->is_completed ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' }}">{{ $subtask->is_completed ? 'Completed' : $incompleteLabel }}</span>
                                        @if($subtask->due_date)
                                            <span class="text-xs text-slate-500 whitespace-nowrap">
                                                📅 {{ $subtask->due_date->format($globalDateFormat) }}
                                                @if($subtask->due_time)
                                                    at {{ $subtask->due_time }}
                                                @endif
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-slate-400 mt-1 space-x-2">
                                        <span>Created: {{ $subtask->created_at->format($globalDateFormat . ' H:i') }}</span>
                                        @if($subtask->updated_at && $subtask->created_at && $subtask->updated_at->greaterThan($subtask->created_at))
                                            <span>Edited: {{ $subtask->updated_at->format($globalDateFormat . ' H:i') }}</span>
                                        @endif
                                        @if($subtask->is_completed)
                                            <span>• Completed: {{ $subtask->completed_at->format($globalDateFormat . ' H:i') }}</span>
                                        @endif
                                    </div>
                                    @if($canInlineEdit)
                                        <div class="subtask-edit-row mt-2 flex items-center gap-2" data-subtask-id="{{ $subtask->id }}" style="display: none;">
                                            <input type="text" class="subtask-edit-input flex-1 min-w-0 rounded border border-slate-200 bg-white px-2 py-1 text-xs text-slate-900" value="{{ $subtask->title }}" />
                                            <button type="button" class="subtask-save-btn text-xs font-semibold text-teal-600 hover:text-teal-700" data-subtask-id="{{ $subtask->id }}">Save</button>
                                            <button type="button" class="subtask-cancel-btn text-xs font-semibold text-slate-500 hover:text-slate-600" data-subtask-id="{{ $subtask->id }}">Cancel</button>
                                        </div>
                                    @endif
                                </div>
                                @if($canInlineEdit)
                                    <div class="ml-auto shrink-0 flex items-center gap-2">
                                        <button type="button" class="subtask-edit-btn text-xs font-semibold text-teal-600 hover:text-teal-700" data-subtask-id="{{ $subtask->id }}">Edit</button>
                                        @if($routePrefix === 'employee')
                                            <button type="button" class="subtask-status-btn rounded-full border border-amber-200 px-3 py-1 text-[10px] font-semibold text-amber-700 hover:border-amber-300" data-subtask-id="{{ $subtask->id }}" data-status="in_progress">
                                                In progress
                                            </button>
                                            <button type="button" class="subtask-status-btn rounded-full border border-emerald-200 px-3 py-1 text-[10px] font-semibold text-emerald-700 hover:border-emerald-300" data-subtask-id="{{ $subtask->id }}" data-status="completed">
                                                Completed
                                            </button>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                <!-- Add Subtask Form -->
                @if($canAddSubtask)
                    <div id="subtaskForm" style="display: none;" class="p-4 rounded-lg border-2 border-teal-200 bg-teal-50 space-y-3">
                        <div>
                            <textarea id="subtaskTitle" placeholder="What needs to be done?" class="w-full rounded-lg border border-teal-300 bg-white px-3 py-2 text-sm font-medium text-slate-900 placeholder-slate-500"></textarea>
                        </div>
                        @if($routePrefix !== 'client')
                            <div class="grid gap-3 md:grid-cols-2">
                                <div>
                                    <label class="text-xs text-slate-600 font-medium block mb-1">Due Date</label>
                                    <input type="date" id="subtaskDate" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" />
                                </div>
                                <div>
                                    <label class="text-xs text-slate-600 font-medium block mb-1">Due Time</label>
                                    <input type="time" id="subtaskTime" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" />
                                </div>
                            </div>
                        @endif
                        <div class="flex gap-2 justify-end">
                            <button type="button" id="cancelSubtaskBtn" class="px-4 py-2 text-sm font-medium rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-100 transition">
                                Cancel
                            </button>
                            <button type="button" id="saveSubtaskBtn" class="px-4 py-2 text-sm font-medium rounded-lg bg-teal-600 text-white hover:bg-teal-700 transition">
                                Add Subtask
                            </button>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Activity/Attachments Section -->
            @if($uploadActivities->isNotEmpty() || $canPost)
                <div class="card p-6">
                    <h2 class="text-lg font-bold text-slate-900 mb-4">Attachments</h2>
                    @if($canPost)
                        <form method="POST" action="{{ $uploadRoute }}" enctype="multipart/form-data" class="mb-6 space-y-3">
                            @csrf
                            <input type="file" name="attachments[]" accept=".png,.jpg,.jpeg,.webp,.pdf,.docx,.xlsx" multiple class="block w-full text-sm text-slate-600" />
                            <input type="text" name="message" placeholder="Optional note" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" />
                            <p class="text-xs text-slate-500">Max {{ $uploadMaxMb }}MB per file.</p>
                            @error('attachments')
                                <div class="text-xs text-rose-600">{{ $message }}</div>
                            @enderror
                            @error('attachments.*')
                                <div class="text-xs text-rose-600">{{ $message }}</div>
                            @enderror
                            @error('attachment')
                                <div class="text-xs text-rose-600">{{ $message }}</div>
                            @enderror
                            <div class="flex justify-end">
                                <button type="submit" class="rounded-lg border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Upload</button>
                            </div>
                        </form>
                    @endif
                    @php
                        $uploadsByDay = $uploadActivities->groupBy(fn ($upload) => $upload->created_at?->format($globalDateFormat) ?? 'Unknown date');
                    @endphp
                    @if($uploadActivities->isEmpty())
                        <div class="text-xs text-slate-500">No attachments yet.</div>
                    @else
                        <div class="space-y-4">
                        @foreach($uploadsByDay as $day => $uploads)
                            <div>
                                <div class="text-xs uppercase tracking-[0.2em] text-slate-400 mb-2">{{ $day }}</div>
                                <div class="space-y-3">
                                    @foreach($uploads as $upload)
                                        <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 p-3">
                                            <a href="{{ route($attachmentRouteName, [$project, $task, $upload]) }}" target="_blank" rel="noopener" class="flex items-center gap-3 flex-1 min-w-0">
                                                @if($upload->isImageAttachment())
                                                    <img src="{{ route($attachmentRouteName, [$project, $task, $upload]) }}" alt="Attachment" class="h-12 w-12 rounded border border-slate-200 object-cover" loading="lazy" />
                                                @else
                                                    <div class="h-12 w-12 rounded border border-slate-200 bg-slate-200 flex items-center justify-center text-xs font-bold text-slate-600">DOC</div>
                                                @endif
                                                <div class="flex-1 min-w-0">
                                                    <div class="text-sm font-medium text-slate-900 truncate">{{ $upload->attachmentName() }}</div>
                                                    <div class="text-xs text-slate-500">Uploaded by {{ $upload->actorName() }}</div>
                                                    <div class="text-xs text-slate-500">{{ $upload->created_at->format($globalDateFormat . ' H:i') }}</div>
                                                    @if($upload->message)
                                                        <div class="text-xs text-slate-600">{{ $upload->message }}</div>
                                                    @endif
                                                </div>
                                            </a>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </div>        

        <!-- Relationships Card -->
        @if($task->relationship_ids)
            <div class="card p-5">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-600 mb-3">Related Tasks</h3>
                <div class="space-y-2 text-xs">
                    @foreach(explode(',', implode(',', $task->relationship_ids)) as $relId)
                        @php $relId = trim($relId); @endphp
                        <div class="px-2 py-1 rounded bg-slate-100 text-slate-700 font-medium">
                            Task #{{ $relId }}
                        </div>
                    @endforeach
                </div>
            </div>
    @endif
</div>

    <script>
        const csrfToken = document.querySelector('meta[name=\"csrf-token\"]')?.getAttribute('content')
            || document.querySelector('[name=\"_token\"]')?.value
            || '';

        // Initialize subtask form buttons
        const addBtn = document.getElementById('addSubtaskBtn');
        const cancelBtn = document.getElementById('cancelSubtaskBtn');
        const saveBtn = document.getElementById('saveSubtaskBtn');
        const form = document.getElementById('subtaskForm');
        const titleInput = document.getElementById('subtaskTitle');
        const dateInput = document.getElementById('subtaskDate');
        const timeInput = document.getElementById('subtaskTime');

        if (addBtn) {
            addBtn.addEventListener('click', () => {
                form.style.display = 'block';
                titleInput.focus();
            });
        }

        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => {
                form.style.display = 'none';
                titleInput.value = '';
                if (dateInput) {
                    dateInput.value = '';
                }
                if (timeInput) {
                    timeInput.value = '';
                }
            });
        }

        if (saveBtn) {
            saveBtn.addEventListener('click', () => {
                const title = titleInput.value.trim();
                const date = dateInput ? dateInput.value : '';
                const time = timeInput ? timeInput.value : '';

                if (!title) {
                    alert('Please enter a subtask title');
                    return;
                }

                const formData = new FormData();
                formData.append('title', title);
                if (dateInput) {
                    formData.append('due_date', date || '');
                }
                if (timeInput) {
                    formData.append('due_time', time || '');
                }
                formData.append('_token', csrfToken);

                fetch(`{{ route($routePrefix . '.projects.tasks.subtasks.store', [$project, $task]) }}`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: formData
                })
                .then(response => {
                    if (response.ok) {
                        location.reload();
                    } else {
                        return response.text().then(text => {
                            console.error('Response:', text);
                            alert('Error adding subtask: ' + (response.status || 'Unknown error'));
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error adding subtask: ' + error.message);
                });
            });
        }

        @if($routePrefix !== 'employee')
        // Handle subtask completion (non-employee)
        document.querySelectorAll('.subtask-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                const subtaskId = checkbox.getAttribute('data-subtask-id');
                const isCompleted = checkbox.checked;

                const formData = new FormData();
                formData.append('is_completed', isCompleted ? 1 : 0);
                formData.append('_token', csrfToken);
                formData.append('_method', 'PATCH');

                fetch(`{{ route($routePrefix . '.projects.tasks.subtasks.update', [$project, $task, ':id']) }}`.replace(':id', subtaskId), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: formData
                })
                .then(response => {
                    if (response.ok) {
                        location.reload();
                    } else {
                        return response.text().then(text => {
                            console.error('Response:', text);
                            alert('Error updating subtask: ' + (response.status || 'Unknown error'));
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error updating subtask: ' + error.message);
                });
            });
        });
        @endif

        @if(in_array($routePrefix, ['client', 'employee'], true))
        document.querySelectorAll('.subtask-edit-btn').forEach(button => {
            button.addEventListener('click', () => {
                const subtaskId = button.getAttribute('data-subtask-id');
                const editRow = document.querySelector(`.subtask-edit-row[data-subtask-id="${subtaskId}"]`);
                const input = editRow?.querySelector('.subtask-edit-input');
                const titleSpan = document.querySelector(`.subtask-title[data-subtask-id="${subtaskId}"]`);

                if (!editRow || !input || !titleSpan) {
                    return;
                }

                input.value = titleSpan.textContent.trim();
                editRow.style.display = 'flex';
                input.focus();
                input.select();
            });
        });

        document.querySelectorAll('.subtask-cancel-btn').forEach(button => {
            button.addEventListener('click', () => {
                const subtaskId = button.getAttribute('data-subtask-id');
                const editRow = document.querySelector(`.subtask-edit-row[data-subtask-id="${subtaskId}"]`);
                const input = editRow?.querySelector('.subtask-edit-input');
                const titleSpan = document.querySelector(`.subtask-title[data-subtask-id="${subtaskId}"]`);

                if (!editRow || !input || !titleSpan) {
                    return;
                }

                input.value = titleSpan.textContent.trim();
                editRow.style.display = 'none';
            });
        });

        document.querySelectorAll('.subtask-save-btn').forEach(button => {
            button.addEventListener('click', () => {
                const subtaskId = button.getAttribute('data-subtask-id');
                const editRow = document.querySelector(`.subtask-edit-row[data-subtask-id="${subtaskId}"]`);
                const input = editRow?.querySelector('.subtask-edit-input');
                const titleSpan = document.querySelector(`.subtask-title[data-subtask-id="${subtaskId}"]`);

                if (!editRow || !input || !titleSpan) {
                    return;
                }

                const title = input.value.trim();
                if (!title) {
                    alert('Please enter a subtask title');
                    return;
                }

                const formData = new FormData();
                formData.append('title', title);
                formData.append('_token', csrfToken);
                formData.append('_method', 'PATCH');

                fetch(`{{ route($routePrefix . '.projects.tasks.subtasks.update', [$project, $task, ':id']) }}`.replace(':id', subtaskId), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: formData
                })
                .then(response => {
                    if (response.ok) {
                        titleSpan.textContent = title;
                        editRow.style.display = 'none';
                    } else {
                        return response.text().then(text => {
                            console.error('Response:', text);
                            alert('Error updating subtask: ' + (response.status || 'Unknown error'));
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error updating subtask: ' + error.message);
                });
            });
        });
        @endif

        @if($routePrefix === 'employee')
        document.querySelectorAll('.subtask-status-btn').forEach(button => {
            button.addEventListener('click', () => {
                const subtaskId = button.getAttribute('data-subtask-id');
                const status = button.getAttribute('data-status');
                const isCompleted = status === 'completed';

                const formData = new FormData();
                formData.append('is_completed', isCompleted ? 1 : 0);
                formData.append('_token', csrfToken);
                formData.append('_method', 'PATCH');

                fetch(`{{ route($routePrefix . '.projects.tasks.subtasks.update', [$project, $task, ':id']) }}`.replace(':id', subtaskId), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: formData
                })
                .then(response => {
                    if (response.ok) {
                        location.reload();
                    } else {
                        return response.text().then(text => {
                            console.error('Response:', text);
                            alert('Error updating subtask: ' + (response.status || 'Unknown error'));
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error updating subtask: ' + error.message);
                });
            });
        });
        @endif

    </script>

    <style>
        :root {
            --status-pending: #f1f5f9;
            --status-pending-text: #64748b;
            --status-in-progress: #fef3c7;
            --status-in-progress-text: #b45309;
            --status-blocked: #fee2e2;
            --status-blocked-text: #b91c1c;
            --status-completed: #d1fae5;
            --status-completed-text: #065f46;
        }
    </style>
@endsection

