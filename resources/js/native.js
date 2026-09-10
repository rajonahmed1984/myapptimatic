// Native shell bridge for the Capacitor Android/iOS apps.
//
// This module is imported lazily from app.jsx and only when the page is actually
// running inside the native WebView, so the browser bundle never pays for the
// Capacitor plugin wrappers. Everything here degrades quietly: a plugin that is
// missing on one platform must never break navigation.

import { Capacitor } from '@capacitor/core';
import { App as CapacitorApp } from '@capacitor/app';
import { Browser } from '@capacitor/browser';
import { Dialog } from '@capacitor/dialog';
import { Network } from '@capacitor/network';
import { SplashScreen } from '@capacitor/splash-screen';
import { StatusBar, Style } from '@capacitor/status-bar';
import { Keyboard } from '@capacitor/keyboard';

// "Home" screens: the login pages and each portal's dashboard. Back on one of
// these asks before closing the app instead of walking further into history
// (which would only lead back to a stale login screen).
const HOME_PATH = /^\/(?:login|(?:admin|client|employee|rep|sales|support)(?:\/(?:login|dashboard))?)?\/?$/;

const PORTAL_DASHBOARDS = {
    admin: '/admin/dashboard',
    client: '/client/dashboard',
    employee: '/employee/dashboard',
    rep: '/sales/dashboard',
    sales: '/sales/dashboard',
    support: '/support/dashboard',
};

// Checkout hands the user to a payment gateway and then redirects back. Those
// hosts have to stay inside the WebView, so keep this list in step with
// server.allowNavigation in capacitor.config.json.
const IN_APP_HOST_SUFFIXES = ['.sslcommerz.com', '.bka.sh', '.paypal.com'];

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

const isHomePath = (pathname) => HOME_PATH.test(pathname);

const portalDashboard = (pathname) => PORTAL_DASHBOARDS[pathname.split('/')[1]] || '/';

const visit = (url) => {
    if (window.__inertiaRouter) {
        window.__inertiaRouter.visit(url, { replace: true });
        return;
    }

    window.location.replace(url);
};

// Native confirm with our own button labels; an older installed shell without
// the Dialog plugin still gets the WebView's native confirm box.
const confirmExit = async () => {
    const message = 'Are you sure you want to close the app?';

    try {
        const { value } = await Dialog.confirm({
            title: 'Exit app',
            message,
            okButtonTitle: 'Exit',
            cancelButtonTitle: 'Cancel',
        });

        return value;
    } catch (error) {
        return window.confirm(message);
    }
};

let exitPromptOpen = false;

const promptExit = async () => {
    if (exitPromptOpen) {
        return;
    }

    exitPromptOpen = true;

    try {
        if (await confirmExit()) {
            await quietly(() => CapacitorApp.exitApp());
        }
    } finally {
        exitPromptOpen = false;
    }
};

// Android hardware back: inner pages step back through history one screen at a
// time; on a home screen (login or dashboard) ask before closing the app.
const bindHardwareBackButton = () => quietly(async () => {
    await CapacitorApp.addListener('backButton', ({ canGoBack }) => {
        const { pathname } = window.location;

        if (isHomePath(pathname)) {
            promptExit();
            return;
        }

        if (canGoBack && window.history.length > 1) {
            window.history.back();
            return;
        }

        visit(portalDashboard(pathname));
    });
});

const readInitialPage = () => {
    try {
        const raw = document.querySelector('script[data-page]')?.textContent
            || document.getElementById('app')?.dataset.page
            || '';

        return raw ? JSON.parse(raw) : null;
    } catch (error) {
        return null;
    }
};

// Stepping back (hardware back or the iOS edge swipe) past the moment of sign-in
// makes Inertia restore the login screen from history even though the session is
// live. Ask the server again instead, which sends a signed-in user to their dashboard.
const bindStaleLoginGuard = () => {
    let signedIn = Boolean(readInitialPage()?.props?.auth?.user);
    let restoringHistory = false;

    window.addEventListener('popstate', () => {
        restoringHistory = true;
    });

    // A real visit (as opposed to a history restore) always hits the server.
    document.addEventListener('inertia:start', () => {
        restoringHistory = false;
    });

    document.addEventListener('inertia:navigate', (event) => {
        const page = event?.detail?.page;
        const restored = restoringHistory;
        restoringHistory = false;

        if (restored && signedIn && String(page?.component || '').startsWith('Auth/')) {
            visit(window.location.href);
            return;
        }

        signedIn = Boolean(page?.props?.auth?.user);
    });
};

const staysInWebView = (parsed) => (
    parsed.origin === window.location.origin
    || IN_APP_HOST_SUFFIXES.some((suffix) => parsed.hostname.endsWith(suffix))
);

const opensInSystemBrowser = (url) => {
    try {
        return !staysInWebView(new URL(url, window.location.origin));
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

        if (!opensInSystemBrowser(anchor.href)) {
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
    bindStaleLoginGuard();
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
