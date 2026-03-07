<?php
/**
 * WGS Service Worker - Dynamická verze
 *
 * Verze se automaticky mění podle filemtime klíčových souborů.
 * Není třeba ruční update SW_VERSION.
 */

// KRITICKÉ: Service Worker NESMÍ být cachován
header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Service-Worker-Allowed: /');

// Výpočet verze z filemtime klíčových souborů
$klicoveSoubory = [
    __DIR__ . '/assets/js/psa-kalkulator.js',
    __DIR__ . '/assets/js/seznam.js',
    __DIR__ . '/assets/js/objednatservis.js',
    __DIR__ . '/assets/js/utils.min.js',
    __DIR__ . '/assets/css/main.min.css',
    __DIR__ . '/assets/css/modal-detail.min.css',
    __DIR__ . '/assets/css/seznam.min.css',
    __DIR__ . '/assets/css/psa-kalkulator.min.css',
    __DIR__ . '/init.php',
    __FILE__ // sw.php sám
];

$maxMtime = 0;
foreach ($klicoveSoubory as $soubor) {
    if (file_exists($soubor)) {
        $mtime = filemtime($soubor);
        if ($mtime > $maxMtime) {
            $maxMtime = $mtime;
        }
    }
}

// Verze ve formátu: rok.měsíc.den.hodina_minuta
$swVerze = date('Y.m.d.Hi', $maxMtime ?: time());

?>
/**
 * WGS Service Worker
 * Automatická verze z deploy timestamp
 *
 * @version <?= $swVerze ?>

 * @generated <?= date('Y-m-d H:i:s') ?>

 */

// ============================================
// VERZE - Automaticky generována z filemtime
// ============================================
const SW_VERSION = '<?= $swVerze ?>';
const CACHE_NAME = `wgs-cache-${SW_VERSION}`;

console.log(`[SW ${SW_VERSION}] Service Worker načten (auto-versioning)`);

// Soubory k cachování (ikony pro PWA)
const STATIC_ASSETS = [
  '/icon192.png',
  '/icon512.png'
];

// Stránky které se NIKDY nesmí cachovat (obsahují CSRF tokeny)
const NEVER_CACHE_PAGES = [
  '/login.php',
  '/registration.php',
  '/password_reset.php',
  '/app/controllers/get_csrf_token.php',
  '/app/controllers/login_controller.php'
];

// ============================================
// INSTALACE
// ============================================
self.addEventListener('install', (event) => {
  console.log(`[SW ${SW_VERSION}] Instalace...`);

  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        console.log(`[SW ${SW_VERSION}] Cachuji offline shell`);
        return cache.addAll(STATIC_ASSETS);
      })
      .then(() => {
        console.log(`[SW ${SW_VERSION}] Instalace dokončena - skipWaiting()`);
        // Automaticky přeskočit čekání - nový SW nastoupí okamžitě
        return self.skipWaiting();
      })
  );
});

// ============================================
// AKTIVACE
// ============================================
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
        // KRITICKÉ: Převzít kontrolu nad všemi klienty
        console.log(`[SW ${SW_VERSION}] clients.claim() - přebírám kontrolu`);
        return self.clients.claim();
      })
      .then(() => {
        // Oznámit všem klientům novou verzi
        return self.clients.matchAll().then((clients) => {
          clients.forEach((client) => {
            client.postMessage({
              type: 'SW_ACTIVATED',
              version: SW_VERSION,
              timestamp: Date.now()
            });
          });
        });
      })
  );
});

// ============================================
// FETCH - Network First strategie
// ============================================

