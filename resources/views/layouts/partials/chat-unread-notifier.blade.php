@php
    $chatUnreadCount = (int) ($unreadCount ?? 0);
    $chatUnreadRoute = (string) ($chatRoute ?? '#');
    $chatUnreadScope = (string) ($scope ?? 'chat');
@endphp

@if($chatUnreadCount > 0)
    <div
        id="chatUnreadNotifier"
        class="fixed bottom-4 right-4 z-[120] w-[min(92vw,22rem)] rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-lg"
        data-unread-count="{{ $chatUnreadCount }}"
        data-chat-route="{{ $chatUnreadRoute }}"
        data-scope="{{ $chatUnreadScope }}"
        role="status"
        aria-live="polite"
    >
        <div class="flex items-start justify-between gap-3">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-700">Unread Chat</div>
                <div class="mt-1 text-sm font-semibold text-amber-900">
                    You have {{ $chatUnreadCount }} unread chat {{ $chatUnreadCount === 1 ? 'message' : 'messages' }}.
                </div>
            </div>
            <button
                type="button"
                id="chatUnreadNotifierClose"
                class="rounded-full border border-amber-300 px-2 py-1 text-xs font-semibold text-amber-700 hover:bg-amber-100"
                aria-label="Close unread chat notification"
            >
                Close
            </button>
        </div>
        <div class="mt-3">
            <a
                href="{{ $chatUnreadRoute }}"
                class="inline-flex items-center rounded-full border border-amber-300 px-3 py-1 text-xs font-semibold text-amber-800 hover:bg-amber-100"
            >
                Open Chat
            </a>
        </div>
    </div>
    <script>
        (function () {
            const root = document.getElementById('chatUnreadNotifier');
            if (!root) {
                return;
            }

            const closeBtn = document.getElementById('chatUnreadNotifierClose');
            const unreadCount = Number(root.dataset.unreadCount || 0);
            const scope = String(root.dataset.scope || 'chat');
            const dismissedKey = `chat-unread-dismissed:${scope}`;
            const lastDismissed = Number(sessionStorage.getItem(dismissedKey) || 0);
            const titleBase = document.title;
            let titleTimer = null;
            let titleFlip = false;

            const stopTitleAlert = () => {
                if (titleTimer) {
                    window.clearInterval(titleTimer);
                    titleTimer = null;
                }
                document.title = titleBase;
            };

            const closeNotifier = () => {
                sessionStorage.setItem(dismissedKey, String(unreadCount));
                root.remove();
                stopTitleAlert();
            };

            if (unreadCount <= 0 || unreadCount <= lastDismissed) {
                root.remove();
                return;
            }

            titleTimer = window.setInterval(() => {
                titleFlip = !titleFlip;
                document.title = titleFlip
                    ? `(${unreadCount}) Unread Chat`
                    : titleBase;
            }, 1000);

            closeBtn?.addEventListener('click', closeNotifier);
            window.addEventListener('beforeunload', stopTitleAlert);
        })();
    </script>
@endif
