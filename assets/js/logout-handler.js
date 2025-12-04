/**
 * LOGOUT HANDLER - CSRF Protected Logout
 *
 * SECURITY FIX: Chrání logout proti force-logout útokům
 * Odchytí všechny logout odkazy a pošle POST request s CSRF tokenem
 */

(function() {
  'use strict';

  /**
   * Odešle logout request s CSRF tokenem
   */
  async function handleLogout(event) {
    event.preventDefault();

    try {
      // Získat CSRF token z existujícího inputu nebo ze session
      let csrfToken = null;

      // 1. Zkusit najít token ve stránce
      const tokenInput = document.querySelector('input[name="csrf_token"]');
      if (tokenInput && tokenInput.value) {
        csrfToken = tokenInput.value;
      }

      // 2. Pokud není, zkusit fetch z API (s timeoutem)
      if (!csrfToken) {
        try {
          // OPRAVA: Přidat timeout 5 sekund pro fetch
          const controller = new AbortController();
          const timeoutId = setTimeout(() => controller.abort(), 5000);

          const response = await fetch('/app/controllers/get_csrf_token.php', {
            credentials: 'same-origin',
            signal: controller.signal
          });

          clearTimeout(timeoutId);

          const data = await response.json();
          if ((data.status === 'success' || data.success === true) && data.token) {
            csrfToken = data.token;
          }
        } catch (error) {
          console.error('CSRF token fetch failed:', error);
          // Pokud fetch selhal, zobrazit varování
          if (error.name === 'AbortError') {
            console.error('CSRF token fetch timeout (5s)');
          }
        }
      }

      if (!csrfToken) {
        wgsToast.error('Nepodařilo se získat bezpečnostní token. Obnovte stránku a zkuste to znovu.');
        // Fallback: přesměrovat na potvrzovací logout stránku (bezpečnější než neudělat nic)
        window.location.href = '/logout.php';
        return;
      }

      // Odeslat POST request na logout.php
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = '/logout.php';

      const tokenField = document.createElement('input');
      tokenField.type = 'hidden';
      tokenField.name = 'csrf_token';
      tokenField.value = csrfToken;

      form.appendChild(tokenField);
      document.body.appendChild(form);
      form.submit();

  } catch (error) {
    console.error('Logout error:', error);
    wgsToast.error('Chyba při odhlašování. Zkuste to prosím znovu.');
    // Fallback pro nečekané chyby - zobrazit potvrzovací stránku
    window.location.href = '/logout.php';
  }
}

  /**
   * Inicializace logout handlerů
   */
  function initLogoutHandlers() {
    // Najít všechny logout odkazy (relativní i absolutní cesty)
    const logoutSelectors = [
      'a[href="logout.php"]',
      'a[href="/logout.php"]',
      'a.logout-link',
      'a.hamburger-logout',
      '#logoutBtn',
      '#logoutBtnDesktop'
    ];

    let logoutLinks = [];
    try {
      logoutLinks = document.querySelectorAll(logoutSelectors.join(', '));
    } catch (selectorError) {
      console.error('Logout handler selector error:', selectorError);
      // Bezpečný fallback: zajistit alespoň ID selektory, aby logout fungoval
      logoutLinks = document.querySelectorAll('#logoutBtn, #logoutBtnDesktop');
    }

    logoutLinks.forEach(link => {
      link.addEventListener('click', handleLogout);
    });
  }

  // Inicializovat po načtení DOM
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLogoutHandlers);
  } else {
    initLogoutHandlers();
  }

  // Globální funkce pro manuální logout (pro admin panel JS)
  window.secureLogout = handleLogout;

})();
