@extends($layout)

@section('title', 'Task Chat')
@section('page-title', 'Task Chat')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Task Chat</div>
            <div class="text-2xl font-semibold text-slate-900">{{ $task->title }}</div>
            <div class="text-sm text-slate-500">Project: {{ $project->name }}</div>
        </div>
        <a href="{{ $backRoute }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Back to project</a>
    </div>

    <div class="card p-6 space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Task Details</div>
            <div class="mt-2 font-semibold text-slate-900">{{ $task->title }}</div>
            @if($task->description)
                <div class="mt-1 text-xs text-slate-600 whitespace-pre-wrap">{{ $task->description }}</div>
            @endif
            <div class="mt-2 text-xs text-slate-500">
                Start: {{ $task->start_date?->format($globalDateFormat) ?? '--' }} |
                Due: {{ $task->due_date?->format($globalDateFormat) ?? '--' }} |
                Status: {{ ucfirst(str_replace('_', ' ', $task->status)) }}
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Conversation</div>
                    <div class="text-xs text-slate-500">Messages refresh every few seconds.</div>
                </div>
            </div>
            <div id="task-chat-messages" class="mt-4 max-h-[60vh] space-y-4 overflow-y-auto pr-1">
                @include('projects.partials.task-chat-messages', [
                    'messages' => $messages,
                    'project' => $project,
                    'task' => $task,
                    'attachmentRouteName' => $attachmentRouteName,
                    'currentAuthorType' => $currentAuthorType,
                    'currentAuthorId' => $currentAuthorId,
                ])
            </div>
        </div>

        @if($canPost)
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Post a message</div>
                <form method="POST" action="{{ $postRoute }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                    @csrf
                    <div>
                        <label class="text-xs text-slate-500">Message</label>
                        <textarea name="message" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="Share an update...">{{ old('message') }}</textarea>
                        @error('message')
                            <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                        @enderror
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Attachment (optional)</label>
                        <input name="attachment" type="file" accept="image/*,.pdf" class="mt-1 block w-full text-sm text-slate-600" />
                        <p class="mt-1 text-xs text-slate-500">Images or PDF up to 5MB.</p>
                        @error('attachment')
                            <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Send message</button>
                    </div>
                </form>
            </div>
        @endif
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const container = document.getElementById('task-chat-messages');
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
