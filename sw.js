/**
 * WGS Service Worker
 * Verze se mění při každém deploymentu = automatická aktualizace
 */

// VERZE - ZMĚŇ PŘI KAŽDÉM RELEASU!
const SW_VERSION = '2025.11.27.001';
const CACHE_NAME = `wgs-cache-${SW_VERSION}`;

// Soubory k cachování (základní shell)
const STATIC_ASSETS = [
  '/offline.php',
  '/assets/css/offline.css',
  '/icon192.png',
  '/icon512.png'
];

// Instalace - cachovat základní soubory
self.addEventListener('install', (event) => {
  console.log(`[SW ${SW_VERSION}] Instalace...`);

  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        console.log(`[SW ${SW_VERSION}] Cachuji statické soubory`);
        return cache.addAll(STATIC_ASSETS);
      })
      .then(() => {
        // DŮLEŽITÉ: Okamžitě aktivovat novou verzi
        console.log(`[SW ${SW_VERSION}] Skip waiting - aktivuji ihned`);
        return self.skipWaiting();
      })
  );
});

// Aktivace - smazat staré cache
self.addEventListener('activate', (event) => {
  console.log(`[SW ${SW_VERSION}] Aktivace...`);

  event.waitUntil(
    caches.keys()
      .then((cacheNames) => {
        return Promise.all(
          cacheNames
            .filter((name) => name.startsWith('wgs-cache-') && name !== CACHE_NAME)
            .map((name) => {
              console.log(`[SW ${SW_VERSION}] Mažu starý cache: ${name}`);
              return caches.delete(name);
            })
        );
      })
      .then(() => {
        // DŮLEŽITÉ: Převzít kontrolu nad všemi klienty
        console.log(`[SW ${SW_VERSION}] Přebírám kontrolu nad klienty`);
        return self.clients.claim();
      })
      .then(() => {
        // Oznámit všem klientům že je nová verze
        return self.clients.matchAll().then((clients) => {
          clients.forEach((client) => {
            client.postMessage({
              type: 'SW_UPDATED',
              version: SW_VERSION
            });
          });
        });
      })
  );
});

// Fetch - Network First strategie (vždy čerstvá data)
self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);

  // Ignorovat non-GET requesty
  if (event.request.method !== 'GET') {
    return;
  }

  // Ignorovat external requesty (API, CDN)
  if (!url.origin.includes(self.location.origin)) {
    return;
  }

  // Pro HTML stránky: Network First (vždy čerstvé)
  if (event.request.headers.get('accept')?.includes('text/html')) {
    event.respondWith(
      fetch(event.request)
        .then((response) => {
          // Uložit do cache pro offline
          const responseClone = response.clone();
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(event.request, responseClone);
          });
          return response;
        })
        .catch(() => {
          // Offline - zkusit cache nebo offline stránku
          return caches.match(event.request)
            .then((cached) => cached || caches.match('/offline.php'));
        })
    );
    return;
  }

  // Pro ostatní assety (JS, CSS, obrázky): Network First s fallbackem
  event.respondWith(
    fetch(event.request)
      .then((response) => {
        // Cache pouze úspěšné odpovědi
        if (response.ok) {
          const responseClone = response.clone();
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(event.request, responseClone);
          });
        }
        return response;
      })
      .catch(() => {
        return caches.match(event.request);
      })
  );
});

// Zprávy od klientů
self.addEventListener('message', (event) => {
  if (event.data === 'SKIP_WAITING') {
    self.skipWaiting();
  }

  if (event.data === 'GET_VERSION') {
    event.ports[0].postMessage({ version: SW_VERSION });
  }
});

console.log(`[SW ${SW_VERSION}] Service Worker načten`);
