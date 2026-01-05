const CACHE_NAME = "helios-info-v4";
const API_CACHE_NAME = "helios-api-v4";

// Helper: Check if we should handle this request
function shouldHandle(url) {
    return url.includes("soneprojects.com/ai/helios-info");
}

// Helper: Strip version query params from URL for caching
function getCleanUrl(url) {
    const urlObj = new URL(url);
    urlObj.searchParams.delete("ver");
    urlObj.searchParams.delete("_t");
    return urlObj.toString();
}

// Cache first with version stripping for static assets
async function cacheFirstWithVersionStrip(request) {
    const cache = await caches.open(CACHE_NAME);
    const cleanUrl = getCleanUrl(request.url);

    const cachedResponse = await cache.match(cleanUrl);
    if (cachedResponse) {
        return cachedResponse;
    }

    try {
        const networkResponse = await fetch(request);
        if (networkResponse.ok) {
            cache.put(cleanUrl, networkResponse.clone());
        }
        return networkResponse;
    } catch (error) {
        return new Response("Offline", { status: 503 });
    }
}

// Fetch handler
self.addEventListener("fetch", (event) => {
    const url = event.request.url;

    // Only handle our own requests
    if (!shouldHandle(url)) {
        return;
    }

    const urlObj = new URL(url);

    // Handle API requests (network first, cache fallback)
    if (urlObj.pathname.includes("api.php")) {
        event.respondWith(networkFirstForAPI(event.request));
        return;
    }

    // Handle core assets (app.js, styles.css) - NETWORK FIRST to ensure updates
    if (
        urlObj.pathname.endsWith("app.js") ||
        urlObj.pathname.endsWith("styles.css")
    ) {
        event.respondWith(networkFirstForAssets(event.request));
        return;
    }

    // For other static files (images, manifest) - cache first with version stripping
    event.respondWith(cacheFirstWithVersionStrip(event.request));
});

// ...

// Network First strategy for Assets (JS/CSS)
async function networkFirstForAssets(request) {
    const cache = await caches.open(CACHE_NAME);
    const cleanUrl = getCleanUrl(request.url);

    try {
        const networkResponse = await fetch(request);
        if (networkResponse.ok) {
            cache.put(cleanUrl, networkResponse.clone());
            console.log("[SW] Asset updated:", cleanUrl);
        }
        return networkResponse;
    } catch (error) {
        console.log("[SW] Network failed for asset, using cache:", cleanUrl);
        const cachedResponse = await cache.match(cleanUrl);
        if (cachedResponse) return cachedResponse;
        // Fallback?
        return new Response("/* Offline */", {
            headers: { "Content-Type": "text/css" },
        });
    }
}

// Network First strategy for API
async function networkFirstForAPI(request) {
    const cache = await caches.open(API_CACHE_NAME);
    const cleanUrl = getCleanUrl(request.url);

    try {
        const networkResponse = await fetch(request);
        if (networkResponse.ok) {
            cache.put(cleanUrl, networkResponse.clone());
        }
        return networkResponse;
    } catch (error) {
        const cachedResponse = await cache.match(cleanUrl);
        if (cachedResponse) return cachedResponse;

        return new Response(
            JSON.stringify({
                error: "Offline",
                message: "Brak połączenia. Najpierw załaduj stronę online.",
                offline: true,
                movies: [],
            }),
            {
                status: 503,
                headers: { "Content-Type": "application/json" },
            }
        );
    }
}

// Listen for skip waiting message
self.addEventListener("message", (event) => {
    if (event.data === "skipWaiting") {
        self.skipWaiting();
    }
});
