/**
 * Makes browsers drop the previous build after a deploy.
 *
 * The server stamps the build version into <meta name="app-version"> and serves
 * the live one from /app-version. A tab compares the two:
 *
 * - on boot, if this browser last ran another version, every service-worker
 *   cache from that build is deleted;
 * - when a tab wakes up (refocus, bfcache restore) or on a slow poll, a changed
 *   server version clears those caches and reloads the tab onto the new build —
 *   but never under someone who has typed into the page, so no input is lost
 *   (their next Inertia visit reloads anyway, as Inertia sees the version change);
 * - if a lazily loaded chunk from the old build is gone, the tab reloads once.
 */

const META_SELECTOR = 'meta[name="app-version"]';
const VERSION_ENDPOINT = '/app-version';
const STORAGE_KEY = 'app_version';
const RELOAD_GUARD_KEY = 'app_version_reloaded_for';
const CACHE_PREFIX = 'apptimatic-pwa-';
const CHECK_INTERVAL_MS = 5 * 60 * 1000;

let lastCheckAt = 0;
let pendingVersion = null;
let userHasTyped = false;

const pageVersion = () => (document.querySelector(META_SELECTOR)?.getAttribute('content') || '').trim();

const storageGet = (storage, key) => {
    try {
        return storage.getItem(key);
    } catch (error) {
        return null;
    }
};

const storageSet = (storage, key, value) => {
    try {
        storage.setItem(key, value);
    } catch (error) {
        // Private mode or blocked storage: the server poll still catches deploys.
    }
};

/** Deletes every Cache Storage entry that does not belong to `keepVersion`. */
const clearCachesExcept = async (keepVersion) => {
    if (typeof caches === 'undefined') {
        return;
    }

    try {
        const keep = `${CACHE_PREFIX}${keepVersion}`;
        const keys = await caches.keys();
        await Promise.all(keys.filter((key) => key !== keep).map((key) => caches.delete(key)));
    } catch (error) {
        // Cache Storage can be unavailable (insecure origin, storage blocked).
    }

    navigator.serviceWorker?.controller?.postMessage({ type: 'CLEAR_CACHES' });
    navigator.serviceWorker?.getRegistration?.()
        .then((registration) => registration?.update())
        .catch(() => {});
};

/** Reloads at most once per target version so a bad deploy can't loop the tab. */
const reloadOnce = (target) => {
    if (storageGet(window.sessionStorage, RELOAD_GUARD_KEY) === target) {
        return false;
    }

    storageSet(window.sessionStorage, RELOAD_GUARD_KEY, target);
    window.location.reload();

    return true;
};

const fetchServerVersion = async () => {
    lastCheckAt = Date.now();

    try {
        const response = await fetch(VERSION_ENDPOINT, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            cache: 'no-store',
        });

        if (!response.ok) {
            return null;
        }

        const data = await response.json();

        return typeof data?.version === 'string' && data.version !== '' ? data.version : null;
    } catch (error) {
        return null;
    }
};

const checkForNewBuild = async ({ reloadIfChanged }) => {
    const current = pageVersion();
    const server = pendingVersion || await fetchServerVersion();

    if (!current || !server || server === current) {
        return;
    }

    if (pendingVersion !== server) {
        pendingVersion = server;
        await clearCachesExcept(server);
    }

    if (reloadIfChanged && !userHasTyped) {
        reloadOnce(server);
    }
};

const onWake = () => {
    if (document.visibilityState !== 'visible') {
        return;
    }

    // A tab that was away is the one most likely to still run an old build, and
    // its user has not touched it yet, so this is the safe moment to reload.
    if (pendingVersion || Date.now() - lastCheckAt >= CHECK_INTERVAL_MS) {
        checkForNewBuild({ reloadIfChanged: true });
    }
};

export const installAppVersionWatcher = () => {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return;
    }

    const current = pageVersion();
    if (!current) {
        // Dev server (no build manifest): nothing to compare against.
        return;
    }

    const previous = storageGet(window.localStorage, STORAGE_KEY);
    if (previous !== current) {
        if (previous) {
            clearCachesExcept(current);
        }
        storageSet(window.localStorage, STORAGE_KEY, current);
    }

    lastCheckAt = Date.now();

    document.addEventListener('input', () => {
        userHasTyped = true;
    }, { capture: true, passive: true });

    document.addEventListener('visibilitychange', onWake);
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            onWake();
        }
    });

    window.setInterval(() => {
        if (document.visibilityState === 'visible') {
            checkForNewBuild({ reloadIfChanged: false });
        }
    }, CHECK_INTERVAL_MS);

    // Vite fires this when a lazily imported chunk fails to load — after a deploy
    // that is a file from the old build that no longer exists on the server.
    window.addEventListener('vite:preloadError', (event) => {
        if (reloadOnce(`preload:${current}`)) {
            event.preventDefault();
        }
    });
};
