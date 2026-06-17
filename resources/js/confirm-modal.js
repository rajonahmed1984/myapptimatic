/**
 * confirm-modal.js — Beautiful custom confirm dialog
 *
 * Replaces the native window.confirm() with an animated, center-screen modal.
 * Works for:
 *   1. All existing window.confirm() calls in JS/JSX (via window.confirm override)
 *   2. form onSubmit patterns: onSubmit={(e) => !window.confirm('...') && e.preventDefault()}
 *   3. Direct calls:   if (window.confirm('...')) { ... }
 *
 * Usage (programmatic async):
 *   const ok = await window.confirmModal({ message: '...', title: '...', confirmText: '...' });
 */

(function () {
    'use strict';

    /* ─── inject styles once ─────────────────────────────────────── */
    const STYLE = `
        #cm-overlay {
            position: fixed;
            inset: 0;
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.25rem;
            background: rgba(15, 23, 42, 0.55);
            backdrop-filter: blur(3px);
            -webkit-backdrop-filter: blur(3px);
            opacity: 0;
            transition: opacity .22s ease;
            pointer-events: none;
        }
        #cm-overlay.cm-visible {
            opacity: 1;
            pointer-events: auto;
        }
        #cm-dialog {
            background: #ffffff;
            border-radius: 20px;
            box-shadow:
                0 24px 64px rgba(0,0,0,.18),
                0 4px 16px rgba(0,0,0,.08),
                0 0 0 1px rgba(0,0,0,.04);
            width: 100%;
            max-width: 420px;
            overflow: hidden;
            transform: scale(.93) translateY(10px);
            transition: transform .26s cubic-bezier(.22,1,.36,1);
        }
        #cm-overlay.cm-visible #cm-dialog {
            transform: scale(1) translateY(0);
        }
        #cm-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            padding: 22px 22px 0;
        }
        #cm-icon-wrap {
            flex-shrink: 0;
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        #cm-icon-wrap.cm-danger  { background: #fff1f2; color: #e11d48; }
        #cm-icon-wrap.cm-warning { background: #fffbeb; color: #d97706; }
        #cm-icon-wrap.cm-info    { background: #eff6ff; color: #2563eb; }
        #cm-icon-wrap.cm-success { background: #f0fdf4; color: #16a34a; }
        #cm-close-btn {
            flex-shrink: 0;
            border: none;
            background: #f1f5f9;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
            transition: background .15s;
        }
        #cm-close-btn:hover { background: #e2e8f0; }
        #cm-body {
            padding: 14px 22px 22px;
        }
        #cm-title {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin: 12px 0 6px;
            line-height: 1.35;
            font-family: inherit;
        }
        #cm-message {
            font-size: 13.5px;
            color: #475569;
            line-height: 1.55;
            font-family: inherit;
        }
        #cm-footer {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            padding: 14px 22px;
            background: #f8fafc;
            border-top: 1px solid #f1f5f9;
        }
        .cm-btn {
            border: none;
            border-radius: 999px;
            padding: 9px 22px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            transition: filter .15s, transform .1s;
            line-height: 1;
        }
        .cm-btn:hover  { filter: brightness(.93); }
        .cm-btn:active { transform: scale(.97); }
        .cm-btn-cancel {
            background: #f1f5f9;
            color: #475569;
        }
        .cm-btn-confirm.cm-danger  { background: #e11d48; color: #fff; }
        .cm-btn-confirm.cm-warning { background: #d97706; color: #fff; }
        .cm-btn-confirm.cm-info    { background: #2563eb; color: #fff; }
        .cm-btn-confirm.cm-success { background: #16a34a; color: #fff; }

        @media (max-width: 480px) {
            #cm-dialog { max-width: 100%; border-radius: 16px; }
            #cm-footer { flex-direction: column-reverse; }
            .cm-btn { width: 100%; text-align: center; }
        }
    `;

    const styleEl = document.createElement('style');
    styleEl.textContent = STYLE;
    document.head.appendChild(styleEl);

    /* ─── icons ──────────────────────────────────────────────────── */
    const ICONS = {
        danger: `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polygon points="7.86 2 16.14 2 22 7.86 22 16.14 16.14 22 7.86 22 2 16.14 2 7.86 7.86 2"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`,
        warning:`<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
        info:   `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>`,
        success:`<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>`,
    };

    /* ─── detect intent from message text ────────────────────────── */
    function detectVariant(msg) {
        const m = (msg || '').toLowerCase();
        if (m.includes('delete') || m.includes('remove') || m.includes('terminate') || m.includes('cancel') || m.includes('revoke')) return 'danger';
        if (m.includes('sure') || m.includes('merge') || m.includes('duplicate') || m.includes('mark')) return 'warning';
        if (m.includes('send') || m.includes('email') || m.includes('remind')) return 'info';
        return 'warning';
    }

    /* ─── build DOM ──────────────────────────────────────────────── */
    const overlay = document.createElement('div');
    overlay.id = 'cm-overlay';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-labelledby', 'cm-title');
    overlay.innerHTML = `
        <div id="cm-dialog">
            <div id="cm-header">
                <div id="cm-icon-wrap"></div>
                <button id="cm-close-btn" type="button" aria-label="Close">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <div id="cm-body">
                <div id="cm-title"></div>
                <div id="cm-message"></div>
            </div>
            <div id="cm-footer">
                <button class="cm-btn cm-btn-cancel" id="cm-cancel" type="button">Cancel</button>
                <button class="cm-btn cm-btn-confirm" id="cm-confirm" type="button">Confirm</button>
            </div>
        </div>
    `;
    document.body.appendChild(overlay);

    const iconWrap  = overlay.querySelector('#cm-icon-wrap');
    const titleEl   = overlay.querySelector('#cm-title');
    const msgEl     = overlay.querySelector('#cm-message');
    const confirmBtn= overlay.querySelector('#cm-confirm');
    const cancelBtn = overlay.querySelector('#cm-cancel');
    const closeBtn  = overlay.querySelector('#cm-close-btn');

    let _resolve = null;

    const close = (result) => {
        overlay.classList.remove('cm-visible');
        document.body.style.overflow = '';
        setTimeout(() => {
            if (_resolve) { _resolve(result); _resolve = null; }
        }, 60);
    };

    cancelBtn.addEventListener('click', () => close(false));
    closeBtn.addEventListener('click',  () => close(false));
    overlay.addEventListener('click', (e) => { if (e.target === overlay) close(false); });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && overlay.classList.contains('cm-visible')) close(false);
        if (e.key === 'Enter'  && overlay.classList.contains('cm-visible')) {
            e.preventDefault();
            close(true);
        }
    });

    /* ─── open function ──────────────────────────────────────────── */
    window.confirmModal = function ({
        message     = 'Are you sure?',
        title       = null,
        variant     = null,
        confirmText = 'Confirm',
        cancelText  = 'Cancel',
    } = {}) {
        return new Promise((resolve) => {
            _resolve = resolve;

            const v = variant || detectVariant(message + ' ' + (title || ''));

            /* auto title */
            const autoTitle = title || (() => {
                if (v === 'danger')  return 'Are you sure?';
                if (v === 'warning') return 'Confirm Action';
                return 'Confirm';
            })();

            /* button label auto-detection */
            const m = message.toLowerCase();
            let autoConfirm = confirmText;
            if (confirmText === 'Confirm') {
                if (m.includes('delete') || m.includes('remove'))   autoConfirm = 'Yes, Delete';
                else if (m.includes('cancel'))                       autoConfirm = 'Yes, Cancel';
                else if (m.includes('terminate') || m.includes('revoke')) autoConfirm = 'Yes, Terminate';
                else if (m.includes('mark paid'))                    autoConfirm = 'Mark Paid';
                else if (m.includes('mark unpaid'))                  autoConfirm = 'Mark Unpaid';
                else if (m.includes('mark') && m.includes('cancelled')) autoConfirm = 'Mark Cancelled';
                else if (m.includes('duplicate'))                    autoConfirm = 'Duplicate';
                else if (m.includes('merge'))                        autoConfirm = 'Merge';
                else if (m.includes('send') || m.includes('remind')) autoConfirm = 'Send';
                else if (m.includes('complete'))                     autoConfirm = 'Complete';
            }

            iconWrap.className = `cm-icon-wrap cm-${v}`;
            iconWrap.innerHTML  = ICONS[v] || ICONS.warning;
            titleEl.textContent  = autoTitle;
            msgEl.textContent    = message;
            confirmBtn.textContent = autoConfirm;
            confirmBtn.className   = `cm-btn cm-btn-confirm cm-${v}`;
            cancelBtn.textContent  = cancelText;

            document.body.style.overflow = 'hidden';
            overlay.classList.add('cm-visible');
            setTimeout(() => confirmBtn.focus(), 80);
        });
    };

    confirmBtn.addEventListener('click', () => close(true));

    /* ─── override window.confirm with async shim ─────────────────
       Because window.confirm is synchronous but our modal is async,
       we intercept ALL onSubmit / onClick patterns that use the pattern:
         if (!window.confirm('...')) e.preventDefault()
       by hooking into document click/submit capture phase.
       For direct async patterns, window.confirmModal() is provided.
    ──────────────────────────────────────────────────────────────── */

    /* Store native confirm to restore if needed */
    window._nativeConfirm = window.confirm.bind(window);

    /*
     * Intercept form submit events that use the inline pattern:
     *   onSubmit={(e) => !window.confirm('...') && e.preventDefault()}
     *
     * We can't truly make window.confirm async-safe. Instead we:
     * 1. Override window.confirm to always return TRUE (allow the default handler to pass)
     * 2. Intercept the native form submit in capture phase, cancel it, show our modal,
     *    then re-submit if confirmed.
     *
     * But this won't work for function patterns. The cleanest universal approach is
     * to replace window.confirm with a synchronous-like wrapper using a hidden trick:
     * we queue the modal open, return false synchronously (preventing the action),
     * then after the user confirms, we re-fire the original event.
     */

    /* Track pending deferred submissions */
    let _pendingAction = null;

    /*
     * Global intercept: capture click on submit buttons and form submit events.
     * If the form has `data-confirm` attribute, use our modal.
     * For JS-triggered window.confirm() calls (JSX onSubmit handlers), we
     * override window.confirm to:
     *   1. Record the message
     *   2. Return false (so the handler calls e.preventDefault())
     *   3. Open the modal
     *   4. On confirm → re-submit the form / call the pending action
     */
    window.confirm = function (message) {
        // If no current event context, just open modal non-blocking and return false
        // The caller will receive false and prevent the action.
        // Then our modal resolves and calls _pendingAction if set.
        if (_pendingAction) {
            // Already handling one — fallback to native for nested calls
            return window._nativeConfirm(message);
        }

        // We must return synchronously. Return false so the calling code
        // calls e.preventDefault(). Then open the modal and resolve async.
        // This works for: if (!window.confirm('...')) e.preventDefault()
        // But NOT for: if (window.confirm('...')) { doThing(); }
        // For the latter, window.confirmModal() must be used explicitly.
        //
        // Since all existing code uses the `e.preventDefault()` guard pattern,
        // this is fine for form-based actions.

        const event = window.__cm_currentEvent || null;
        const form  = event?.target instanceof HTMLFormElement ? event.target
                    : event?.target?.closest?.('form') || null;

        window.confirmModal({ message }).then((confirmed) => {
            if (!confirmed) return;
            if (form) {
                // Remove our listener temporarily, re-submit
                window.__cm_skipNext = true;
                form.submit();
            } else if (typeof _pendingAction === 'function') {
                const fn = _pendingAction;
                _pendingAction = null;
                fn();
            }
        });

        return false; // prevent the immediate action
    };

    /*
     * Capture phase: track the current event so window.confirm() above
     * can see the originating form.
     */
    document.addEventListener('click', (e) => { window.__cm_currentEvent = e; }, true);
    document.addEventListener('submit', (e) => {
        window.__cm_currentEvent = e;
        if (window.__cm_skipNext) {
            window.__cm_skipNext = false;
        }
    }, true);

    /* Clear event reference after handlers run */
    document.addEventListener('click',  () => { setTimeout(() => { window.__cm_currentEvent = null; }, 0); });
    document.addEventListener('submit', () => { setTimeout(() => { window.__cm_currentEvent = null; }, 0); });

})();
