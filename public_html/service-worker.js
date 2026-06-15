const APP_VERSION = '__APP_VERSION__';
// Cache name tied to the deploy version → every deploy gets a fresh cache,
// and the old one is purged on activate. No more stale JS/CSS after a push.
const CACHE_NAME  = 'familia-' + APP_VERSION;
const OFFLINE_URL = '/offline.html';

// App-shell assets worth having available before the first offline navigation.
const PRECACHE = [
  OFFLINE_URL,
  '/assets/images/icon.svg',
  '/assets/images/icon-192.png',
];

self.addEventListener('install', event => {
  // Precache the offline shell, then activate immediately.
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(PRECACHE))
      .catch(() => {})
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys()
      .then(keys => Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', event => {
  const { request } = event;
  if (request.method !== 'GET') return;
  const url = new URL(request.url);

  // Network-first for everything: fresh content when online, cache fallback offline.
  // Only same-origin successful responses get cached (CDN is left to the HTTP cache).
  event.respondWith(
    fetch(request)
      .then(response => {
        if (response.ok && url.origin === self.location.origin) {
          const clone = response.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(request, clone));
        }
        return response;
      })
      .catch(async () => {
        const cached = await caches.match(request);
        if (cached) return cached;
        // No cached copy → show the branded offline page for page navigations.
        if (request.mode === 'navigate') return caches.match(OFFLINE_URL);
        return Response.error();
      })
  );
});

/* ── Version query ───────────────────────────────── */
self.addEventListener('message', event => {
  if (event.data?.type === 'GET_VERSION') {
    event.ports[0]?.postMessage({ version: APP_VERSION });
  }
  if (event.data?.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});

/* ── Push notifications ──────────────────────────── */
self.addEventListener('push', event => {
  if (!event.data) return;
  let data;
  try { data = event.data.json(); }
  catch { data = { title: 'Familia', body: event.data.text() }; }

  event.waitUntil(
    self.registration.showNotification(data.title || 'Familia', {
      body:    data.body   || '',
      icon:    '/assets/images/icon-192.png',
      badge:   '/assets/images/icon-192.png',
      data:    { url: data.url || '/' },
      vibrate: [100, 50, 100],
      tag:     'familia-event',
      renotify: true,
    })
  );
});

self.addEventListener('notificationclick', event => {
  event.notification.close();
  const url = event.notification.data?.url || '/';
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(list => {
      for (const client of list) {
        if (client.url.includes(self.location.origin) && 'focus' in client) {
          client.navigate(url);
          return client.focus();
        }
      }
      if (clients.openWindow) return clients.openWindow(url);
    })
  );
});
