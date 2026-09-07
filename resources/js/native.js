// Native shell bridge for the Capacitor Android/iOS apps.
//
// This module is imported lazily from app.jsx and only when the page is actually
// running inside the native WebView, so the browser bundle never pays for the
// Capacitor plugin wrappers. Everything here degrades quietly: a plugin that is
// missing on one platform must never break navigation.

import { Capacitor } from '@capacitor/core';
import { App as CapacitorApp } from '@capacitor/app';
import { Browser } from '@capacitor/browser';
import { Network } from '@capacitor/network';
import { SplashScreen } from '@capacitor/splash-screen';
import { StatusBar, Style } from '@capacitor/status-bar';
import { Keyboard } from '@capacitor/keyboard';

const ROOT_PATHS = new Set(['/', '/login', '/admin', '/client', '/employee', '/rep', '/sales', '/support']);

const quietly = async (task) => {
    try {
        await task();
    } catch (error) {
        // A plugin that is unavailable on this platform is not a failure worth
        // surfacing to the user; the web experience keeps working without it.
    }
};

const markPlatform = () => {
    const platform = Capacitor.getPlatform();
    const root = document.documentElement;

    root.classList.add('native-app', `native-${platform}`);
    root.dataset.nativePlatform = platform;
};

const applyStatusBar = () => quietly(async () => {
    if (Capacitor.getPlatform() === 'web') {
        return;
    }

    await StatusBar.setStyle({ style: Style.Light });

    if (Capacitor.getPlatform() === 'android') {
        await StatusBar.setBackgroundColor({ color: '#0d9488' });
        await StatusBar.setOverlaysWebView({ overlay: false });
    }
});

const hideSplashScreen = () => quietly(() => SplashScreen.hide());

// Android hardware back: walk the WebView history first, fall back to the portal
// landing page, and only then send the app to the background. Killing the app on
// a stray back press is the behaviour users complain about most.
const bindHardwareBackButton = () => quietly(async () => {
    await CapacitorApp.addListener('backButton', ({ canGoBack }) => {
        if (canGoBack && window.history.length > 1) {
            window.history.back();
            return;
        }

        if (!ROOT_PATHS.has(window.location.pathname)) {
            window.location.assign('/');
            return;
        }

        quietly(() => CapacitorApp.minimizeApp());
    });
});

const isExternalUrl = (url) => {
    try {
        return new URL(url, window.location.origin).origin !== window.location.origin;
    } catch (error) {
        return false;
    }
};

// Off-site links must not replace the app's own WebView, or the user ends up
// stranded on a third-party page with no way back.
const bindExternalLinks = () => {
    document.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0) {
            return;
        }

        const anchor = event.target instanceof Element ? event.target.closest('a[href]') : null;
        if (!anchor || anchor.hasAttribute('download')) {
            return;
        }

        const href = anchor.getAttribute('href') || '';
        if (href === '' || href.startsWith('#') || /^(mailto:|tel:|sms:)/i.test(href)) {
            return;
        }

        if (!isExternalUrl(anchor.href)) {
            return;
        }

        event.preventDefault();
        quietly(() => Browser.open({ url: anchor.href }));
    });
};

// Reloading on reconnect matters because the bundled offline page is a dead end
// until something navigates back to the server.
const bindNetworkStatus = () => quietly(async () => {
    const applyStatus = (status) => {
        document.documentElement.dataset.networkStatus = status.connected ? 'online' : 'offline';
    };

    applyStatus(await Network.getStatus());

    await Network.addListener('networkStatusChange', (status) => {
        const wasOffline = document.documentElement.dataset.networkStatus === 'offline';
        applyStatus(status);

        if (status.connected && wasOffline && !window.location.protocol.startsWith('http')) {
            window.location.reload();
        }
    });
});

const bindKeyboard = () => quietly(async () => {
    await Keyboard.addListener('keyboardWillShow', () => {
        document.documentElement.classList.add('native-keyboard-open');
    });

    await Keyboard.addListener('keyboardWillHide', () => {
        document.documentElement.classList.remove('native-keyboard-open');
    });
});

let initialised = false;

export function initNativeShell() {
    if (initialised || typeof window === 'undefined' || !Capacitor.isNativePlatform()) {
        return;
    }

    initialised = true;

    markPlatform();
    applyStatusBar();
    bindHardwareBackButton();
    bindExternalLinks();
    bindNetworkStatus();
    bindKeyboard();

    if (document.readyState === 'complete') {
        hideSplashScreen();
    } else {
        window.addEventListener('load', hideSplashScreen, { once: true });
    }
}

export function isNativeApp() {
    return typeof window !== 'undefined' && Boolean(window.Capacitor?.isNativePlatform?.());
}
