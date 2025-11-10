(function () {
  'use strict';

  const SESSION_TIMEOUT_MESSAGE = 'Nepodařilo se načíst seznam uživatelů. Přihlaste se prosím znovu.';
  const LOGIN_REDIRECT = 'statistiky.php';

  function redirectToLogin() {
    window.location.href = `login.php?redirect=${encodeURIComponent(LOGIN_REDIRECT)}`;
  }

  function safeParseUsers(raw) {
    if (!raw) {
      return [];
    }

    try {
      const parsed = JSON.parse(raw);
      return Array.isArray(parsed) ? parsed : [];
    } catch (error) {
      console.warn('Statistiky: Nelze načíst data z localStorage', error);
      return [];
    }
  }

  function updateEmptyPlaceholders(message) {
    const targets = ['salesperson', 'technician'];
    targets.forEach((type) => {
      const displayEl = document.getElementById(`display-${type}`);
      const items = window.WGS?.multiSelect?.[type]?.items ?? [];

      if (displayEl && (!items || items.length === 0)) {
        displayEl.classList.add('empty');
        displayEl.textContent = message;
      }
    });
  }

  function shouldHandle(response) {
    return response && (response.status === 401 || response.status === 403);
  }

  function buildFallbackUsers() {
    const fallback = safeParseUsers(localStorage.getItem('wgsUsers'));
    if (window.WGS) {
      window.WGS.users = fallback;
    }

    if (!fallback.length) {
      updateEmptyPlaceholders('Žádné volby nejsou k dispozici');
    }

    return fallback;
  }

  if (typeof window.loadUsers !== 'function') {
    return;
  }

  window.loadUsers = async function patchedLoadUsers() {
    try {
      const response = await fetch('api/admin_api.php?action=list_users', {
        credentials: 'same-origin',
      });

      if (response.ok) {
        const result = await response.json();
        const users = Array.isArray(result?.data) ? result.data : [];

        if (window.WGS) {
          window.WGS.users = users;
        }

        if (!users.length) {
          updateEmptyPlaceholders('Žádné volby nejsou k dispozici');
        }

        return users;
      }

      if (shouldHandle(response)) {
        updateEmptyPlaceholders(SESSION_TIMEOUT_MESSAGE);
        setTimeout(redirectToLogin, 600);
        return [];
      }

      console.warn('Statistiky: Nepodařilo se načíst uživatele.', response.status);
    } catch (error) {
      console.error('Statistiky: Chyba při načítání uživatelů', error);
    }

    return buildFallbackUsers();
  };
})();
