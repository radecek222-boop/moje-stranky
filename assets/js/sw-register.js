/**
 * Service Worker Registration & Update Handler
 * Automaticky aktualizuje PWA při změně verze
 */

(function() {
  'use strict';

  // Kontrola podpory Service Worker
  if (!('serviceWorker' in navigator)) {
    console.log('[PWA] Service Worker není podporován');
    return;
  }

  let refreshing = false;

  // Registrace Service Workeru
  window.addEventListener('load', async () => {
    try {
      const registration = await navigator.serviceWorker.register('/sw.js', {
        scope: '/'
      });

      console.log('[PWA] Service Worker registrován:', registration.scope);

      // Kontrolovat aktualizace při každém načtení stránky
      registration.update();

      // Listener pro novou verzi
      registration.addEventListener('updatefound', () => {
        const newWorker = registration.installing;
        console.log('[PWA] Nalezena nová verze Service Workeru...');

        newWorker.addEventListener('statechange', () => {
          if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
            // Nová verze je připravena
            console.log('[PWA] Nová verze připravena k aktivaci');
            zobrazitAktualizacniBanner();
          }
        });
      });

    } catch (error) {
      console.error('[PWA] Chyba registrace Service Workeru:', error);
    }
  });

  // Reload stránky když se aktivuje nový SW
  navigator.serviceWorker.addEventListener('controllerchange', () => {
    if (!refreshing) {
      refreshing = true;
      console.log('[PWA] Nový Service Worker aktivován, reloaduji...');
      window.location.reload();
    }
  });

  // Zprávy od Service Workeru
  navigator.serviceWorker.addEventListener('message', (event) => {
    if (event.data.type === 'SW_UPDATED') {
      console.log('[PWA] Service Worker aktualizován na verzi:', event.data.version);
    }
  });

  /**
   * Zobrazí banner s možností aktualizace
   */
  function zobrazitAktualizacniBanner() {
    // Kontrola jestli banner už neexistuje
    if (document.getElementById('pwa-update-banner')) {
      return;
    }

    const banner = document.createElement('div');
    banner.id = 'pwa-update-banner';
    banner.innerHTML = `
      <div style="
        position: fixed;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        background: #222;
        color: #fff;
        padding: 16px 24px;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        display: flex;
        align-items: center;
        gap: 16px;
        z-index: 99999;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        font-size: 14px;
        max-width: 90vw;
      ">
        <span>Je dostupná nová verze aplikace</span>
        <button id="pwa-update-btn" style="
          background: #fff;
          color: #222;
          border: none;
          padding: 8px 16px;
          border-radius: 4px;
          font-weight: 600;
          cursor: pointer;
          white-space: nowrap;
        ">Aktualizovat</button>
        <button id="pwa-dismiss-btn" style="
          background: transparent;
          color: #999;
          border: none;
          padding: 8px;
          cursor: pointer;
          font-size: 18px;
          line-height: 1;
        ">&times;</button>
      </div>
    `;

    document.body.appendChild(banner);

    // Tlačítko aktualizovat
    document.getElementById('pwa-update-btn').addEventListener('click', () => {
      // Říct SW aby se aktivoval
      navigator.serviceWorker.ready.then((registration) => {
        if (registration.waiting) {
          registration.waiting.postMessage('SKIP_WAITING');
        }
      });
      banner.remove();
    });

    // Tlačítko zavřít
    document.getElementById('pwa-dismiss-btn').addEventListener('click', () => {
      banner.remove();
    });
  }

  /**
   * Manuální kontrola aktualizací (volat z konzole nebo tlačítka)
   */
  window.zkontrolujAktualizace = async function() {
    if (!navigator.serviceWorker.controller) {
      console.log('[PWA] Žádný aktivní Service Worker');
      return;
    }

    const registration = await navigator.serviceWorker.ready;
    console.log('[PWA] Kontroluji aktualizace...');
    await registration.update();
    console.log('[PWA] Kontrola dokončena');
  };

  /**
   * Vynutit aktualizaci (smaže cache a reloadne)
   */
  window.vynutitAktualizaci = async function() {
    console.log('[PWA] Vynucuji aktualizaci...');

    // Smazat všechny cache
    const cacheNames = await caches.keys();
    await Promise.all(cacheNames.map(name => caches.delete(name)));

    // Odregistrovat SW
    const registrations = await navigator.serviceWorker.getRegistrations();
    await Promise.all(registrations.map(reg => reg.unregister()));

    console.log('[PWA] Cache smazán, reloaduji...');
    window.location.reload(true);
  };

})();
