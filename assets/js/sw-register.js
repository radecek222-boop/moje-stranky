/**
 * Service Worker Registration & Update Handler
 * Automaticky aktualizuje PWA při změně verze
 *
 * Vylepseno pro iOS PWA - agresivnejsi kontrola aktualizaci
 */

(function() {
  'use strict';

  // Kontrola podpory Service Worker
  if (!('serviceWorker' in navigator)) {
    console.log('[PWA] Service Worker není podporován');
    return;
  }

  let refreshing = false;
  let swRegistration = null;

  // Detekce iOS
  const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
  const isPWA = window.matchMedia('(display-mode: standalone)').matches ||
                window.navigator.standalone === true;

  // Interval pro kontrolu aktualizaci (iOS PWA potrebuje castejsi kontroly)
  const UPDATE_CHECK_INTERVAL = isIOS && isPWA ? 60000 : 300000; // 1 min pro iOS PWA, 5 min pro ostatni

  // Registrace Service Workeru
  window.addEventListener('load', async () => {
    try {
      swRegistration = await navigator.serviceWorker.register('/sw.js', {
        scope: '/',
        updateViaCache: 'none' // Vzdy kontrolovat novou verzi SW souboru
      });

      console.log('[PWA] Service Worker registrován:', swRegistration.scope);

      // Kontrolovat aktualizace pri nacteni
      await zkontrolujAktualizaceTiche();

      // Listener pro novou verzi
      swRegistration.addEventListener('updatefound', () => {
        const newWorker = swRegistration.installing;
        console.log('[PWA] Nalezena nová verze Service Workeru...');

        newWorker.addEventListener('statechange', () => {
          if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
            console.log('[PWA] Nová verze připravena k aktivaci');

            // Pro iOS PWA: Automaticky aktivovat bez ptani
            if (isIOS && isPWA) {
              console.log('[PWA] iOS PWA - automaticka aktivace');
              aktivovatNovouVerzi();
            } else {
              zobrazitAktualizacniBanner();
            }
          }
        });
      });

      // Periodicka kontrola aktualizaci
      setInterval(zkontrolujAktualizaceTiche, UPDATE_CHECK_INTERVAL);

    } catch (error) {
      console.error('[PWA] Chyba registrace Service Workeru:', error);
    }
  });

  // Kontrola aktualizaci kdyz se stranka stane viditelnou (iOS PWA fix)
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible' && swRegistration) {
      console.log('[PWA] Stranka viditelna - kontroluji aktualizace');
      zkontrolujAktualizaceTiche();
    }
  });

  // Kontrola pri focus (iOS PWA fix)
  window.addEventListener('focus', () => {
    if (swRegistration) {
      zkontrolujAktualizaceTiche();
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

  /**
   * Ticha kontrola aktualizaci (bez logovani pokud neni nova verze)
   */
  async function zkontrolujAktualizaceTiche() {
    if (!swRegistration) return;
    try {
      await swRegistration.update();
    } catch (e) {
      // Ticha chyba - muze byt offline
    }
  }

  /**
   * Aktivovat novou verzi (pro automatickou aktualizaci)
   */
  function aktivovatNovouVerzi() {
    if (swRegistration && swRegistration.waiting) {
      swRegistration.waiting.postMessage('SKIP_WAITING');
    }
  }

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
