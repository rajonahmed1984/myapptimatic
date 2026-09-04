<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="app-base-url" content="{{ request()->getBaseUrl() }}">
    <title inertia>{{ config('app.name', 'MyApptimatic') }}</title>
    @if(!empty($portalBranding['favicon_url']))
        <link rel="icon" href="{{ $portalBranding['favicon_url'] }}">
    @endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        try {
            if (localStorage.getItem('sidebar_collapsed') === 'true' && window.innerWidth >= 768) {
                document.documentElement.classList.add('sidebar-collapsed');
                document.addEventListener('DOMContentLoaded', () => {
                    document.body.classList.add('sidebar-collapsed');
                });
            }
        } catch (e) {}
    </script>
    @php
        $hasViteAssets = file_exists(public_path('hot')) || file_exists(public_path('build/manifest.json'));
    @endphp
    @if ($hasViteAssets)
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    @endif
    @inertiaHead
</head>
<body class="bg-dashboard">
    @if ($hasViteAssets)
        @inertia
    @else
        <div style="padding:16px;border:1px solid #fca5a5;background:#fef2f2;color:#991b1b;border-radius:8px;margin:20px;">
            Frontend assets are missing. Run <code>npm run dev</code> or <code>npm run build</code> and reload.
        </div>
    @endif

    {{-- Global Toast Stack Container --}}
    <div id="toast-stack" aria-live="polite" aria-atomic="false"
         style="position:fixed;top:1.25rem;right:1.25rem;z-index:9999;display:flex;flex-direction:column;gap:.625rem;width:22rem;max-width:calc(100vw - 2.5rem);pointer-events:none;">
    </div>

    {{-- Global Delete Confirmation Modal --}}
    <div id="delete-confirm-modal" class="fixed inset-0 z-[60] hidden" aria-hidden="true">
        <div class="absolute inset-0 bg-slate-900/60" data-delete-confirm-backdrop></div>
        <div class="relative mx-auto flex min-h-full max-w-2xl items-center justify-center px-4 py-10">
            <div class="w-full rounded-2xl bg-white shadow-2xl">
                <div class="flex items-start justify-between border-b border-slate-300 px-6 py-5">
                    <div>
                        <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Delete</div>
                        <div data-delete-confirm-title class="mt-2 text-lg font-semibold text-slate-900">Delete this item?</div>
                    </div>
                    <button type="button" data-delete-confirm-close class="rounded-full border border-slate-300 p-2 text-slate-400 hover:border-slate-300 hover:text-slate-600" aria-label="Close">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="px-6 py-5 text-sm text-slate-600">
                    <p data-delete-confirm-description class="leading-relaxed">
                        This action cannot be undone.
                    </p>
                    <div class="mt-5">
                        <label class="text-xs uppercase tracking-[0.2em] text-slate-400">
                            Type "<span data-delete-confirm-phrase class="font-semibold text-slate-700">DELETE</span>" to confirm
                        </label>
                        <input
                            id="delete-confirm-input"
                            type="text"
                            autocomplete="off"
                            class="mt-2 w-full rounded-[10px] border border-slate-300 bg-white px-4 py-2 text-sm text-slate-700 focus:border-rose-300 focus:ring-0"
                            placeholder="Type the confirmation text"
                        />
                        <div data-delete-confirm-hint class="mt-2 text-xs text-slate-400"></div>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 border-t border-slate-100 bg-slate-50 px-6 py-4">
                    <button type="button" data-delete-confirm-cancel class="rounded-full border border-slate-300 px-5 py-2 text-xs font-semibold text-slate-600 hover:border-slate-300">
                        Cancel
                    </button>
                    <button type="button" data-delete-confirm-submit class="rounded-full bg-rose-500 px-6 py-2 text-xs font-semibold text-white opacity-50" disabled>
                        Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const modal = document.getElementById('delete-confirm-modal');
            if (!modal) return;

            const titleEl = modal.querySelector('[data-delete-confirm-title]');
            const descriptionEl = modal.querySelector('[data-delete-confirm-description]');
            const phraseEl = modal.querySelector('[data-delete-confirm-phrase]');
            const hintEl = modal.querySelector('[data-delete-confirm-hint]');
            const inputEl = modal.querySelector('#delete-confirm-input');
            const submitBtn = modal.querySelector('[data-delete-confirm-submit]');
            const cancelBtn = modal.querySelector('[data-delete-confirm-cancel]');
            const closeBtn = modal.querySelector('[data-delete-confirm-close]');
            const backdrop = modal.querySelector('[data-delete-confirm-backdrop]');

            let activeForm = null;
            let requiredPhrase = 'DELETE';

            const setButtonState = (enabled) => {
                if (!submitBtn) return;
                submitBtn.disabled = !enabled;
                submitBtn.classList.toggle('opacity-50', !enabled);
            };

            const openModal = (form) => {
                activeForm = form;
                const name = (form.getAttribute('data-confirm-name') || '').trim();
                requiredPhrase = name !== '' ? name : 'DELETE';
                const title = form.getAttribute('data-confirm-title') || (name ? `Delete ${name}?` : 'Delete this item?');
                const description = form.getAttribute('data-confirm-description') || 'This action cannot be undone.';
                const actionLabel = form.getAttribute('data-confirm-action') || 'Delete';
                const hint = form.getAttribute('data-confirm-hint') || '';

                if (titleEl) titleEl.textContent = title;
                if (descriptionEl) descriptionEl.textContent = description;
                if (phraseEl) phraseEl.textContent = requiredPhrase;
                if (hintEl) hintEl.textContent = hint;
                if (submitBtn) submitBtn.textContent = actionLabel;

                if (inputEl) {
                    inputEl.value = '';
                    inputEl.placeholder = `Type "${requiredPhrase}" to confirm`;
                }

                setButtonState(false);
                modal.classList.remove('hidden');
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('overflow-hidden');
                setTimeout(() => inputEl?.focus(), 0);
            };

            const closeModal = () => {
                modal.classList.add('hidden');
                modal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('overflow-hidden');
                activeForm = null;
                requiredPhrase = 'DELETE';
            };

            const handleDeleteRequest = (event, form) => {
                if (!form || !form.hasAttribute('data-delete-confirm')) return false;
                event.preventDefault();
                event.stopPropagation();
                event.stopImmediatePropagation();
                openModal(form);
                return true;
            };

            document.addEventListener('click', (event) => {
                const trigger = event.target.closest('button[type="submit"], input[type="submit"]');
                if (!trigger) return;
                const form = trigger.form || trigger.closest('form');
                handleDeleteRequest(event, form);
            }, true);

            document.addEventListener('submit', (event) => {
                const form = event.target;
                if (!(form instanceof HTMLFormElement)) return;
                handleDeleteRequest(event, form);
            }, true);

            inputEl?.addEventListener('input', () => {
                const value = (inputEl.value || '').trim();
                setButtonState(value === requiredPhrase);
            });

            inputEl?.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter') return;
                event.preventDefault();
                if ((inputEl.value || '').trim() === requiredPhrase) {
                    submitBtn?.click();
                }
            });

            submitBtn?.addEventListener('click', () => {
                if (!activeForm) return;
                const value = (inputEl?.value || '').trim();
                if (value !== requiredPhrase) return;
                const formToSubmit = activeForm;
                closeModal();
                formToSubmit.submit();
            });

            cancelBtn?.addEventListener('click', closeModal);
            closeBtn?.addEventListener('click', closeModal);
            backdrop?.addEventListener('click', closeModal);

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                    closeModal();
                }
            });
        });
    </script>
</body>
</html>
