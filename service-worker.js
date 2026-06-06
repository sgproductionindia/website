const SG_CACHE = "sg-production-pwa-v1";
const CORE_ASSETS = [
  "/",
  "/index.php",
  "/styles.css",
  "/styles.min.css",
  "/script.js",
  "/script.min.js",
  "/transitions.min.css",
  "/transitions.min.js",
  "/page-search.js",
  "/assets/sg-logo.svg"
];

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(SG_CACHE)
      .then((cache) => cache.addAll(CORE_ASSETS))
      .catch(() => undefined)
  );
  self.skipWaiting();
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(
      keys
        .filter((key) => key !== SG_CACHE)
        .map((key) => caches.delete(key))
    ))
  );
  self.clients.claim();
});

function isCacheableStatic(pathname) {
  return [
    "/styles.css",
    "/styles.min.css",
    "/script.js",
    "/script.min.js",
    "/transitions.min.css",
    "/transitions.min.js",
    "/page-search.js"
  ].includes(pathname)
    || pathname.startsWith("/assets/")
    || pathname.startsWith("/uploads/covers/")
    || pathname.startsWith("/uploads/site/");
}

async function networkFirst(request) {
  const cache = await caches.open(SG_CACHE);
  try {
    const fresh = await fetch(request);
    if (fresh && fresh.ok) {
      cache.put(request, fresh.clone());
    }
    return fresh;
  } catch {
    return (await cache.match(request)) || cache.match("/");
  }
}

async function cacheFirst(request) {
  const cache = await caches.open(SG_CACHE);
  const cached = await cache.match(request);
  if (cached) return cached;

  const fresh = await fetch(request);
  if (fresh && fresh.ok) {
    cache.put(request, fresh.clone());
  }
  return fresh;
}

self.addEventListener("fetch", (event) => {
  const request = event.request;
  if (request.method !== "GET") return;

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  if (request.mode === "navigate") {
    event.respondWith(networkFirst(request));
    return;
  }

  if (isCacheableStatic(url.pathname)) {
    event.respondWith(cacheFirst(request));
  }
});
