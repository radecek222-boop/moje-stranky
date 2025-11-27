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
    notificationIcon: '/icon192.png',
    notificationTag: 'wgs-notes'
  };

  // Stav
  let lastUnreadCounts = {};
  let totalUnread = 0;
  let pollingTimer = null;
  let notificationPermission = 'default';
  let swRegistration = null;

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
    }

    // Nacist pocatecni stav
    await aktualizovatPocetNeprectenych();

    // Spustit polling
    spustitPolling();

    // Listener pro viditelnost stranky
    document.addEventListener('visibilitychange', onVisibilityChange);

    console.log('[Notifikace] Inicializace dokoncena');
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
          background: none;
          border: none;
          font-size: 24px;
          color: #999;
          cursor: pointer;
          padding: 4px;
          line-height: 1;
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
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            width: 100%;
          ">Rozumím</button>
        </div>
      </div>
    `;

    document.body.appendChild(dialog);

    const zavritDialog = () => {
      dialog.remove();
      localStorage.setItem('wgs_ios_guide_shown', 'true');
    };

    document.getElementById('ios-guide-close').addEventListener('click', zavritDialog);
    document.getElementById('ios-guide-dismiss').addEventListener('click', zavritDialog);
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
    getTotal: () => totalUnread,
    isIOS: isIOS,
    isPWA: isPWA,
    iosVersion: iosVersion
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
