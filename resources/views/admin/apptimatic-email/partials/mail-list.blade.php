@if(!empty($messages))
    <div class="h-full">
        <div class="border-b border-slate-200 px-4 py-3 text-xs uppercase tracking-[0.2em] text-slate-500">
            Inbox
        </div>
        <div>
            @foreach($messages as $message)
                @include('admin.apptimatic-email.partials.mail-row', [
                    'message' => $message,
                    'selectedMessage' => $selectedMessage ?? null,
                ])
            @endforeach
        </div>
    </div>
@else
    <div class="grid h-full min-h-[18rem] place-items-center px-6 py-10 text-center">
        <div>
            <div class="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-2xl bg-slate-100 text-slate-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5 12 13l9-5.5M4.5 6h15A1.5 1.5 0 0 1 21 7.5v9A1.5 1.5 0 0 1 19.5 18h-15A1.5 1.5 0 0 1 3 16.5v-9A1.5 1.5 0 0 1 4.5 6Z" />
                </svg>
            </div>
            <div class="text-sm font-semibold text-slate-700">No emails yet</div>
            <div class="mt-1 text-sm text-slate-500">Once sync is enabled, inbox messages will appear here.</div>
        </div>
    </div>
@endif
