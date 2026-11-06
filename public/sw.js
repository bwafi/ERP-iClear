const CACHE = 'absen-iclear-v1';
const IKON = ['./template/assets/pwa/icon-192.png', './template/assets/pwa/icon-512.png'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE).then((cache) => cache.addAll(IKON)).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim())
    );
});

// Hanya ikon PWA yang di-cache. Halaman, API absen, dan sesi selalu ke jaringan
// supaya validasi radius/roster dan data terbaru tidak basi.
self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET' || !event.request.url.includes('/template/assets/pwa/')) {
        return;
    }

    event.respondWith(
        caches.match(event.request).then((hit) => hit || fetch(event.request))
    );
});
