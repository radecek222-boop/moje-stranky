/**
 * Service Worker Registration - Minimální verze
 *
 * Pouze registrace SW pro offline podporu a push notifikace.
 * Žádné automatické aktualizace ani reloady.
 *
 * @version 2025.12.04
 */

(function() {
  'use strict';

  const SW_PATH = '/sw.php';

  async function registrujServiceWorker() {
    try {
      await navigator.serviceWorker.register(SW_PATH, {
        scope: '/'
      });
      console.log('[PWA] Service Worker registrován');
    } catch (error) {
      console.error('[PWA] Chyba registrace SW:', error);
    }
  }

  if ('serviceWorker' in navigator) {
    window.addEventListener('load', registrujServiceWorker);
  }

})();
