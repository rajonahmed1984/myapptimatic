/**
 * Keeps the page's CSRF token alive.
 *
 * The token is rendered once into <meta name="csrf-token"> when the document
 * first loads, and every form/fetch in the app reads it from there. On a phone
 * a tab is rarely reloaded: it gets frozen, restored from bfcache hours later,
 * and posts that original token — by then the session behind it may have been
 * swept or regenerated, so Laravel answers 419 and the user is thrown at the
 * login screen with "Session expired. Please log in again."
 *
 * So: whenever a tab comes back to life (bfcache restore, tab refocus, Inertia
 * visit) we re-read the token from the server and push the fresh value into the
 * meta tag and every hidden _token input already sitting in the DOM.
 */

const META_SELECTOR = 'meta[name="csrf-token"]';
const TOKEN_INPUT_SELECTOR = 'input[name="_token"]';
const REFRESH_ENDPOINT = '/csrf-token';

// Don't hammer the endpoint: a tab that is switched to repeatedly only needs one
// round trip per stale window.
const REFRESH_INTERVAL_MS = 5 * 60 * 1000;

let lastRefreshAt = Date.now();
let inFlight = null;

const metaTag = () => (typeof document === 'undefined' ? null : document.querySelector(META_SELECTOR));

export const getCsrfToken = () => (metaTag()?.getAttribute('content') || '').trim();

/**
 * Writes a token into the meta tag and into any hidden _token input that was
 * rendered with the old value. Native (non-XHR) form posts read those inputs
 * directly, so leaving them stale would keep the 419s coming.
 */
export const setCsrfToken = (token) => {
    const next = String(token || '').trim();
    if (next === '' || typeof document === 'undefined') {
        return false;
    }

    const current = getCsrfToken();
    if (current === next) {
        return false;
    }

    metaTag()?.setAttribute('content', next);
    document.querySelectorAll(TOKEN_INPUT_SELECTOR).forEach((input) => {
        input.value = next;
    });

    return true;
};

/**
 * Pulls a token bound to whatever session the server has for us right now. If
 * the old session was already gone this also starts a fresh one, which is what
 * makes the retried request succeed instead of bouncing to the login page.
 */
export const refreshCsrfToken = ({ force = false } = {}) => {
    if (typeof window === 'undefined') {
        return Promise.resolve(getCsrfToken());
    }

    if (inFlight) {
        return inFlight;
    }

    if (!force && Date.now() - lastRefreshAt < REFRESH_INTERVAL_MS) {
        return Promise.resolve(getCsrfToken());
    }

    inFlight = fetch(REFRESH_ENDPOINT, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        cache: 'no-store',
    })
        .then((response) => (response.ok ? response.json() : null))
        .then((payload) => {
            lastRefreshAt = Date.now();
            if (payload?.token) {
                setCsrfToken(payload.token);
            }

            return getCsrfToken();
        })
        .catch(() => getCsrfToken())
        .finally(() => {
            inFlight = null;
        });

    return inFlight;
};

let installed = false;

export const installCsrfTokenRefresh = () => {
    if (installed || typeof window === 'undefined' || typeof document === 'undefined') {
        return;
    }
    installed = true;

    // Every Inertia response carries a token for the session that just answered,
    // so navigating around the app keeps the meta tag current for free.
    const syncFromInertiaEvent = (event) => {
        const page = event?.detail?.page || event?.detail?.visit?.page || null;
        const token = page?.props?.csrf_token;
        if (token) {
            lastRefreshAt = Date.now();
            setCsrfToken(token);
        }
    };

    document.addEventListener('inertia:success', syncFromInertiaEvent);
    document.addEventListener('inertia:navigate', syncFromInertiaEvent);

    // Restored from bfcache — the DOM (and its token) is exactly as old as the
    // moment the tab was frozen. This is the mobile case.
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            refreshCsrfToken({ force: true });
        }
    });

    // Tab brought back to the foreground after being parked in the background.
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            refreshCsrfToken();
        }
    });

    window.addEventListener('focus', () => {
        refreshCsrfToken();
    });

    // Last line of defence for plain <form method="POST"> submits: sync the
    // hidden input to the freshest token we hold before the browser leaves.
    document.addEventListener(
        'submit',
        (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            const token = getCsrfToken();
            if (token === '') {
                return;
            }

            form.querySelectorAll(TOKEN_INPUT_SELECTOR).forEach((input) => {
                input.value = token;
            });
        },
        true
    );
};

export default {
    getCsrfToken,
    setCsrfToken,
    refreshCsrfToken,
    installCsrfTokenRefresh,
};
