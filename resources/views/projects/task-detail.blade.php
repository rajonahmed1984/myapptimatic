@extends($layout)

@section('title', 'Task')
@section('page-title', 'Task')

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
        <a href="{{ $backRoute }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Back to project</a>
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
                            <label class="text-xs text-slate-500">Assignees</label>
                            <select name="assignees[]" multiple class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                                @if($employees->isNotEmpty())
                                    <optgroup label="Employees">
                                        @foreach($employees as $employee)
                                            @php $value = 'employee:'.$employee->id; @endphp
                                            <option value="{{ $value }}" @selected(in_array($value, $assignees, true))>{{ $employee->name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                                @if($salesReps->isNotEmpty())
                                    <optgroup label="Sales Reps">
                                        @foreach($salesReps as $rep)
                                            @php $value = 'sales_rep:'.$rep->id; @endphp
                                            <option value="{{ $value }}" @selected(in_array($value, $assignees, true))>{{ $rep->name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            </select>
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

                        <div>
                            <label class="text-xs text-slate-500">Description</label>
                            <textarea name="description" rows="4" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">{{ old('description', $task->description) }}</textarea>
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
                            <span class="text-xs uppercase tracking-[0.2em] text-slate-400">Description</span>
                            <div class="mt-1 whitespace-pre-wrap text-slate-700">{{ $task->description ?? 'No description.' }}</div>
                        </div>
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
                <div id="task-activity-feed" class="mt-4 max-h-[65vh] space-y-4 overflow-y-auto pr-1">
                    @include('projects.partials.task-activity-feed', [
                        'activities' => $activities,
                        'project' => $project,
                        'task' => $task,
                        'attachmentRouteName' => $attachmentRouteName,
                        'currentActorType' => $currentActorType,
                        'currentActorId' => $currentActorId,
                    ])
                </div>
            </div>

            @if($canPost)
                <div class="card p-6 space-y-4">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Add comment</div>
                    <form method="POST" action="{{ $activityPostRoute }}" class="space-y-3">
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

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const container = document.getElementById('task-activity-feed');
            const pollUrl = @json($pollUrl);

            if (!container || !pollUrl) {
                return;
            }

            const scrollToBottom = () => {
                container.scrollTop = container.scrollHeight;
            };

            const isNearBottom = () => {
                const threshold = 120;
                return (container.scrollHeight - container.scrollTop - container.clientHeight) < threshold;
            };

            scrollToBottom();

            setInterval(() => {
                const keepAtBottom = isNearBottom();
                fetch(pollUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(response => response.text())
                    .then(html => {
                        container.innerHTML = html;
                        if (keepAtBottom) {
                            scrollToBottom();
                        }
                    })
                    .catch(() => {});
            }, 3000);
        });
    </script>
@endsection
