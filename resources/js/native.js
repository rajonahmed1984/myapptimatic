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

// Branded exit confirmation. Plain DOM with its own styles so it works on any
// page (Inertia or legacy) without depending on React or Tailwind's class scan.
const EXIT_MODAL_STYLES = `
.mya-exit{position:fixed;inset:0;z-index:2147483000;display:flex;align-items:center;justify-content:center;padding:24px;background:rgba(15,23,42,.45);-webkit-backdrop-filter:blur(6px);backdrop-filter:blur(6px);opacity:0;transition:opacity .18s ease}
.mya-exit.is-open{opacity:1}
.mya-exit__card{width:100%;max-width:340px;background:#fff;border-radius:24px;padding:28px 20px 20px;text-align:center;box-shadow:0 24px 60px rgba(15,23,42,.25);transform:translateY(12px) scale(.96);transition:transform .2s cubic-bezier(.2,.8,.2,1);font-family:inherit}
.mya-exit.is-open .mya-exit__card{transform:none}
.mya-exit__icon{width:56px;height:56px;margin:0 auto 16px;border-radius:999px;display:grid;place-items:center;background:#eeedfa;color:#302B87;box-shadow:0 0 0 6px rgba(48,43,135,.08)}
.mya-exit__title{margin:0;font-size:18px;font-weight:700;color:#0f172a}
.mya-exit__text{margin:8px 0 0;font-size:13px;line-height:1.55;color:#64748b}
.mya-exit__actions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:22px}
.mya-exit__btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-width:0;min-height:46px;padding:6px 10px;border-radius:14px;font-size:14px;font-weight:600;line-height:1.25;white-space:nowrap;cursor:pointer;-webkit-tap-highlight-color:transparent;font-family:inherit}
.mya-exit__btn svg{flex:none}
@media (max-width:350px){.mya-exit{padding:16px}.mya-exit__card{padding:24px 14px 14px}.mya-exit__btn{font-size:13px;padding:6px}}
.mya-exit__btn:active{transform:scale(.97)}
.mya-exit__btn--stay{background:#fff;border:1px solid #e2e8f0;color:#0f172a}
.mya-exit__btn--leave{background:linear-gradient(135deg,#302B87,#4338ca);border:0;color:#fff;box-shadow:0 8px 18px rgba(48,43,135,.3)}
`;

const exitIcon = (size) => `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>`;

let exitModal = null;

const closeExitModal = () => {
    if (!exitModal) {
        return;
    }

    const modal = exitModal;
    exitModal = null;
    modal.classList.remove('is-open');
    window.setTimeout(() => modal.remove(), 180);
};

const openExitModal = () => {
    if (exitModal) {
        return;
    }

    if (!document.getElementById('mya-exit-styles')) {
        const style = document.createElement('style');
        style.id = 'mya-exit-styles';
        style.textContent = EXIT_MODAL_STYLES;
        document.head.appendChild(style);
    }

    const modal = document.createElement('div');
    modal.className = 'mya-exit';
    modal.setAttribute('role', 'alertdialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-labelledby', 'mya-exit-title');
    modal.innerHTML = `
        <div class="mya-exit__card">
            <div class="mya-exit__icon">${exitIcon(24)}</div>
            <h2 class="mya-exit__title" id="mya-exit-title">অ্যাপ বন্ধ করতে চান?</h2>
            <p class="mya-exit__text">Are you sure you want to exit MyApptimatic?<br>আপনি কি নিশ্চিত যে MyApptimatic অ্যাপ থেকে বের হতে চান?</p>
            <div class="mya-exit__actions">
                <button type="button" class="mya-exit__btn mya-exit__btn--stay" data-exit-action="stay">না, থাকুন (Stay)</button>
                <button type="button" class="mya-exit__btn mya-exit__btn--leave" data-exit-action="leave">${exitIcon(16)}বের হন (Exit)</button>
            </div>
        </div>`;

    modal.addEventListener('click', (event) => {
        const action = event.target instanceof Element
            ? event.target.closest('[data-exit-action]')?.getAttribute('data-exit-action')
            : null;

        if (action === 'leave') {
            closeExitModal();
            quietly(() => CapacitorApp.exitApp());
            return;
        }

        // "Stay" or a tap on the dimmed backdrop keeps the user in the app.
        if (action === 'stay' || event.target === modal) {
            closeExitModal();
        }
    });

    document.body.appendChild(modal);
    exitModal = modal;
    window.requestAnimationFrame(() => modal.classList.add('is-open'));
    modal.querySelector('[data-exit-action="stay"]')?.focus();
};

// Android hardware back: inner pages step back through history one screen at a
// time; on a home screen (login or dashboard) ask before closing the app.
const bindHardwareBackButton = () => quietly(async () => {
    await CapacitorApp.addListener('backButton', ({ canGoBack }) => {
        const { pathname } = window.location;

        // Back while the exit prompt is showing means "stay".
        if (exitModal) {
            closeExitModal();
            return;
        }

        if (isHomePath(pathname)) {
            openExitModal();
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
