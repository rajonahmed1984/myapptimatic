@php
    $isOwn = $activity->actor_type === $currentActorType
        && (string) $activity->actor_id === (string) $currentActorId;
    $safeMessage = $activity->message !== null ? e($activity->message) : '';
    $linkedMessage = $safeMessage !== ''
        ? preg_replace('~(https?://[^\\s<]+)~', '<a href="$1" class="text-teal-600 hover:text-teal-500 underline" target="_blank" rel="noopener">$1</a>', $safeMessage)
        : '';
    $linkUrl = $activity->linkUrl();
    $linkHost = $linkUrl ? parse_url($linkUrl, PHP_URL_HOST) : null;
@endphp
<div class="flex {{ $isOwn ? 'justify-end' : 'justify-start' }}">
    <div class="max-w-2xl rounded-2xl border border-slate-200 bg-white/90 p-3 text-sm text-slate-700">
        <div class="flex flex-wrap items-center gap-2 text-[11px] uppercase tracking-[0.18em] text-slate-400">
            <span class="text-xs font-semibold text-slate-700 normal-case">{{ $activity->actorName() }}</span>
            <span class="normal-case text-slate-500">{{ $activity->actorTypeLabel() }}</span>
            <span class="normal-case text-slate-400">{{ $activity->created_at?->format('M d, Y H:i') ?? '' }}</span>
            <span class="rounded-full border border-slate-200 px-2 py-0.5 text-[10px] font-semibold text-slate-500">
                {{ ucfirst($activity->type) }}
            </span>
        </div>
        @if($activity->type === 'link' && $linkUrl)
            <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                <div class="text-xs text-slate-500">Shared link</div>
                <a href="{{ $linkUrl }}" target="_blank" rel="noopener" class="mt-1 block text-sm font-semibold text-teal-600 hover:text-teal-500">
                    {{ $linkHost ?? $linkUrl }}
                </a>
                <div class="mt-1 text-xs text-slate-500 break-all">{{ $linkUrl }}</div>
            </div>
        @else
            @if($linkedMessage !== '')
                <div class="mt-2 text-sm text-slate-700 whitespace-pre-wrap">{!! $linkedMessage !!}</div>
            @endif
        @endif

        @if($activity->attachment_path)
            <div class="mt-2">
                @if($activity->isImageAttachment())
                    <a href="{{ route($attachmentRouteName, [$project, $task, $activity]) }}" target="_blank" rel="noopener">
                        <img src="{{ route($attachmentRouteName, [$project, $task, $activity]) }}" alt="Attachment" class="max-h-64 rounded-xl border border-slate-200">
                    </a>
                @else
                    <a href="{{ route($attachmentRouteName, [$project, $task, $activity]) }}" class="inline-flex items-center gap-2 rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">
                        Download {{ $activity->attachmentName() ?? 'attachment' }}
                    </a>
                @endif
            </div>
        @endif
    </div>
</div>
