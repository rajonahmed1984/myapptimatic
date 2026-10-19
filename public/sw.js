// The page registers /sw.js?v=<build version>, so each deploy installs a new
// worker whose activate step deletes every cache left by the previous build.
const BUILD_VERSION = new URL(self.location.href).searchParams.get('v') || 'v2';
const CACHE_NAME = `apptimatic-pwa-${BUILD_VERSION}`;
const OFFLINE_URL = '/offline.html';

const PRECACHE_ASSETS = [
    OFFLINE_URL,
    '/manifest.json',
    '/icons/icon-192x192.png',
    '/icons/icon-512x512.png',
    '/icons/apple-touch-icon.png',
];

// Patterns that must NEVER be cached by the service worker
const NEVER_CACHE_PATTERNS = [
    /\/login/,
    /\/admin\/login/,
    /\/employee\/login/,
    /\/sales\/login/,
    /\/support\/login/,
    /\/register/,
    /\/forgot-password/,
    /\/reset-password/,
    /\/project-login/,
    /\/logout/,
    /\/csrf-token/,
    /\/app-version/,
    /\/auth\//,
    /\/api\//,
    /\/payments\//,
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(PRECACHE_ASSETS);
        })
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.map((key) => {
                    if (key !== CACHE_NAME) {
                        return caches.delete(key);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// The page asks for this when it notices a new deploy before this worker has
// been replaced, so no asset from the old build is served from cache.
self.addEventListener('message', (event) => {
    if (event.data?.type !== 'CLEAR_CACHES') {
        return;
    }

    event.waitUntil(
        caches.keys().then((keys) => Promise.all(keys.map((key) => caches.delete(key))))
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    // 1. Only handle GET requests
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    // 2. Only handle same-origin or trusted font CDNs
    const isSameOrigin = url.origin === self.location.origin;
    const isGoogleFont = url.origin === 'https://fonts.googleapis.com' || url.origin === 'https://fonts.gstatic.com';

    if (!isSameOrigin && !isGoogleFont) {
        return;
    }

    // 3. Check for never-cache patterns (auth, payments, csrf, api)
    if (NEVER_CACHE_PATTERNS.some((pattern) => pattern.test(url.pathname))) {
        // Always go directly to network, no cache
        return;
    }

    // 4. Navigation requests (HTML page loads / document visits)
    // ALWAYS Network-First with offline fallback
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .catch(async () => {
                    const cache = await caches.open(CACHE_NAME);
                    const cachedOffline = await cache.match(OFFLINE_URL);
                    return cachedOffline || new Response('Offline', { status: 503, statusText: 'Offline' });
                })
        );
        return;
    }

    // 5. Static assets (Vite build assets, fonts, icons, images)
    const isStaticAsset =
        url.pathname.startsWith('/build/assets/') ||
        url.pathname.startsWith('/icons/') ||
        url.pathname.endsWith('.png') ||
        url.pathname.endsWith('.jpg') ||
        url.pathname.endsWith('.svg') ||
        url.pathname.endsWith('.woff2') ||
        isGoogleFont;

    if (isStaticAsset) {
        event.respondWith(
            caches.match(request).then((cachedResponse) => {
                if (cachedResponse) {
                    // Update cache in background (Stale-While-Revalidate)
                    fetch(request).then((networkResponse) => {
                        if (networkResponse && networkResponse.status === 200) {
                            caches.open(CACHE_NAME).then((cache) => cache.put(request, networkResponse));
                        }
                    }).catch(() => {});
                    return cachedResponse;
                }

                return fetch(request).then((networkResponse) => {
                    if (networkResponse && networkResponse.status === 200) {
                        const clone = networkResponse.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
                    }
                    return networkResponse;
                });
            })
        );
        return;
    }

    // 6. Default fallback for other GET requests: Network-First
    event.respondWith(
        fetch(request).catch(async () => {
            return caches.match(request);
        })
    );
});

// Web Push from PushNotificationService (WebPushClient). The payload is
// { title, body, url }; url is a portal path opened when the notification is tapped.
self.addEventListener('push', (event) => {
    let payload = {};
    try {
        payload = event.data ? event.data.json() : {};
    } catch (error) {
        payload = { body: event.data ? event.data.text() : '' };
    }

    event.waitUntil(
        self.registration.showNotification(payload.title || 'Apptimatic', {
            body: payload.body || '',
            icon: '/icons/icon-192x192.png',
            badge: '/icons/icon-maskable-192x192.png',
            data: { url: payload.url || '/' },
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const target = new URL(event.notification.data?.url || '/', self.location.origin);
    if (target.origin !== self.location.origin) {
        return;
    }

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(async (windows) => {
            // Reuse an open window of the app instead of stacking new ones.
            const existing = windows.find((client) => new URL(client.url).origin === self.location.origin);
            if (existing) {
                await existing.focus();
                return existing.navigate(target.href).catch(() => {});
            }

            return self.clients.openWindow(target.href);
        })
    );
});
