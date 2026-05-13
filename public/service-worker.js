/*
 * Zorbl service worker. Bumping CACHE_VERSION on deploy busts the cache.
 * Strategy:
 *  - Pre-cache the offline shell on install.
 *  - Network-first for navigation; fall back to the cached offline page when
 *    the network is unreachable.
 *  - Pass-through for everything else (we let the browser cache handle static
 *    assets via standard Cache-Control headers).
 */
const CACHE_VERSION = 'zorbl-v1';
const OFFLINE_URL = '/offline.html';
const PRECACHE = [OFFLINE_URL, '/site.webmanifest'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_VERSION)
            .then((cache) => cache.addAll(PRECACHE))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== CACHE_VERSION).map((k) => caches.delete(k))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match(OFFLINE_URL).then((r) => r || new Response('Offline', { status: 503 }))),
        );
    }
});
