// Browser push subscription (Web Push) for the installed web app and desktop
// browsers. The Capacitor app uses Firebase instead (see native.js), so all of
// this stays off there.

import { detectInstallPlatform, isNativeShell, isStandalone } from './pwa';

const SUBSCRIPTIONS_URL = '/push/web-subscriptions';
const SERVICE_WORKER_TIMEOUT_MS = 10000;

export const isWebPushSupported = () => (
    typeof window !== 'undefined'
    && 'serviceWorker' in navigator
    && 'PushManager' in window
    && 'Notification' in window
    && !isNativeShell()
);

// iOS only delivers web push to an app added to the Home Screen.
export const canUseWebPush = () => isWebPushSupported() && (detectInstallPlatform() !== 'ios' || isStandalone());

const readCookie = (name) => {
    const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));
    return match ? decodeURIComponent(match[1]) : '';
};

// The XSRF cookie always matches the live session; the meta tag can lag behind
// a sign-in. Laravel prefers X-CSRF-TOKEN when both are sent, so send just one.
const csrfHeaders = () => {
    const xsrf = readCookie('XSRF-TOKEN');
    if (xsrf) {
        return { 'X-XSRF-TOKEN': xsrf };
    }

    return { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' };
};

const keyToBytes = (base64Url) => {
    const base64 = `${base64Url}${'='.repeat((4 - (base64Url.length % 4)) % 4)}`.replace(/-/g, '+').replace(/_/g, '/');
    return Uint8Array.from(window.atob(base64), (char) => char.charCodeAt(0));
};

const subscribedWithKey = (subscription, publicKey) => {
    const current = subscription?.options?.applicationServerKey;
    if (!current) {
        return true;
    }

    const expected = keyToBytes(publicKey);
    const actual = new Uint8Array(current);

    return actual.length === expected.length && actual.every((byte, index) => byte === expected[index]);
};

const serviceWorkerReady = () => Promise.race([
    navigator.serviceWorker.ready,
    new Promise((resolve, reject) => {
        setTimeout(() => reject(new Error('Service worker is not ready.')), SERVICE_WORKER_TIMEOUT_MS);
    }),
]);

const saveSubscription = async (publicKey) => {
    const registration = await serviceWorkerReady();
    let subscription = await registration.pushManager.getSubscription();

    // New server keys (push:vapid-keys --force) invalidate the old subscription.
    if (subscription && !subscribedWithKey(subscription, publicKey)) {
        await subscription.unsubscribe().catch(() => {});
        subscription = null;
    }

    if (!subscription) {
        subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: keyToBytes(publicKey),
        });
    }

    const { endpoint, keys } = subscription.toJSON();
    const response = await fetch(SUBSCRIPTIONS_URL, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...csrfHeaders(),
        },
        body: JSON.stringify({ endpoint, keys }),
    });

    return response.ok;
};

/**
 * Must run from a tap or click: iOS ignores permission requests made without one.
 *
 * @returns {Promise<'granted'|'denied'|'default'|'failed'>}
 */
export const enableWebPush = async (publicKey) => {
    try {
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            return permission;
        }

        return (await saveSubscription(publicKey)) ? 'granted' : 'failed';
    } catch (error) {
        return 'failed';
    }
};

// Notifications already allowed: make sure the server has this browser filed
// under whoever is signed in now (logout detaches it).
export const syncWebPush = async (publicKey) => {
    if (!publicKey || !canUseWebPush() || Notification.permission !== 'granted') {
        return;
    }

    try {
        await saveSubscription(publicKey);
    } catch (error) {
        // Not worth bothering the user; the next page load tries again.
    }
};
