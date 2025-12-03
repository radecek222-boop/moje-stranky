/**
 * Service Worker Registration & Auto-Update Handler
 *
 * Funkce:
 * - Automatická registrace SW (sw.php s dynamickou verzí)
 * - PING_VERSION kanál pro kontrolu verze
 * - Ochrana proti reload loop
 * - Bezpečná aktualizace s toast notifikací
 *
 * @version 2025.12.03
 */

(function() {
  'use strict';

  // ============================================
  // KONFIGURACE
  // ============================================
  const SW_PATH = '/sw.php'; // Dynamicky generovaný SW
  const RELOAD_COOLDOWN_MS = 60000; // Min. 1 minuta mezi reloady
  const RELOAD_STORAGE_KEY = 'pwa_last_reload';
  const VERSION_STORAGE_KEY = 'pwa_known_version';
  const UPDATE_CHECK_INTERVAL_DEFAULT = 300000; // 5 minut
  const UPDATE_CHECK_INTERVAL_IOS_PWA = 60000; // 1 minuta pro iOS PWA

  // ============================================
  // DETEKCE PROSTŘEDÍ
  // ============================================
  const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
  const isPWA = window.matchMedia('(display-mode: standalone)').matches ||
                window.navigator.standalone === true;
  const UPDATE_CHECK_INTERVAL = (isIOS && isPWA)
    ? UPDATE_CHECK_INTERVAL_IOS_PWA
    : UPDATE_CHECK_INTERVAL_DEFAULT;

  // ============================================
  // STAV
  // ============================================
  let swRegistration = null;
  let refreshing = false;
  let currentSwVersion = null;

  // ============================================
  // OCHRANA PROTI RELOAD LOOP
  // ============================================
  function canReload() {
    const lastReload = parseInt(sessionStorage.getItem(RELOAD_STORAGE_KEY) || '0', 10);
    const now = Date.now();
    const elapsed = now - lastReload;

    if (elapsed < RELOAD_COOLDOWN_MS) {
      console.warn(`[PWA] Reload blokován - příliš brzy (${Math.round((RELOAD_COOLDOWN_MS - elapsed) / 1000)}s do dalšího povoleného)`);
      return false;
    }
    return true;
  }

  function markReload() {
    sessionStorage.setItem(RELOAD_STORAGE_KEY, Date.now().toString());
  }

  function safeReload() {
    if (!canReload()) {
      console.warn('[PWA] Reload přeskočen kvůli ochraně proti loop');
      return false;
    }
    markReload();
    console.log('[PWA] Bezpečný reload...');
    window.location.reload();
    return true;
  }

  // ============================================
  // TOAST NOTIFIKACE
  // ============================================
  function zobrazitUpdateToast(callback, delay = 2000) {
    // Kontrola jestli už toast neexistuje
    if (document.getElementById('pwa-update-toast')) {
      return;
    }

    const toast = document.createElement('div');
    toast.id = 'pwa-update-toast';
    toast.innerHTML = `
      <div style="
        position: fixed;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        background: #222;
        color: #fff;
        padding: 12px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        z-index: 10003;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 12px;
      ">
        <span style="width: 16px; height: 16px; border: 2px solid #fff; border-top-color: transparent; border-radius: 50%; animation: pwa-spin 1s linear infinite;"></span>
        <span>Aktualizuji aplikaci...</span>
      </div>
      <style>
        @keyframes pwa-spin {
          to { transform: rotate(360deg); }
        }
      </style>
    `;

    document.body.appendChild(toast);

    // Callback po delay (dát uživateli čas vidět toast)
    setTimeout(() => {
      if (callback) callback();
    }, delay);
  }

  function zobrazitAktualizacniBanner() {
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
        z-index: 10003;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        font-size: 14px;
        max-width: 90vw;
      ">
        <span>Je dostupna nova verze aplikace</span>
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

    document.getElementById('pwa-update-btn').addEventListener('click', () => {
      aktivovatNovouVerzi();
      banner.remove();
    });

    document.getElementById('pwa-dismiss-btn').addEventListener('click', () => {
      banner.remove();
    });
  }

  // ============================================
  // SW KOMUNIKACE
  // ============================================
  function aktivovatNovouVerzi() {
    if (swRegistration && swRegistration.waiting) {
      console.log('[PWA] Odesílám SKIP_WAITING');
      swRegistration.waiting.postMessage('SKIP_WAITING');
    }
  }

  async function pingSwVersion() {
    if (!navigator.serviceWorker.controller) {
      return null;
    }

    return new Promise((resolve) => {
      const timeout = setTimeout(() => {
        resolve(null);
      }, 3000);

      const messageHandler = (event) => {
        if (event.data?.type === 'PONG_VERSION') {
          clearTimeout(timeout);
          navigator.serviceWorker.removeEventListener('message', messageHandler);
          resolve(event.data.version);
        }
      };

      navigator.serviceWorker.addEventListener('message', messageHandler);

      navigator.serviceWorker.controller.postMessage({
        type: 'PING_VERSION',
        clientId: Math.random().toString(36).substr(2, 9)
      });
    });
  }

  async function zkontrolujAktualizaceTiche() {
    if (!swRegistration) return;

    try {
      await swRegistration.update();
    } catch (e) {
      // Tichá chyba - může být offline
    }
  }

  // ============================================
  // HLAVNÍ REGISTRACE
  // ============================================
  async function registrujServiceWorker() {
    try {
      swRegistration = await navigator.serviceWorker.register(SW_PATH, {
        scope: '/',
        updateViaCache: 'none' // KRITICKÉ: Vždy kontrolovat server
      });

      console.log('[PWA] Service Worker registrován:', swRegistration.scope);

      // Získat aktuální verzi
      setTimeout(async () => {
        currentSwVersion = await pingSwVersion();
        if (currentSwVersion) {
          console.log('[PWA] Aktuální SW verze:', currentSwVersion);
          localStorage.setItem(VERSION_STORAGE_KEY, currentSwVersion);
        }
      }, 1000);

      // Kontrolovat aktualizace při načtení
      await zkontrolujAktualizaceTiche();

      // Listener pro novou verzi
      swRegistration.addEventListener('updatefound', () => {
        const newWorker = swRegistration.installing;
        console.log('[PWA] Nalezena nová verze SW...');

        newWorker.addEventListener('statechange', () => {
          if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
            console.log('[PWA] Nová verze připravena k aktivaci');

            // Pro iOS PWA nebo při prvním načtení: automatická aktivace
            if (isIOS && isPWA) {
              console.log('[PWA] iOS PWA - automatická aktivace');
              aktivovatNovouVerzi();
            } else {
              zobrazitAktualizacniBanner();
            }
          }
        });
      });

      // Periodická kontrola
      setInterval(zkontrolujAktualizaceTiche, UPDATE_CHECK_INTERVAL);

    } catch (error) {
      console.error('[PWA] Chyba registrace SW:', error);
    }
  }

  // ============================================
  // EVENT LISTENERS
  // ============================================

  // Kontrola při návratu na stránku
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible' && swRegistration) {
      console.log('[PWA] Stránka viditelná - kontroluji aktualizace');
      zkontrolujAktualizaceTiche();
    }
  });

  // Kontrola při focus
  window.addEventListener('focus', () => {
    if (swRegistration) {
      zkontrolujAktualizaceTiche();
    }
  });

  // Reload při aktivaci nového SW
  navigator.serviceWorker.addEventListener('controllerchange', () => {
    if (refreshing) return;
    refreshing = true;

    console.log('[PWA] Nový SW aktivován');

    // Bezpečný reload s toast notifikací
    zobrazitUpdateToast(() => {
      safeReload();
    }, 1500);
  });

  // Zprávy od SW
  navigator.serviceWorker.addEventListener('message', (event) => {
    if (event.data?.type === 'SW_ACTIVATED') {
      console.log('[PWA] SW aktivován, verze:', event.data.version);
      currentSwVersion = event.data.version;
      localStorage.setItem(VERSION_STORAGE_KEY, event.data.version);
    }

    if (event.data?.type === 'CACHE_INFO') {
      console.log('[PWA] Cache info:', event.data);
    }
  });

  // ============================================
  // INIT
  // ============================================
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', registrujServiceWorker);
  } else {
    console.log('[PWA] Service Worker není podporován');
  }

  // ============================================
  // GLOBÁLNÍ UTILITY FUNKCE
  // ============================================

  /**
   * Manuální kontrola aktualizací
   */
  window.zkontrolujAktualizace = async function() {
    if (!swRegistration) {
      console.log('[PWA] SW není registrován');
      return;
    }
    console.log('[PWA] Kontroluji aktualizace...');
    await swRegistration.update();
    const version = await pingSwVersion();
    console.log('[PWA] Aktuální verze:', version);
  };

  /**
   * Vynutit reload (s ochranou proti loop)
   */
  window.vynutitReload = function() {
    return safeReload();
  };

  /**
   * Vynutit kompletní aktualizaci (smaže cache + odregistruje SW + reload)
   */
  window.vynutitAktualizaci = async function() {
    console.log('[PWA] Vynucuji kompletní aktualizaci...');

    // Smazat cache
    const cacheNames = await caches.keys();
    await Promise.all(cacheNames.map(name => caches.delete(name)));
    console.log('[PWA] Cache smazán');

    // Odregistrovat SW
    const registrations = await navigator.serviceWorker.getRegistrations();
    await Promise.all(registrations.map(reg => reg.unregister()));
    console.log('[PWA] SW odregistrován');

    // Vyčistit storage
    sessionStorage.removeItem(RELOAD_STORAGE_KEY);
    localStorage.removeItem(VERSION_STORAGE_KEY);

    // Reload
    window.location.reload(true);
  };

  /**
   * Získat info o SW
   */
  window.getSwInfo = async function() {
    const version = await pingSwVersion();
    const cacheNames = await caches.keys();

    return {
      version: version,
      registration: swRegistration ? 'OK' : 'NONE',
      caches: cacheNames,
      isIOS: isIOS,
      isPWA: isPWA,
      knownVersion: localStorage.getItem(VERSION_STORAGE_KEY)
    };
  };

})();
