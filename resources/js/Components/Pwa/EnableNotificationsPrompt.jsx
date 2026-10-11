import React, { useState } from 'react';
import { detectInstallPlatform, isStandalone } from '../../utils/pwa';
import { canUseWebPush, enableWebPush } from '../../utils/webPush';

const DISMISS_KEY = 'web_push_prompt_dismissed_at';
const DISMISS_FOR_MS = 14 * 24 * 60 * 60 * 1000;

const wasDismissedRecently = () => {
    try {
        const dismissedAt = Number(localStorage.getItem(DISMISS_KEY) || 0);
        return dismissedAt > 0 && Date.now() - dismissedAt < DISMISS_FOR_MS;
    } catch (error) {
        return false;
    }
};

const rememberDismissal = () => {
    try {
        localStorage.setItem(DISMISS_KEY, String(Date.now()));
    } catch (error) {
        // Private mode: the prompt simply comes back next visit.
    }
};

// Asks a signed-in user to allow notifications. Phones only see it inside the
// installed app (the install banner covers the browser tab, and iOS cannot
// push to a plain tab); computers see it in the browser.
export default function EnableNotificationsPrompt({ publicKey }) {
    const [hidden, setHidden] = useState(wasDismissedRecently);
    const [busy, setBusy] = useState(false);

    const onPhoneBrowser = detectInstallPlatform() !== 'desktop' && !isStandalone();

    if (hidden || !publicKey || onPhoneBrowser || !canUseWebPush() || Notification.permission !== 'default') {
        return null;
    }

    const dismiss = () => {
        rememberDismissal();
        setHidden(true);
    };

    const allow = async () => {
        setBusy(true);
        const outcome = await enableWebPush(publicKey);
        setBusy(false);

        if (outcome === 'default') {
            // Closed the browser dialog without choosing; ask again later.
            dismiss();
            return;
        }

        setHidden(true);
    };

    return (
        <div className="pwa-banner fixed inset-x-3 z-50 mx-auto max-w-md md:left-auto md:right-5 md:mx-0" role="dialog" aria-label="Turn on notifications">
            <div className="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-3 shadow-xl shadow-slate-900/10">
                <div className="grid h-11 w-11 flex-shrink-0 place-items-center rounded-xl bg-teal-50 text-teal-600">
                    <svg className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                </div>
                <div className="min-w-0 flex-1">
                    <div className="truncate text-sm font-semibold text-slate-900">Turn on notifications</div>
                    <div className="text-xs leading-snug text-slate-500">Get invoices, payments and support replies right away.</div>
                </div>
                <button
                    type="button"
                    onClick={allow}
                    disabled={busy}
                    className="flex-shrink-0 rounded-full bg-teal-600 px-4 py-2 text-xs font-semibold text-white hover:bg-teal-500 disabled:opacity-60"
                >
                    {busy ? '...' : 'Allow'}
                </button>
                <button
                    type="button"
                    onClick={dismiss}
                    className="flex-shrink-0 rounded-full p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                    aria-label="Not now"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    );
}
