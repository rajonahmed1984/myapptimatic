<div class="h-full p-4 md:p-5">
    <div class="mb-4">
        @include('admin.apptimatic-email.partials.compose-button')
    </div>

    @php
        $isInboxRoute = request()->routeIs('admin.apptimatic-email.inbox') || request()->routeIs('admin.apptimatic-email.show');
    @endphp

    <nav class="space-y-1.5 text-sm">
        <a
            href="{{ route('admin.apptimatic-email.inbox') }}"
            class="{{ $isInboxRoute ? 'bg-teal-50 text-teal-700 border-teal-200' : 'border-transparent text-slate-700 hover:bg-slate-100' }} inline-flex w-full items-center gap-2 rounded-xl border px-3 py-2.5 font-semibold transition"
        >
            <span>Inbox</span>
            <span class="ml-auto rounded-full bg-teal-100 px-2 py-0.5 text-xs font-semibold text-teal-700">{{ (int) ($unreadCount ?? 0) }}</span>
        </a>

        <span
            aria-disabled="true"
            title="Coming soon"
            class="inline-flex w-full cursor-not-allowed items-center rounded-xl border border-transparent px-3 py-2.5 text-slate-400"
        >
            Sent
            <span class="ml-auto text-[11px] uppercase tracking-[0.2em] text-slate-300">Soon</span>
        </span>
        <span
            aria-disabled="true"
            title="Coming soon"
            class="inline-flex w-full cursor-not-allowed items-center rounded-xl border border-transparent px-3 py-2.5 text-slate-400"
        >
            Drafts
            <span class="ml-auto text-[11px] uppercase tracking-[0.2em] text-slate-300">Soon</span>
        </span>
        <span
            aria-disabled="true"
            title="Coming soon"
            class="inline-flex w-full cursor-not-allowed items-center rounded-xl border border-transparent px-3 py-2.5 text-slate-400"
        >
            Trash
            <span class="ml-auto text-[11px] uppercase tracking-[0.2em] text-slate-300">Soon</span>
        </span>
    </nav>
</div>
