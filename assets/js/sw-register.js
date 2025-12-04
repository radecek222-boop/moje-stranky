/**
 * Service Worker Registration - Jednoduchá verze
 *
 * Tiché aktualizace bez UI - žádné bannery ani notifikace.
 * Aktualizace proběhne při dalším načtení stránky.
 *
 * @version 2025.12.04
 */

(function() {
  'use strict';

  const SW_PATH = '/sw.php';
  let swRegistration = null;

  // ============================================
  // HLAVNÍ REGISTRACE
  // ============================================
  async function registrujServiceWorker() {
    try {
      swRegistration = await navigator.serviceWorker.register(SW_PATH, {
        scope: '/',
        updateViaCache: 'none'
      });

      console.log('[PWA] Service Worker registrován');

      // Tichá kontrola aktualizací
      swRegistration.addEventListener('updatefound', () => {
        const newWorker = swRegistration.installing;
        console.log('[PWA] Nová verze nalezena');

        newWorker.addEventListener('statechange', () => {
          if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
            // Automaticky aktivovat novou verzi
            console.log('[PWA] Aktivuji novou verzi...');
            newWorker.postMessage('SKIP_WAITING');
          }
        });
      });

      // Periodická kontrola (každých 5 minut)
      setInterval(() => {
        if (swRegistration) {
          swRegistration.update().catch(() => {});
        }
      }, 300000);

    } catch (error) {
      console.error('[PWA] Chyba registrace SW:', error);
    }
  }

  // Kontrola při návratu na stránku
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible' && swRegistration) {
      swRegistration.update().catch(() => {});
    }
  });

  // Reload při aktivaci nového SW (jednou za session)
  let reloaded = false;
  navigator.serviceWorker.addEventListener('controllerchange', () => {
    if (reloaded) return;
    reloaded = true;
    console.log('[PWA] Nový SW aktivován - reload');
    window.location.reload();
  });

  // ============================================
  // INIT
  // ============================================
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', registrujServiceWorker);
  }

  // ============================================
  // GLOBÁLNÍ UTILITY (pro debug)
  // ============================================
  window.vynutitAktualizaci = async function() {
    console.log('[PWA] Vynucuji aktualizaci...');
    const cacheNames = await caches.keys();
    await Promise.all(cacheNames.map(name => caches.delete(name)));
    const registrations = await navigator.serviceWorker.getRegistrations();
    await Promise.all(registrations.map(reg => reg.unregister()));
    window.location.reload(true);
  };

})();
