@if(!$selectedMessage)
    <div class="grid h-full min-h-[18rem] place-items-center px-6 py-10 text-center">
        <div>
            <div class="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-2xl bg-slate-100 text-slate-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5 12 13l9-5.5M4.5 6h15A1.5 1.5 0 0 1 21 7.5v9A1.5 1.5 0 0 1 19.5 18h-15A1.5 1.5 0 0 1 3 16.5v-9A1.5 1.5 0 0 1 4.5 6Z" />
                </svg>
            </div>
            <div class="text-sm font-semibold text-slate-700">Select a message</div>
            <div class="mt-1 text-sm text-slate-500">Open any inbox item to view the thread.</div>
        </div>
    </div>
@else
    <div class="h-full space-y-4 px-4 py-4 md:px-6">
        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Thread</div>
            <h3 class="mt-1 text-base font-semibold text-slate-900">
                {{ $selectedMessage['subject'] ?? '(No subject)' }}
            </h3>
            <div class="mt-2 text-xs text-slate-500">
                {{ count($threadMessages ?? []) }} message(s)
            </div>
        </div>

        @foreach(($threadMessages ?? []) as $threadMessage)
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="grid gap-2 text-xs text-slate-500 md:grid-cols-2">
                    <div><span class="font-semibold text-slate-700">From:</span> {{ $threadMessage['sender_name'] ?? '' }} &lt;{{ $threadMessage['sender_email'] ?? '' }}&gt;</div>
                    <div><span class="font-semibold text-slate-700">To:</span> {{ $threadMessage['to'] ?? '' }}</div>
                    <div><span class="font-semibold text-slate-700">Date:</span> {{ ($threadMessage['received_at'] ?? null)?->format('M d, Y H:i') ?? '' }}</div>
                    <div><span class="font-semibold text-slate-700">Subject:</span> {{ $threadMessage['subject'] ?? '(No subject)' }}</div>
                </div>

                <div class="mt-4 rounded-xl bg-slate-50 p-4 text-sm leading-6 text-slate-700">
                    {!! nl2br(e((string) ($threadMessage['body'] ?? ''))) !!}
                </div>
            </article>
        @endforeach
    </div>
@endif