// Dynamické API endpointy - SW je NESMÍ interceptovat
// Pokud by SW kontext byl uzavřen, tyto requesty by selhaly s "Service Worker context closed"
// Prohlížeč vyřídí tyto requesty přímo, nezávisle na životním cyklu SW
const PASSTHROUGH_PATHS = [
  '/app/controllers/',
  '/api/'
];

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

  // API a dynamické endpointy - přeskočit, nechat prohlížeč vyřídit přímo
  // Tím se předejde chybě "Service Worker context closed" při uzavření SW kontextu
  const jeApiRequest = PASSTHROUGH_PATHS.some(cesta => url.pathname.startsWith(cesta));
  if (jeApiRequest) {
    return;
  }

  // Stránky s CSRF tokeny - VŽDY network only
  const jeNevercachePage = NEVER_CACHE_PAGES.some(stranka => url.pathname.endsWith(stranka));
  if (jeNevercachePage) {
    event.respondWith(
      fetch(event.request)
        .catch(() => {
          // Bez offline fallbacku - vrátit HTTP 503
          return new Response('Aplikace vyžaduje internetové připojení.', {
            status: 503,
            statusText: 'Service Unavailable',
            headers: { 'Content-Type': 'text/plain; charset=utf-8' }
          });
        })
    );
    return;
  }

  // Pro HTML stránky: Network First
  if (event.request.headers.get('accept')?.includes('text/html')) {
    event.respondWith(
      fetch(event.request)
        .then((response) => {
          // Uložit do cache pro offline (ale nepřepisovat s ?v= parametry)
          if (response.ok && !url.search.includes('v=')) {
            const responseClone = response.clone();
            caches.open(CACHE_NAME).then((cache) => {
              cache.put(event.request, responseClone);
            });
          }
          return response;
        })
        .catch(() => {
          // Zkusit cache, pokud není - vrátit HTTP 503
          return caches.match(event.request)
            .then((cached) => {
              if (cached) return cached;
              return new Response('Aplikace vyžaduje internetové připojení.', {
                status: 503,
                statusText: 'Service Unavailable',
                headers: { 'Content-Type': 'text/plain; charset=utf-8' }
              });
            });
        })
    );
    return;
  }

  // Pro ostatní assety: Network First s fallbackem
  event.respondWith(
    fetch(event.request)
      .then((response) => {
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

// ============================================
// MESSAGE HANDLING
// ============================================
self.addEventListener('message', (event) => {
  console.log(`[SW ${SW_VERSION}] Zpráva:`, event.data);

  // Vynutit aktivaci
  if (event.data === 'SKIP_WAITING') {
    console.log(`[SW ${SW_VERSION}] SKIP_WAITING přijat`);
    self.skipWaiting();
  }

  // Vrátit verzi
  if (event.data === 'GET_VERSION') {
    event.ports[0]?.postMessage({ version: SW_VERSION });
  }

  // PING_VERSION - pro kontrolu z klientské strany
  if (event.data?.type === 'PING_VERSION') {
    const clientId = event.data.clientId;
    event.source?.postMessage({
      type: 'PONG_VERSION',
      version: SW_VERSION,
      clientId: clientId,
      timestamp: Date.now()
    });
  }

  // GET_CACHE_INFO - pro debug
  if (event.data === 'GET_CACHE_INFO') {
    caches.keys().then((names) => {
      event.source?.postMessage({
        type: 'CACHE_INFO',
        caches: names,
        currentCache: CACHE_NAME,
        version: SW_VERSION
      });
    });
  }
});

// ============================================
// WEB PUSH NOTIFIKACE
// ============================================
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

  if (event.data) {
    try {
      const payload = event.data.json();
      data = { ...data, ...payload };
    } catch (e) {
      data.body = event.data.text();
    }
  }

  // Výchozí akce - zobrazit zakázku (jen pokud je k dispozici ID)
  const vychoziAkce = [];
  if (data.data && data.data.claim_id) {
    vychoziAkce.push({ action: 'zobrazit', title: 'Zobrazit zakázku' });
  }
  if (!data.data || !data.data.claim_id) {
    vychoziAkce.push({ action: 'zobrazit', title: 'Otevřít aplikaci' });
  }

  const options = {
    body: data.body,
    icon: data.icon || '/icon192.png',
    badge: data.badge || '/icon192.png',
    tag: data.tag || 'wgs-notification',
    vibrate: data.silent ? [] : [200, 100, 200],
    data: data.data || {},
    actions: data.actions && data.actions.length > 0 ? data.actions : vychoziAkce,
    requireInteraction: data.requireInteraction || false,
    timestamp: data.timestamp || Date.now(),
    renotify: true,
    dir: 'ltr',
    lang: 'cs'
  };

  // Velký obrázek v notifikaci (volitelný)
  if (data.image) {
    options.image = data.image;
  }

  event.waitUntil(
    self.registration.showNotification(data.title, options)
  );
});

// Kliknutí na notifikaci nebo akční tlačítko
self.addEventListener('notificationclick', (event) => {
  console.log(`[SW ${SW_VERSION}] Kliknuto na notifikaci, akce: ${event.action}`);

  event.notification.close();

  let urlToOpen = '/seznam.php';

  // Určit URL podle akce nebo dat notifikace
  if (event.action === 'zaviit') {
    // Akce "zavřít" - pouze zavřít notifikaci, neotevírat nic
    return;
  }

  if (event.notification.data?.url) {
    urlToOpen = event.notification.data.url;
  } else if (event.notification.data?.claim_id) {
    urlToOpen = '/seznam.php?highlight=' + event.notification.data.claim_id;
  }

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true })
      .then((clientList) => {
        // Pokusit se fokusovat existující okno se správnou URL
        for (const client of clientList) {
          if (client.url.includes(self.location.origin) && 'focus' in client) {
            client.navigate(urlToOpen);
            return client.focus();
          }
        }
        // Jinak otevřít nové okno
        if (self.clients.openWindow) {
          return self.clients.openWindow(urlToOpen);
        }
      })
  );
});

self.addEventListener('notificationclose', (event) => {
  console.log(`[SW ${SW_VERSION}] Notifikace zavřena`);
});
