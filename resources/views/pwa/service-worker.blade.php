/*
 * Notify Desk service worker (P9.1) — static assets only.
 *
 * - Versioned build files, icons and brand images: cache-first from an allow-list.
 * - Page navigations: network-only; the branded offline page is used only when the network fails.
 * - Everything else (client pages and their secrets, finance, contracts, reports, settings,
 *   sign-in, JSON…): not intercepted and never stored.
 * - Non-GET requests are never intercepted, cached, queued or retried.
 */
'use strict';

const CACHE_PREFIX = @json($cachePrefix);
const CACHE_NAME = @json($cacheName);
const OFFLINE_URL = @json($offlineUrl);
const PRECACHE_URLS = @json($precacheUrls);
const STATIC_PATH_PREFIXES = @json($staticPathPrefixes);

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(PRECACHE_URLS))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys
                    .filter((key) => key.startsWith(CACHE_PREFIX) && key !== CACHE_NAME)
                    .map((key) => caches.delete(key)),
            ))
            .then(() => self.clients.claim()),
    );
});

function isStaticAsset(url) {
    return url.origin === self.location.origin
        && url.search === ''
        && STATIC_PATH_PREFIXES.some((prefix) => url.pathname.startsWith(prefix));
}

async function cacheFirst(request) {
    const cache = await caches.open(CACHE_NAME);
    const cached = await cache.match(request);
    if (cached) {
        return cached;
    }

    const response = await fetch(request);
    if (response.ok && response.type === 'basic') {
        await cache.put(request, response.clone());
    }

    return response;
}

async function networkOnlyNavigation(request) {
    try {
        return await fetch(request);
    } catch (error) {
        return (await caches.match(OFFLINE_URL)) || Response.error();
    }
}

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(networkOnlyNavigation(request));
        return;
    }

    if (isStaticAsset(new URL(request.url))) {
        event.respondWith(cacheFirst(request));
    }
});
