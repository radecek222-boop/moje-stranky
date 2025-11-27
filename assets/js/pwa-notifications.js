/**
 * PWA Notifications Manager
 * Badge na ikone + lokalni notifikace pro nove poznamky
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

  /**
   * Inicializace notifikacniho systemu
   */
  async function init() {
    console.log('[Notifikace] Inicializace...');

    // Zkontrolovat podporu
    if (!('Notification' in window)) {
      console.log('[Notifikace] Notification API neni podporovano');
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
   */
  function zobrazitNotifikaci(titulek, text, data = {}) {
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
        // Pokud mame claim_id, otevrit detail
        if (data.claim_id) {
          window.location.href = '/seznam.php?highlight=' + data.claim_id;
        }
      };

      console.log('[Notifikace] Notifikace zobrazena:', titulek);
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

  // Exportovat funkce pro globalni pouziti
  window.WGSNotifikace = {
    init: init,
    pozadatOPovoleni: pozadatOPovoleni,
    nastavitBadge: nastavitBadge,
    zobrazitNotifikaci: zobrazitNotifikaci,
    aktualizovat: aktualizovatPocetNeprectenych,
    zobrazitDialogPovoleni: zobrazitDialogPovoleni,
    getTotal: () => totalUnread
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
