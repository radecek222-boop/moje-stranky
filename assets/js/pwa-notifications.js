/**
 * PWA Notifications Manager
 * Badge na ikone + lokalni notifikace pro nove poznamky
 * Podpora pro iOS 16.4+ Web Push
 */

(function() {
  'use strict';

  // Konfigurace
  const CONFIG = {
    pollingInterval: 30000,  // 30 sekund
    apiEndpoint: '/api/notes_api.php?action=get_unread_counts',
    pushApiEndpoint: '/api/push_subscription_api.php',
    notificationIcon: '/icon192.png',
    notificationTag: 'wgs-notes'
  };

  // Stav
  let lastUnreadCounts = {};
  let totalUnread = 0;
  let pollingTimer = null;
  let notificationPermission = 'default';
  let swRegistration = null;
  let pushSubscription = null;
  let vapidPublicKey = null;

  // ========================================
  // DETEKCE PLATFORMY
  // ========================================

  // Detekce iOS
  const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
  const isIOSSafari = isIOS && /Safari/.test(navigator.userAgent) && !/CriOS|FxiOS/.test(navigator.userAgent);

  // Detekce PWA (spuštěno z plochy)
  const isPWA = window.matchMedia('(display-mode: standalone)').matches ||
                window.navigator.standalone === true;

  // iOS verze (16.4+ podporuje Web Push v PWA)
  function getIOSVersion() {
    if (!isIOS) return 0;
    const match = navigator.userAgent.match(/OS (\d+)_(\d+)/);
    if (match) {
      return parseFloat(match[1] + '.' + match[2]);
    }
    return 0;
  }
  const iosVersion = getIOSVersion();
  const iosSupportsWebPush = isIOS && iosVersion >= 16.4 && isPWA;

  /**
   * Inicializace notifikacniho systemu
   */
  async function init() {
    console.log('[Notifikace] Inicializace...');
    console.log('[Notifikace] iOS:', isIOS, 'verze:', iosVersion, 'PWA:', isPWA);

    // Registrace Service Worker
    if ('serviceWorker' in navigator) {
      try {
        swRegistration = await navigator.serviceWorker.ready;
        console.log('[Notifikace] Service Worker připraven');

        // Zkontrolovat existujici push subscription
        if ('PushManager' in window) {
          pushSubscription = await swRegistration.pushManager.getSubscription();
          if (pushSubscription) {
            console.log('[Notifikace] Existujici push subscription nalezena');
          }
        }
      } catch (e) {
        console.log('[Notifikace] Service Worker není dostupný:', e);
      }
    }

    // Zkontrolovat podporu notifikací
    if (!('Notification' in window)) {
      console.log('[Notifikace] Notification API neni podporovano');

      // Na iOS v prohlížeči (ne PWA) zobrazit návod
      if (isIOS && !isPWA) {
        setTimeout(() => zobrazitIOSNavod(), 3000);
      }
    } else {
      notificationPermission = Notification.permission;
      console.log('[Notifikace] Aktualni povoleni:', notificationPermission);

      // Pokud jsou povoleny a mame SW, registrovat push
      if (notificationPermission === 'granted' && swRegistration && !pushSubscription) {
        await registrovatPushSubscription();
      }
    }

    // Nacist pocatecni stav
    await aktualizovatPocetNeprectenych();

    // Spustit polling
    spustitPolling();

    // Listener pro viditelnost stranky
    document.addEventListener('visibilitychange', onVisibilityChange);

    console.log('[Notifikace] Inicializace dokoncena');
  }

  // ========================================
  // WEB PUSH SUBSCRIPTION
  // ========================================

  /**
   * Nacist VAPID public key ze serveru
   */
  async function nacistVapidKey() {
    if (vapidPublicKey) return vapidPublicKey;

    try {
      const response = await fetch(CONFIG.pushApiEndpoint + '?action=vapid-key');
      const data = await response.json();

      // API vraci vapidPublicKey primo v odpovedi (ne v data.data)
      if (data.status === 'success' && data.vapidPublicKey) {
        vapidPublicKey = data.vapidPublicKey;
        console.log('[Notifikace] VAPID key nacten');
        return vapidPublicKey;
      } else if (data.status === 'error') {
        console.log('[Notifikace] VAPID key: ' + (data.message || 'neni nakonfigurovany'));
        return null;
      }
    } catch (e) {
      console.error('[Notifikace] Chyba pri nacitani VAPID key:', e);
    }

    return null;
  }

  /**
   * Konverze VAPID key z base64url na Uint8Array
   */
  function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding)
      .replace(/\-/g, '+')
      .replace(/_/g, '/');

    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; ++i) {
      outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
  }

  /**
   * Registrovat push subscription na serveru
   */
  async function registrovatPushSubscription() {
    if (!swRegistration || !('PushManager' in window)) {
      console.log('[Notifikace] PushManager neni podporovan');
      return false;
    }

    try {
      // Nacist VAPID key
      const vapidKey = await nacistVapidKey();
      if (!vapidKey) {
        console.log('[Notifikace] VAPID key nenacten - push neni nakonfigurovany');
        return false;
      }

      // Vytvorit subscription
      pushSubscription = await swRegistration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(vapidKey)
      });

      console.log('[Notifikace] Push subscription vytvorena');

      // Odeslat na server
      const ulozeno = await ulozitSubscriptionNaServer(pushSubscription);
      if (ulozeno) {
        console.log('[Notifikace] Subscription ulozena na serveru');
        return true;
      }

    } catch (e) {
      console.error('[Notifikace] Chyba pri registraci push:', e);

      // Pokud je permission denied, nezobrazovat jako chybu
      if (e.name === 'NotAllowedError') {
        console.log('[Notifikace] Uzivatel zamitnul push notifikace');
      }
    }

    return false;
  }

  /**
   * Ulozit subscription na server
   */
  async function ulozitSubscriptionNaServer(subscription) {
    try {
      // Ziskat CSRF token
      const csrfInput = document.querySelector('input[name="csrf_token"]');
      let csrfToken = csrfInput ? csrfInput.value : '';

      if (!csrfToken) {
        // Zkusit nacist CSRF z API
        const csrfResponse = await fetch('/app/controllers/get_csrf_token.php');
        const csrfData = await csrfResponse.json();
        if (csrfData.token) {
          csrfToken = csrfData.token;
        }
      }

      const formData = new FormData();
      formData.append('action', 'subscribe');
      formData.append('csrf_token', csrfToken);
      formData.append('subscription', JSON.stringify(subscription));
      formData.append('platforma', detekujPlatformu());

      const response = await fetch(CONFIG.pushApiEndpoint, {
        method: 'POST',
        body: formData
      });

      const data = await response.json();
      return data.status === 'success';

    } catch (e) {
      console.error('[Notifikace] Chyba pri ukladani subscription:', e);
      return false;
    }
  }

  /**
   * Zrusit push subscription
   */
  async function zrusitPushSubscription() {
    if (!pushSubscription) return true;

    try {
      const endpoint = pushSubscription.endpoint;

      // Zrusit lokalne
      await pushSubscription.unsubscribe();
      pushSubscription = null;

      // Zrusit na serveru
      const csrfInput = document.querySelector('input[name="csrf_token"]');
      const csrfToken = csrfInput ? csrfInput.value : '';

      const formData = new FormData();
      formData.append('action', 'unsubscribe');
      formData.append('csrf_token', csrfToken);
      formData.append('endpoint', endpoint);

      await fetch(CONFIG.pushApiEndpoint, {
        method: 'POST',
        body: formData
      });

      console.log('[Notifikace] Push subscription zrusena');
      return true;

    } catch (e) {
      console.error('[Notifikace] Chyba pri ruseni subscription:', e);
      return false;
    }
  }

  /**
   * Detekovat platformu
   */
  function detekujPlatformu() {
    if (isIOS) return 'ios';
    if (/Android/.test(navigator.userAgent)) return 'android';
    return 'desktop';
  }

  /**
   * Pozadat o povoleni notifikaci
   */
  async function pozadatOPovoleni() {
    if (!('Notification' in window)) {
      console.log('[Notifikace] Notification API neni podporovano');

      // Na iOS bez PWA nabídnout návod
      if (isIOS && !isPWA) {
        zobrazitIOSNavod();
      }
      return false;
    }

    if (Notification.permission === 'granted') {
      notificationPermission = 'granted';

      // Registrovat push pokud jeste neni
      if (!pushSubscription && swRegistration) {
        await registrovatPushSubscription();
      }
      return true;
    }

    if (Notification.permission === 'denied') {
      console.log('[Notifikace] Notifikace jsou zablokovane');
      return false;
    }

    try {
      const permission = await Notification.requestPermission();
      notificationPermission = permission;
      console.log('[Notifikace] Uzivatel odpoveděl:', permission);

      // Po povoleni registrovat push
      if (permission === 'granted' && swRegistration) {
        await registrovatPushSubscription();
      }

      return permission === 'granted';
    } catch (e) {
      console.error('[Notifikace] Chyba pri zadosti o povoleni:', e);
      return false;
    }
  }

  /**
   * Nastavit badge na ikone PWA
   */
  async function nastavitBadge(pocet) {
    if (!('setAppBadge' in navigator)) {
      // Fallback pro starsi prohlizece - nic nedelat
      return;
    }

    try {
      if (pocet > 0) {
        await navigator.setAppBadge(pocet);
        console.log('[Notifikace] Badge nastaven na:', pocet);
      } else {
        await navigator.clearAppBadge();
        console.log('[Notifikace] Badge smazan');
      }
    } catch (e) {
      console.error('[Notifikace] Chyba pri nastaveni badge:', e);
    }
  }

  /**
   * Zobrazit lokalni notifikaci
   * Na iOS PWA pouziva ServiceWorker, jinak klasicky Notification API
   */
  async function zobrazitNotifikaci(titulek, text, data = {}) {
    if (notificationPermission !== 'granted') {
      console.log('[Notifikace] Notifikace nejsou povoleny');
      return;
    }

    // Nezobrazovat kdyz je stranka videt
    if (document.visibilityState === 'visible') {
      console.log('[Notifikace] Stranka je viditelna, preskakuji notifikaci');
      return;
    }

    try {
      // Na iOS PWA preferujeme ServiceWorker showNotification
      if (swRegistration && (iosSupportsWebPush || isPWA)) {
        await swRegistration.showNotification(titulek, {
          body: text,
          icon: CONFIG.notificationIcon,
          badge: CONFIG.notificationIcon,
          tag: CONFIG.notificationTag,
          vibrate: [200, 100, 200],
          data: data,
          requireInteraction: false
        });
        console.log('[Notifikace] SW notifikace zobrazena:', titulek);
      } else {
        // Fallback na klasický Notification API
        const notification = new Notification(titulek, {
          body: text,
          icon: CONFIG.notificationIcon,
          tag: CONFIG.notificationTag,
          badge: CONFIG.notificationIcon,
          vibrate: [200, 100, 200],
          data: data,
          requireInteraction: false
        });

        notification.onclick = function() {
          window.focus();
          this.close();
          if (data.claim_id) {
            window.location.href = '/seznam.php?highlight=' + data.claim_id;
          }
        };

        console.log('[Notifikace] Notifikace zobrazena:', titulek);
      }
    } catch (e) {
      console.error('[Notifikace] Chyba pri zobrazeni notifikace:', e);
    }
  }

  /**
   * Aktualizovat pocet neprectenych a badge
   */
  async function aktualizovatPocetNeprectenych() {
    try {
      const response = await fetch(CONFIG.apiEndpoint);
      if (!response.ok) {
        throw new Error('HTTP ' + response.status);
      }

      const data = await response.json();
      if (data.status !== 'success') {
        throw new Error(data.error || 'Neznama chyba');
      }

      const newCounts = data.unread_counts || {};

      // Spocitat celkovy pocet
      const newTotal = Object.values(newCounts).reduce((sum, count) => sum + parseInt(count, 10), 0);

      // Zkontrolovat jestli pribyly nove poznamky
      const rozdil = newTotal - totalUnread;
      if (rozdil > 0 && totalUnread > 0) {
        // Najit ktere zakazky maji nove poznamky
        for (const [claimId, count] of Object.entries(newCounts)) {
          const oldCount = lastUnreadCounts[claimId] || 0;
          if (count > oldCount) {
            // Nova poznamka pro tuto zakazku
            zobrazitNotifikaci(
              'Nova poznamka',
              `Mate ${count} neprectenych poznamek`,
              { claim_id: claimId }
            );
            break; // Zobrazit jen jednu notifikaci
          }
        }
      }

      // Ulozit stav
      lastUnreadCounts = newCounts;
      totalUnread = newTotal;

      // Aktualizovat badge
      await nastavitBadge(newTotal);

      // Aktualizovat title stranky
      aktualizovatTitle(newTotal);

      return newTotal;

    } catch (e) {
      console.error('[Notifikace] Chyba pri nacitani neprectenych:', e);
      return totalUnread;
    }
  }

  /**
   * Aktualizovat title stranky s poctem neprectenych
   */
  function aktualizovatTitle(pocet) {
    const baseTitle = document.title.replace(/^\(\d+\)\s*/, '');
    if (pocet > 0) {
      document.title = `(${pocet}) ${baseTitle}`;
    } else {
      document.title = baseTitle;
    }
  }

  /**
   * Spustit polling
   */
  function spustitPolling() {
    if (pollingTimer) {
      clearInterval(pollingTimer);
    }

    pollingTimer = setInterval(async () => {
      await aktualizovatPocetNeprectenych();
    }, CONFIG.pollingInterval);

    console.log('[Notifikace] Polling spusten (interval:', CONFIG.pollingInterval, 'ms)');
  }

  /**
   * Zastavit polling
   */
  function zastavitPolling() {
    if (pollingTimer) {
      clearInterval(pollingTimer);
      pollingTimer = null;
      console.log('[Notifikace] Polling zastaven');
    }
  }

  /**
   * Handler pro zmenu viditelnosti stranky
   */
  function onVisibilityChange() {
    if (document.visibilityState === 'visible') {
      // Stranka je videt - okamzite aktualizovat
      aktualizovatPocetNeprectenych();
      spustitPolling();
    } else {
      // Stranka neni videt - polling pokracuje
    }
  }

  /**
   * Zobrazit dialog pro povoleni notifikaci
   */
  function zobrazitDialogPovoleni() {
    // Pokud uz je rozhodnuto, nic nezobrazovat
    if (notificationPermission !== 'default') {
      return;
    }

    // Zkontrolovat jestli uz byl dialog zobrazen
    if (localStorage.getItem('wgs_notif_asked') === 'true') {
      return;
    }

    // Vytvorit dialog
    const dialog = document.createElement('div');
    dialog.id = 'notif-permission-dialog';
    dialog.innerHTML = `
      <div style="
        position: fixed;
        bottom: 80px;
        left: 50%;
        transform: translateX(-50%);
        background: #fff;
        color: #222;
        padding: 20px 24px;
        border-radius: 12px;
        box-shadow: 0 4px 24px rgba(0,0,0,0.2);
        z-index: 99998;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        font-size: 14px;
        max-width: 320px;
        text-align: center;
        border: 1px solid #ddd;
      ">
        <div style="font-weight: 600; font-size: 16px; margin-bottom: 8px;">
          Chcete dostávat upozornění?
        </div>
        <div style="color: #666; margin-bottom: 16px; line-height: 1.4;">
          Budeme vás informovat o nových poznámkách k vašim zakázkám.
        </div>
        <div style="display: flex; gap: 10px; justify-content: center;">
          <button id="notif-allow-btn" style="
            background: #222;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
          ">Povolit</button>
          <button id="notif-deny-btn" style="
            background: #f5f5f5;
            color: #666;
            border: 1px solid #ddd;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
          ">Nyní ne</button>
        </div>
      </div>
    `;

    document.body.appendChild(dialog);

    // Tlacitko povolit
    document.getElementById('notif-allow-btn').addEventListener('click', async () => {
      dialog.remove();
      localStorage.setItem('wgs_notif_asked', 'true');
      await pozadatOPovoleni();
    });

    // Tlacitko odmitnout
    document.getElementById('notif-deny-btn').addEventListener('click', () => {
      dialog.remove();
      localStorage.setItem('wgs_notif_asked', 'true');
    });
  }

  /**
   * Zobrazit návod pro iOS uživatele - Přidání na plochu
   */
  function zobrazitIOSNavod() {
    // Nezobrazovat pokud už je PWA
    if (isPWA) return;

    // Nezobrazovat pokud už byl zobrazen
    if (localStorage.getItem('wgs_ios_guide_shown') === 'true') return;

    // Pouze na iOS Safari
    if (!isIOSSafari) return;

    const dialog = document.createElement('div');
    dialog.id = 'ios-install-guide';
    dialog.innerHTML = `
      <div style="
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background: #fff;
        color: #222;
        padding: 24px;
        box-shadow: 0 -4px 24px rgba(0,0,0,0.15);
        z-index: 99999;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        font-size: 14px;
        border-top: 1px solid #ddd;
      ">
        <button id="ios-guide-close" style="
          position: absolute;
          top: 12px;
          right: 12px;
          background: #f0f0f0;
          border: none;
          font-size: 24px;
          color: #333;
          cursor: pointer;
          padding: 8px 12px;
          line-height: 1;
          border-radius: 8px;
          min-width: 44px;
          min-height: 44px;
          touch-action: manipulation;
          -webkit-tap-highlight-color: transparent;
        ">×</button>

        <div style="font-weight: 700; font-size: 17px; margin-bottom: 12px;">
          Nainstalujte aplikaci WGS
        </div>

        <div style="color: #666; line-height: 1.5; margin-bottom: 16px;">
          Pro plnou funkčnost včetně notifikací přidejte aplikaci na plochu:
        </div>

        <div style="background: #f5f5f5; border-radius: 8px; padding: 16px;">
          <div style="display: flex; align-items: center; margin-bottom: 12px;">
            <span style="
              display: inline-flex;
              align-items: center;
              justify-content: center;
              width: 28px;
              height: 28px;
              background: #222;
              color: #fff;
              border-radius: 50%;
              font-weight: 700;
              font-size: 14px;
              margin-right: 12px;
            ">1</span>
            <span>Klepněte na tlačítko <strong>Sdílet</strong> (ikona se šipkou)</span>
          </div>

          <div style="display: flex; align-items: center; margin-bottom: 12px;">
            <span style="
              display: inline-flex;
              align-items: center;
              justify-content: center;
              width: 28px;
              height: 28px;
              background: #222;
              color: #fff;
              border-radius: 50%;
              font-weight: 700;
              font-size: 14px;
              margin-right: 12px;
            ">2</span>
            <span>Vyberte <strong>Přidat na plochu</strong></span>
          </div>

          <div style="display: flex; align-items: center;">
            <span style="
              display: inline-flex;
              align-items: center;
              justify-content: center;
              width: 28px;
              height: 28px;
              background: #222;
              color: #fff;
              border-radius: 50%;
              font-weight: 700;
              font-size: 14px;
              margin-right: 12px;
            ">3</span>
            <span>Potvrďte <strong>Přidat</strong></span>
          </div>
        </div>

        <div style="margin-top: 16px; text-align: center;">
          <button id="ios-guide-dismiss" style="
            background: #222;
            color: #fff;
            border: none;
            padding: 16px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
            min-height: 50px;
            touch-action: manipulation;
            -webkit-tap-highlight-color: transparent;
            -webkit-appearance: none;
          ">Rozumím</button>
        </div>
      </div>
    `;

    document.body.appendChild(dialog);

    const zavritDialog = (e) => {
      if (e) {
        e.preventDefault();
        e.stopPropagation();
      }
      dialog.remove();
      localStorage.setItem('wgs_ios_guide_shown', 'true');
    };

    // Pouzit timeout aby se elementy spravne renderovaly pred pridanim event listeneru
    setTimeout(() => {
      const closeBtn = document.getElementById('ios-guide-close');
      const dismissBtn = document.getElementById('ios-guide-dismiss');

      if (closeBtn) {
        closeBtn.addEventListener('click', zavritDialog, { passive: false });
        closeBtn.addEventListener('touchend', zavritDialog, { passive: false });
      }

      if (dismissBtn) {
        dismissBtn.addEventListener('click', zavritDialog, { passive: false });
        dismissBtn.addEventListener('touchend', zavritDialog, { passive: false });
      }
    }, 100);
  }

  // Exportovat funkce pro globalni pouziti
  window.WGSNotifikace = {
    init: init,
    pozadatOPovoleni: pozadatOPovoleni,
    nastavitBadge: nastavitBadge,
    zobrazitNotifikaci: zobrazitNotifikaci,
    aktualizovat: aktualizovatPocetNeprectenych,
    zobrazitDialogPovoleni: zobrazitDialogPovoleni,
    zobrazitIOSNavod: zobrazitIOSNavod,
    registrovatPush: registrovatPushSubscription,
    zrusitPush: zrusitPushSubscription,
    getTotal: () => totalUnread,
    getPushSubscription: () => pushSubscription,
    isIOS: isIOS,
    isPWA: isPWA,
    iosVersion: iosVersion,
    iosSupportsWebPush: iosSupportsWebPush
  };

  // Auto-init po nacteni stranky
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      setTimeout(init, 1000); // Pockat 1s po DOMContentLoaded
    });
  } else {
    setTimeout(init, 1000);
  }

})();
