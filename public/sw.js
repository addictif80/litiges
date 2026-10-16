const CACHE_NAME = 'locavente-litiges-v1.1.0';
const OFFLINE_URL = './offline.html';

const APP_SHELL = [
  './',
  './offline.html',
  './manifest.json',
  './assets/css/app.css',
  './assets/js/app.js',
  './assets/css/presentation.css',
  './assets/js/presentation.js',
  './assets/icons/icon.svg',
  './assets/icons/maskable.svg',
  './assets/icons/apple-touch-icon.svg',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(APP_SHELL))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k))
      )
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;

  // Ne pas intercepter les requêtes non-GET
  if (req.method !== 'GET') return;

  // Éviter d'intercepter les requêtes cross-origin non gérées
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) {
    return;
  }

  // Navigation (HTML) : network-first avec fallback offline
  if (req.mode === 'navigate') {
    event.respondWith(
      fetch(req).catch(() => caches.match(OFFLINE_URL))
    );
    return;
  }

  // Seules les ressources statiques sont mises en cache : jamais les réponses dynamiques
  // (JSON d'autocomplétion, téléchargements, flux iCal, API...) qui doivent toujours être fraîches.
  if (!url.pathname.includes('/assets/') && !url.pathname.endsWith('/manifest.json')) {
    return;
  }

  // Ressources statiques (CSS/JS/images/fonts) : cache-first, mise à jour en arrière-plan
  event.respondWith(
    caches.match(req).then((cached) => {
      if (cached) return cached;
      return fetch(req).then((res) => {
        // Ne mettre en cache que les réponses OK
        if (res && res.status === 200 && res.type === 'basic') {
          const clone = res.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(req, clone));
        }
        return res;
      }).catch(() => cached);
    })
  );
});

self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});
