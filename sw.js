/**
 * Khidma DZ — Service Worker
 * Strategy:
 *   - App shell (CSS, JS, fonts) → Cache First
 *   - Dashboard pages           → Network First (fresh data), fallback to cache
 *   - API calls                 → Network Only (never cache)
 *   - Static assets (icons)     → Cache First
 */

const CACHE_NAME    = 'khidma-v1';
const API_CACHE     = 'khidma-api-v1';

// App shell — always cache these on install
const PRECACHE = [
  './',
  'index.php',
  'login.php',
  'js/khidma-realtime.js',
  'icons/icon-192.png',
  'icons/icon-512.png',
  'offline.html',
];

// Pages to serve from network first, fall back to cache
const NETWORK_FIRST = [
  'dashboard-client.php',
  'dashboard-tech.php',
  'dashboard-admin.php',
  'paiment.php',
  'facture.php',
];

// Never cache these
const NEVER_CACHE = [
  'api/',
  'actions/',
];

// ── Install ──────────────────────────────────────────────────
self.addEventListener('install', e => {
  e.waitUntil(
    caches.open(CACHE_NAME)
      .then(c => c.addAll(PRECACHE.filter(u => !u.includes('offline'))))
      .then(() => self.skipWaiting())
  );
});

// ── Activate: clean old caches ───────────────────────────────
self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys
        .filter(k => k !== CACHE_NAME && k !== API_CACHE)
        .map(k => caches.delete(k))
      )
    ).then(() => self.clients.claim())
  );
});

// ── Fetch ────────────────────────────────────────────────────
self.addEventListener('fetch', e => {
  const url = e.request.url;

  // Skip non-GET and never-cache paths
  if (e.request.method !== 'GET') return;
  if (NEVER_CACHE.some(p => url.includes(p))) return;

  // External resources (fonts, CDN) — cache first
  if (!url.startsWith(self.location.origin)) {
    e.respondWith(cacheFirst(e.request));
    return;
  }

  // Dashboard pages — network first
  if (NETWORK_FIRST.some(p => url.includes(p))) {
    e.respondWith(networkFirst(e.request));
    return;
  }

  // Everything else — cache first
  e.respondWith(cacheFirst(e.request));
});

async function cacheFirst(req) {
  const cached = await caches.match(req);
  if (cached) return cached;
  try {
    const fresh = await fetch(req);
    if (fresh.ok) {
      const c = await caches.open(CACHE_NAME);
      c.put(req, fresh.clone());
    }
    return fresh;
  } catch {
    return new Response('Hors ligne', { status: 503 });
  }
}

async function networkFirst(req) {
  try {
    const fresh = await fetch(req);
    if (fresh.ok) {
      const c = await caches.open(CACHE_NAME);
      c.put(req, fresh.clone());
    }
    return fresh;
  } catch {
    const cached = await caches.match(req);
    if (cached) return cached;
    // Serve offline page
    return caches.match('offline.html')
      || new Response('<h2>Vous êtes hors ligne</h2>', {
           status: 503, headers: {'Content-Type':'text/html'}
         });
  }
}

// ── Push notifications ───────────────────────────────────────
self.addEventListener('push', e => {
  if (!e.data) return;
  let data;
  try { data = e.data.json(); }
  catch { data = { title: 'Khidma DZ', body: e.data.text() }; }

  e.waitUntil(
    self.registration.showNotification(data.title || 'Khidma DZ', {
      body:    data.body  || '',
      icon:    'icons/icon-192.png',
      badge:   'icons/icon-72.png',
      data:    { url: data.url || self.registration.scope },
      vibrate: [200, 100, 200],
      tag:     data.tag  || 'khidma-notif',
      renotify: true,
    })
  );
});

// ── Notification click → open/focus the app ──────────────────
self.addEventListener('notificationclick', e => {
  e.notification.close();
  const target = e.notification.data && e.notification.data.url
    ? e.notification.data.url
    : self.registration.scope;

  e.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true })
      .then(wins => {
        const match = wins.find(w => w.url.startsWith(self.registration.scope));
        if (match) { match.focus(); if (match.navigate) match.navigate(target); }
        else clients.openWindow(target);
      })
  );
});