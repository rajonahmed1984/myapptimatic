@forelse($messages as $message)
    @php
        $isOwn = $message->author_type === $currentAuthorType
            && (string) $message->author_id === (string) $currentAuthorId;
        $safeMessage = $message->message !== null ? e($message->message) : '';
        $linkedMessage = $safeMessage !== ''
            ? preg_replace('~(https?://[^\\s<]+)~', '<a href="$1" class="text-teal-600 hover:text-teal-500 underline" target="_blank" rel="noopener">$1</a>', $safeMessage)
            : '';
    @endphp
    <div class="flex {{ $isOwn ? 'justify-end' : 'justify-start' }}">
        <div class="max-w-2xl rounded-2xl border border-slate-200 bg-white/90 p-3 text-sm text-slate-700">
            <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                <span class="font-semibold text-slate-700">{{ $message->authorName() }}</span>
                <span>{{ $message->authorTypeLabel() }}</span>
                <span>{{ $message->created_at?->format('M d, Y H:i') ?? '' }}</span>
            </div>
            @if($linkedMessage !== '')
                <div class="mt-2 text-sm text-slate-700 whitespace-pre-wrap">{!! $linkedMessage !!}</div>
            @endif
            @if($message->attachment_path)
                <div class="mt-2">
                    @if($message->isImageAttachment())
                        <a href="{{ route($attachmentRouteName, [$project, $task, $message]) }}" target="_blank" rel="noopener">
                            <img src="{{ route($attachmentRouteName, [$project, $task, $message]) }}" alt="Attachment" class="max-h-64 rounded-xl border border-slate-200">
                        </a>
                    @else
                        <a href="{{ route($attachmentRouteName, [$project, $task, $message]) }}" class="inline-flex items-center gap-2 rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">
                            Download {{ $message->attachmentName() ?? 'attachment' }}
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </div>
@empty
    <div class="text-sm text-slate-500">No messages yet. Start the conversation.</div>
@endforelse
