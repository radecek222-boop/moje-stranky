/**
 * WGS Service Worker - MIGRACE na sw.php
 *
 * Tento soubor slouží pouze pro zpětnou kompatibilitu.
 * Nové instalace používají sw.php s dynamickým verzováním.
 *
 * @deprecated Použijte sw.php
 * @version MIGRATION-2025.12.03
 */

const SW_VERSION = 'PDF-RACE-FIX-2026.01.21';
const CACHE_NAME = `wgs-migration-${SW_VERSION}`;

console.log(`[SW ${SW_VERSION}] Migrační SW - přesměrování na sw.php`);

// Instalace - okamžitě se aktivovat
self.addEventListener('install', (event) => {
  console.log(`[SW ${SW_VERSION}] Instalace migračního SW`);
  event.waitUntil(self.skipWaiting());
});

// Aktivace - smazat staré cache a oznámit migraci
self.addEventListener('activate', (event) => {
  console.log(`[SW ${SW_VERSION}] Aktivace - mažu staré cache`);

  event.waitUntil(
    caches.keys()
      .then((cacheNames) => {
        return Promise.all(
          cacheNames
            .filter((name) => name.startsWith('wgs-'))
            .map((name) => {
              console.log(`[SW ${SW_VERSION}] Mažu cache: ${name}`);
              return caches.delete(name);
            })
        );
      })
      .then(() => {
        return self.clients.claim();
      })
      .then(() => {
        // Oznámit všem klientům migraci
        return self.clients.matchAll().then((clients) => {
          clients.forEach((client) => {
            client.postMessage({
              type: 'SW_MIGRATION',
              message: 'Přepínám na sw.php',
              version: SW_VERSION
            });
          });
        });
      })
  );
});

// Fetch - Network only (žádné cachování během migrace)
self.addEventListener('fetch', (event) => {
  // Prostě pustit request na network
  return;
});

// Zprávy
self.addEventListener('message', (event) => {
  if (event.data === 'GET_VERSION') {
    event.ports[0]?.postMessage({ version: SW_VERSION, migration: true });
  }

  if (event.data?.type === 'PING_VERSION') {
    event.source?.postMessage({
      type: 'PONG_VERSION',
      version: SW_VERSION,
      migration: true
    });
  }
});
