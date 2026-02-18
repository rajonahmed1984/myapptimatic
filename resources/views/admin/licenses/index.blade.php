@extends('layouts.admin')

@section('title', 'Licenses')
@section('page-title', 'Licenses')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div class="flex-1">
            <form id="licensesSearchForm" method="GET" action="{{ route('admin.licenses.index') }}" class="flex items-center gap-3" data-live-filter="true">
                <div class="relative w-full max-w-sm">
                    <input
                        type="text"
                        name="search"
                        value="{{ $search ?? request('search') }}"
                        placeholder="Search licenses..."
                        class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm"
                    />
                </div>
            </form>
        </div>
        <a href="{{ route('admin.licenses.create') }}" class="rounded-full bg-teal-500 px-4 py-2 text-sm font-semibold text-white">New License</a>
    </div>

    @include('admin.licenses.partials.table', ['licenses' => $licenses])

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const markCopied = (btn) => {
                    if (!btn) return;
                    btn.classList.add('text-emerald-600');
                    setTimeout(() => btn.classList.remove('text-emerald-600'), 1200);
                };

                const fallbackCopy = (text, btn) => {
                    const textarea = document.createElement('textarea');
                    textarea.value = text;
                    textarea.setAttribute('readonly', '');
                    textarea.style.position = 'absolute';
                    textarea.style.left = '-9999px';
                    document.body.appendChild(textarea);
                    textarea.select();
                    try {
                        document.execCommand('copy');
                        markCopied(btn);
                    } catch (e) {
                        window.notify('Unable to copy license key.', 'error');
                    } finally {
                        document.body.removeChild(textarea);
                    }
                };

                document.addEventListener('click', (event) => {
                    const btn = event.target.closest('.copy-license-btn');
                    if (!btn) return;

                    const row = btn.closest('tr');
                    const textEl = row ? row.querySelector('.license-key-text') : null;
                    const key = (textEl?.textContent || btn.getAttribute('data-license-key') || '').trim();
                    if (!key) return;

                    if (navigator.clipboard && window.isSecureContext) {
                        navigator.clipboard.writeText(key)
                            .then(() => markCopied(btn))
                            .catch(() => fallbackCopy(key, btn));
                    } else {
                        fallbackCopy(key, btn);
                    }
                });
            });
        </script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const statusClasses = [
                    'bg-emerald-100',
                    'text-emerald-700',
                    'bg-amber-100',
                    'text-amber-700',
                    'bg-slate-100',
                    'text-slate-600',
                ];

                const updateBadge = (badge, label, classNames) => {
                    if (!badge) return;
                    badge.classList.remove(...statusClasses);
                    const nextClasses = (classNames || '').split(' ').filter(Boolean);
                    if (nextClasses.length) {
                        badge.classList.add(...nextClasses);
                    }
                    badge.textContent = label || 'Synced';
                };

                const refreshStatus = async (statusUrl, licenseId) => {
                    if (!statusUrl || !licenseId) return;

                    const badge = document.querySelector(`[data-sync-badge="${licenseId}"]`);
                    const time = document.querySelector(`[data-sync-time="${licenseId}"]`);

                    try {
                        const response = await fetch(statusUrl, { headers: { 'Accept': 'application/json' } });
                        if (!response.ok) return;
                        const payload = await response.json();
                        if (!payload.ok) return;

                        updateBadge(badge, payload.data?.sync_label, payload.data?.sync_class);
                        if (time && payload.data?.display_time) {
                            time.textContent = payload.data.display_time;
                        }
                    } catch (e) {
                        // Silent fail keeps current UI state.
                    }
                };

                const bindSyncButtons = () => {
                    const syncButtons = document.querySelectorAll('[data-license-sync]');
                    syncButtons.forEach((btn) => {
                        if (btn.dataset.syncBound === 'true') return;
                        btn.dataset.syncBound = 'true';

                        btn.addEventListener('click', async (event) => {
                            if (!window.fetch) {
                                return;
                            }

                            event.preventDefault();

                            const form = btn.closest('form');
                            if (!form) return;

                            const licenseId = btn.getAttribute('data-license-id');
                            const statusUrl = btn.getAttribute('data-sync-status-url');
                            const badge = document.querySelector(`[data-sync-badge="${licenseId}"]`);
                            const time = document.querySelector(`[data-sync-time="${licenseId}"]`);
                            const token = form.querySelector('input[name="_token"]')?.value;
                            const originalLabel = btn.textContent;

                            btn.disabled = true;
                            btn.textContent = 'Syncing...';
                            updateBadge(badge, 'Queued', 'bg-slate-100 text-slate-600');
                            if (time) {
                                time.textContent = 'Sync queued';
                            }

                            try {
                                await fetch(form.action, {
                                    method: 'POST',
                                    headers: {
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': token || '',
                                    },
                                });

                                setTimeout(() => refreshStatus(statusUrl, licenseId), 1500);
                            } catch (e) {
                                updateBadge(badge, 'Sync failed', 'bg-amber-100 text-amber-700');
                            } finally {
                                btn.disabled = false;
                                btn.textContent = originalLabel;
                            }
                        });
                    });
                };

                bindSyncButtons();
                document.addEventListener('ajax:content:loaded', bindSyncButtons);
            });
        </script>
    @endpush
@endsection
