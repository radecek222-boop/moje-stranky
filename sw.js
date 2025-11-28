/**
 * WGS Service Worker
 * Verze se mění při každém deploymentu = automatická aktualizace
 */

// VERZE - ZMĚŇ PŘI KAŽDÉM RELEASU!
const SW_VERSION = '2025.11.28.022';
const CACHE_NAME = `wgs-cache-${SW_VERSION}`;

// Soubory k cachování (základní shell)
const STATIC_ASSETS = [
  '/offline.php',
  '/assets/css/offline.css',
  '/icon192.png',
  '/icon512.png'
];

// FIX: Stranky ktere se NIKDY nesmi cachovat (obsahuji CSRF tokeny)
// Tyto stranky musi vzdy jit primo na server pro cerstva data
const NEVER_CACHE_PAGES = [
  '/login.php',
  '/registration.php',
  '/password_reset.php',
  '/app/controllers/get_csrf_token.php',
  '/app/controllers/login_controller.php'
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

  // FIX: Stranky s CSRF tokeny - VZDY network only, NIKDY cache
  // Reseni problemu "Neplatny CSRF token" pri prvnim prihlaseni z PWA
  const jeNevercachePage = NEVER_CACHE_PAGES.some(stranka => url.pathname.endsWith(stranka));
  if (jeNevercachePage) {
    event.respondWith(
      fetch(event.request)
        .catch(() => {
          // Pokud jsme offline a jde o login, ukazat offline stranku
          if (url.pathname.endsWith('.php')) {
            return caches.match('/offline.php');
          }
          return new Response('Offline', { status: 503 });
        })
    );
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

// ========================================
// WEB PUSH NOTIFIKACE - Pro iOS 16.4+ a ostatní prohlížeče
// ========================================

// Push event - přijímání push zpráv ze serveru
self.addEventListener('push', (event) => {
  console.log(`[SW ${SW_VERSION}] Push zpráva přijata`);

  let data = {
    title: 'WGS Notifikace',
    body: 'Máte novou zprávu',
    icon: '/icon192.png',
    badge: '/icon192.png',
    tag: 'wgs-notification',
    data: {}
  };

  // Pokusit se parsovat JSON data z push zprávy
  if (event.data) {
    try {
      const payload = event.data.json();
      data = { ...data, ...payload };
    } catch (e) {
      // Pokud není JSON, použít jako text
      data.body = event.data.text();
    }
  }

  const options = {
    body: data.body,
    icon: data.icon || '/icon192.png',
    badge: data.badge || '/icon192.png',
    tag: data.tag || 'wgs-notification',
    vibrate: [200, 100, 200],
    data: data.data || {},
    actions: data.actions || [],
    requireInteraction: false
  };

  event.waitUntil(
    self.registration.showNotification(data.title, options)
  );
});

// Kliknutí na notifikaci
self.addEventListener('notificationclick', (event) => {
  console.log(`[SW ${SW_VERSION}] Kliknuto na notifikaci`);

  event.notification.close();

  // Získat URL pro otevření
  let urlToOpen = '/seznam.php';
  if (event.notification.data && event.notification.data.url) {
    urlToOpen = event.notification.data.url;
  } else if (event.notification.data && event.notification.data.claim_id) {
    urlToOpen = '/seznam.php?highlight=' + event.notification.data.claim_id;
  }

  // Otevřít nebo focusnout okno
  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true })
      .then((clientList) => {
        // Zkusit najít existující okno
        for (const client of clientList) {
          if (client.url.includes(self.location.origin) && 'focus' in client) {
            client.navigate(urlToOpen);
            return client.focus();
          }
        }
        // Pokud není otevřené, otevřít nové
        if (self.clients.openWindow) {
          return self.clients.openWindow(urlToOpen);
        }
      })
  );
});

// Zavření notifikace
self.addEventListener('notificationclose', (event) => {
  console.log(`[SW ${SW_VERSION}] Notifikace zavřena`);
});

console.log(`[SW ${SW_VERSION}] Service Worker načten`);
