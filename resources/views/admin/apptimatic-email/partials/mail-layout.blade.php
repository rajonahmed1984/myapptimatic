<div class="card overflow-hidden p-0">
    <div class="border-b border-slate-200 bg-white px-4 py-4 md:px-6">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div class="w-full xl:max-w-2xl">
                <label for="apptimatic-mail-search" class="sr-only">Search mail</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m1.35-5.15a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z" />
                        </svg>
                    </span>
                    <input
                        id="apptimatic-mail-search"
                        type="search"
                        value=""
                        readonly
                        placeholder="Search mail"
                        title="Search coming soon"
                        aria-label="Search coming soon"
                        class="h-11 w-full rounded-full border border-slate-300 bg-slate-50 pl-11 pr-4 text-sm text-slate-600 outline-none"
                    >
                </div>
                <p class="mt-1 text-xs text-slate-500">Search coming soon</p>
            </div>

            <div class="flex items-center justify-end gap-2">
                <button
                    type="button"
                    title="Portal switch coming soon"
                    class="inline-flex items-center rounded-full border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-600"
                >
                    {{ $portalLabel ?? 'Admin portal' }}
                </button>
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-full border border-slate-300 bg-white px-2 py-1.5 text-sm text-slate-700"
                >
                    <span class="h-8 w-8 overflow-hidden rounded-full border border-slate-200 bg-slate-50">
                        <x-avatar :path="$profileAvatarPath ?? null" :name="$profileName ?? 'Admin'" size="h-8 w-8" textSize="text-xs" />
                    </span>
                    <span class="hidden sm:inline">{{ $profileName ?? 'Administrator' }}</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div class="grid min-h-[65vh] grid-cols-1 xl:grid-cols-[16rem_1fr]">
        <aside class="border-b border-slate-200 bg-slate-50 xl:border-b-0 xl:border-r">
            @include('admin.apptimatic-email.partials.mail-sidebar', [
                'unreadCount' => $unreadCount ?? 0,
            ])
        </aside>

        <div class="grid min-h-[65vh] grid-cols-1 2xl:grid-cols-[minmax(20rem,38%)_1fr]">
            <section class="border-b border-slate-200 bg-white 2xl:border-b-0 2xl:border-r">
                @include('admin.apptimatic-email.partials.mail-list', [
                    'messages' => $messages ?? [],
                    'selectedMessage' => $selectedMessage ?? null,
                ])
            </section>

            <section class="bg-slate-50/60">
                @include('admin.apptimatic-email.partials.mail-reader', [
                    'selectedMessage' => $selectedMessage ?? null,
                    'threadMessages' => $threadMessages ?? [],
                ])
            </section>
        </div>
    </div>
</div>
