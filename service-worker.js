/**
 * Service worker — Mairie PWA (Phase X)
 *
 * Stratégie :
 *  - Précache léger (manifest + icônes + page hors-ligne)
 *  - Network-first pour HTML (afin d'avoir le contenu frais)
 *  - Cache-first pour assets statiques (CSS, JS, images, SVG)
 *  - Bypass total pour POST, admin/*, super-admin/* et endpoints sensibles
 *  - Fallback /offline.html quand le réseau est totalement indisponible
 */
const CACHE_VERSION = "maire-v1-2026-05-11";
const CACHE_STATIC = `static-${CACHE_VERSION}`;
const CACHE_PAGES = `pages-${CACHE_VERSION}`;

const PRECACHE_URLS = [
  "./",
  "./offline.html",
  "./manifest.webmanifest",
  "./assets/css/style.css",
  "./assets/img/pwa-icon-192.svg",
  "./assets/img/pwa-icon-512.svg",
];

const BYPASS_PATTERNS = [
  /\/admin\//i,
  /\/super-admin\//i,
  /\/mairie\//i,
  /\/api\/v1\//i,
  /paiement-webhook\.php$/i,
  /telecharger-document\.php/i,
  /\.php\?.*action=/i,
];

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(CACHE_STATIC).then((cache) =>
      cache.addAll(PRECACHE_URLS).catch(() => null)
    ).then(() => self.skipWaiting())
  );
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys
          .filter((k) => k !== CACHE_STATIC && k !== CACHE_PAGES)
          .map((k) => caches.delete(k))
      )
    ).then(() => self.clients.claim())
  );
});

function shouldBypass(request) {
  if (request.method !== "GET") return true;
  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return true;
  return BYPASS_PATTERNS.some((rx) => rx.test(url.pathname + url.search));
}

self.addEventListener("fetch", (event) => {
  const request = event.request;
  if (shouldBypass(request)) {
    return;
  }

  const url = new URL(request.url);
  const accept = request.headers.get("Accept") || "";

  // HTML → network-first, cache fallback, page offline en dernier recours
  if (accept.includes("text/html")) {
    event.respondWith(
      fetch(request)
        .then((resp) => {
          const copy = resp.clone();
          caches.open(CACHE_PAGES).then((cache) => cache.put(request, copy)).catch(() => null);
          return resp;
        })
        .catch(() =>
          caches.match(request).then((cached) => cached || caches.match("./offline.html"))
        )
    );
    return;
  }

  // Assets statiques → cache-first
  event.respondWith(
    caches.match(request).then((cached) => {
      if (cached) return cached;
      return fetch(request).then((resp) => {
        if (resp.ok && resp.type === "basic") {
          const copy = resp.clone();
          caches.open(CACHE_STATIC).then((cache) => cache.put(request, copy)).catch(() => null);
        }
        return resp;
      }).catch(() => cached);
    })
  );
});
