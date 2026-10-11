// Browser-side helpers for installing the web app to the home screen (PWA).
//
// Chrome, Edge and Samsung Internet fire `beforeinstallprompt`, which the inline
// script in app.blade.php stashes on window before React mounts so the event is
// never missed. iOS never fires it: every iOS browser installs through
// Share > Add to Home Screen, so those visitors get written steps instead of a
// button.

import { useEffect, useState } from 'react';

export const PWA_INSTALL_PATH = '/install';

const IN_APP_BROWSERS = [
    ['Facebook', /FBAN|FBAV|FB_IAB|FBIOS/i],
    ['Messenger', /Messenger|FBMSGR/i],
    ['Instagram', /Instagram/i],
    ['LINE', /\bLine\//i],
    ['WeChat', /MicroMessenger/i],
    ['TikTok', /TikTok|musical_ly|BytedanceWebview/i],
    ['Snapchat', /Snapchat/i],
    ['LinkedIn', /LinkedInApp/i],
    ['X', /Twitter/i],
];

const userAgent = () => (typeof navigator === 'undefined' ? '' : navigator.userAgent || '');

export const isNativeShell = () => typeof window !== 'undefined' && Boolean(window.Capacitor?.isNativePlatform?.());

export const isStandalone = () => {
    if (typeof window === 'undefined') {
        return false;
    }

    return Boolean(
        window.navigator?.standalone
        || window.matchMedia?.('(display-mode: standalone)').matches
        || window.matchMedia?.('(display-mode: fullscreen)').matches
        || window.matchMedia?.('(display-mode: minimal-ui)').matches
    );
};

export const detectInstallPlatform = () => {
    const ua = userAgent();

    // iPadOS reports itself as a Mac; the touch screen gives it away.
    const isIpadOs = typeof navigator !== 'undefined' && navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1;
    if (/iPhone|iPad|iPod/i.test(ua) || isIpadOs) {
        return 'ios';
    }

    if (/Android/i.test(ua)) {
        return 'android';
    }

    return 'desktop';
};

export const detectInAppBrowser = () => {
    const ua = userAgent();
    const match = IN_APP_BROWSERS.find(([, pattern]) => pattern.test(ua));

    return match ? match[0] : null;
};

export const isIosSafari = () => {
    const ua = userAgent();

    return detectInstallPlatform() === 'ios' && !/CriOS|FxiOS|EdgiOS|OPiOS|GSA\//i.test(ua) && !detectInAppBrowser();
};

export function usePwaInstall() {
    const [deferredPrompt, setDeferredPrompt] = useState(() => (typeof window === 'undefined' ? null : window.__pwaInstallPrompt || null));
    const [installed, setInstalled] = useState(isStandalone);

    useEffect(() => {
        const onInstallable = () => setDeferredPrompt(window.__pwaInstallPrompt || null);
        const onInstalled = () => {
            setDeferredPrompt(null);
            setInstalled(true);
        };

        // The event may have arrived between the first render and this effect.
        onInstallable();

        window.addEventListener('pwa:installable', onInstallable);
        window.addEventListener('pwa:installed', onInstalled);

        return () => {
            window.removeEventListener('pwa:installable', onInstallable);
            window.removeEventListener('pwa:installed', onInstalled);
        };
    }, []);

    const promptInstall = async () => {
        if (!deferredPrompt) {
            return 'unavailable';
        }

        // A deferred prompt can only be shown once, whatever the user picks.
        window.__pwaInstallPrompt = null;
        setDeferredPrompt(null);

        try {
            await deferredPrompt.prompt();
            const choice = await deferredPrompt.userChoice;
            if (choice?.outcome === 'accepted') {
                setInstalled(true);
            }

            return choice?.outcome || 'dismissed';
        } catch (error) {
            return 'unavailable';
        }
    };

    return {
        canPrompt: Boolean(deferredPrompt),
        installed,
        promptInstall,
    };
}
