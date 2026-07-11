<?php
header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-store'); // SW must never be cached by HTTP
ini_set('zlib.output_compression', 'Off');
?>
// ============================================================
// Service Worker v10 - Mái Nhà Xanh PWA
// Strategy: Network-First for HTML, Stale-While-Revalidate for assets
// ============================================================
importScripts("https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.sw.js");

const CACHE_NAME   = "mainhaxanh-v10";
const STATIC_CACHE = "mainhaxanh-static-v10";
const OFFLINE_URL  = "offline.php";

// Core static assets (cache on install)
const PRECACHE_URLS = [
  "./",
  "offline.php",
  "assets/css/style.css",
  "assets/js/main.js",
  "assets/images/logo.png",
  "assets/images/myhome.png",
  "assets/images/myhome.png",
  "manifest.json"
];

// ── Install: pre-cache static files ──────────────────────────
self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(STATIC_CACHE)
      .then((cache) => {
        return Promise.allSettled(
          PRECACHE_URLS.map(url => 
            cache.add(url).catch(err => console.warn("[SW] Pre-cache failed for:", url, err))
          )
        );
      })
  );
  self.skipWaiting();
});

// ── Activate: clean up old caches ────────────────────────────
self.addEventListener("activate", (event) => {
  const validCaches = [CACHE_NAME, STATIC_CACHE];
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys
          .filter((k) => !validCaches.includes(k))
          .map((k) => caches.delete(k))
      )
    )
  );
  self.clients.claim();
});

// ── Skip waiting on message ───────────────────────────────────
self.addEventListener("message", (event) => {
  if (event.data && event.data.type === "SKIP_WAITING") {
    self.skipWaiting();
  }
});

// ── Fetch: tiered caching strategy ───────────────────────────
self.addEventListener("fetch", (event) => {
  if (event.request.method !== "GET") return;

  let url;
  try { url = new URL(event.request.url); } catch (e) { return; }

  // 1. Skip chrome-extension and non-http requests
  if (!url.protocol.startsWith("http")) return;

  // 2. Chỉ xử lý request của chính website (same-origin)
  // Bỏ qua tất cả request đến domain ngoài như profreehost.com, lh3.googleusercontent.com, ...
  const ownHostname = self.location.hostname;
  if (url.hostname !== ownHostname) return;

  // 3. Skip realtime sockets, WebRTC, and push notifications
  if (url.pathname.includes("socket.io") || url.port === "3000" || url.hostname.includes("onesignal") || url.hostname.includes("zego")) {
    return;
  }

  // 4. API calls — always Network, no cache
  if (url.pathname.includes("/api/") || url.searchParams.has("ajax")) {
    event.respondWith(fetch(event.request));
    return;
  }

  // 3. HTML pages — Network First, fallback to offline page
  const isHTML = event.request.headers.get("accept")?.includes("text/html");
  if (isHTML) {
    event.respondWith(
      fetch(event.request)
        .catch(() =>
          caches.match(event.request).then(
            (cached) => cached || caches.match(OFFLINE_URL)
          ).then(
            (fallback) => fallback || new Response("Bạn đang ngoại tuyến và trang này chưa được lưu cache.", {
              status: 503,
              statusText: "Service Unavailable",
              headers: new Headers({ "Content-Type": "text/html; charset=utf-8" })
            })
          )
        )
    );
    return;
  }

  // 4. Static assets (CSS/JS/Images) — Stale-While-Revalidate
  event.respondWith(
    caches.open(CACHE_NAME).then((cache) =>
      cache.match(event.request).then((cached) => {
        const fetchPromise = fetch(event.request)
          .then((response) => {
            if (response && response.status === 200 && response.type !== "opaque") {
              cache.put(event.request, response.clone());
            }
            return response;
          })
          .catch((err) => {
            if (cached) return cached;
            throw err;
          });

        // Return cached immediately, update in background
        return cached || fetchPromise;
      })
    )
  );
});
