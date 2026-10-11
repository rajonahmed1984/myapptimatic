import React, { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import {
    PWA_INSTALL_PATH,
    detectInAppBrowser,
    detectInstallPlatform,
    isNativeShell,
    usePwaInstall,
} from '../../utils/pwa';

const DISMISS_KEY = 'pwa_install_banner_dismissed_at';
const DISMISS_FOR_MS = 30 * 24 * 60 * 60 * 1000;

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
        // Private mode: the banner simply comes back next visit.
    }
};

// Small "add to home screen" card for phones and tablets. Desktop visitors are
// left alone here; they can still install from the /install page.
export default function InstallAppBanner({ appName = 'Apptimatic' }) {
    const { canPrompt, installed, promptInstall } = usePwaInstall();
    const [dismissed, setDismissed] = useState(wasDismissedRecently);
    const [pathname, setPathname] = useState(() => window.location.pathname);

    useEffect(() => router.on('navigate', () => setPathname(window.location.pathname)), []);

    const platform = detectInstallPlatform();
    const inAppBrowser = detectInAppBrowser();
    const eligible = platform === 'ios' || canPrompt || (platform === 'android' && inAppBrowser);

    if (dismissed || installed || isNativeShell() || platform === 'desktop' || !eligible || pathname === PWA_INSTALL_PATH) {
        return null;
    }

    const dismiss = () => {
        rememberDismissal();
        setDismissed(true);
    };

    const handleInstall = async () => {
        const outcome = await promptInstall();
        if (outcome === 'dismissed') {
            dismiss();
        }
    };

    const showPromptButton = canPrompt && !inAppBrowser;

    return (
        <div className="pwa-banner fixed inset-x-3 z-50 mx-auto max-w-md" role="dialog" aria-label={`Install ${appName}`}>
            <div className="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-3 shadow-xl shadow-slate-900/10">
                <img src="/icons/icon-192x192.png" alt="" className="h-11 w-11 flex-shrink-0 rounded-xl" />
                <div className="min-w-0 flex-1">
                    <div className="truncate text-sm font-semibold text-slate-900">Install {appName}</div>
                    <div className="text-xs leading-snug text-slate-500">
                        {inAppBrowser
                            ? `Open this page in ${platform === 'ios' ? 'Safari' : 'Chrome'} to install the app.`
                            : 'Add it to your home screen and open it like an app.'}
                    </div>
                </div>
                {showPromptButton ? (
                    <button
                        type="button"
                        onClick={handleInstall}
                        className="flex-shrink-0 rounded-full bg-teal-600 px-4 py-2 text-xs font-semibold text-white hover:bg-teal-500"
                    >
                        Install
                    </button>
                ) : (
                    <a
                        href={PWA_INSTALL_PATH}
                        className="flex-shrink-0 rounded-full bg-teal-600 px-4 py-2 text-xs font-semibold text-white hover:bg-teal-500"
                    >
                        How to
                    </a>
                )}
                <button
                    type="button"
                    onClick={dismiss}
                    className="flex-shrink-0 rounded-full p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                    aria-label="Dismiss"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    );
}
