/**
 * ADMIN PANEL - Tab management & core functionality
 */

// ============================================================
// SAFE LOGGER WRAPPER (for Safari compatibility)
// ============================================================
const safeLogger = {
  log: (...args) => typeof logger !== 'undefined' ? logger.log(...args) : console.log(...args),
  error: (...args) => typeof logger !== 'undefined' ? logger.error(...args) : console.error(...args),
  warn: (...args) => typeof logger !== 'undefined' ? logger.warn(...args) : console.warn(...args)
};

// ============================================================
// CSRF TOKEN - poskytuje csrf-auto-inject.js
// ============================================================

const SESSION_EXPIRED_MESSAGE = () => t('session_expired');

function isUnauthorizedStatus(status) {
  return status === 401 || status === 403;
}

function redirectToLogin(redirectTarget = '') {
  const query = redirectTarget ? `?redirect=${encodeURIComponent(redirectTarget)}` : '';
  window.location.href = `login.php${query}`;
}

// ============================================================
// TAB MANAGEMENT
// ============================================================
function initAdminPanel() {
  safeLogger.log('Admin panel initialized');
  setupNavigation();
  initUserManagement();
}

// Inicializace - podpora pro defer i normální načítání
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initAdminPanel);
} else {
  initAdminPanel();
}

safeLogger.log('admin.js loaded');

// ============================================================
// GLOBAL ERROR HANDLER
// ============================================================
window.addEventListener('error', (event) => {
  // Nelogovat externí skripty (např. Google Analytics)
  if (event.filename && !event.filename.includes(window.location.origin)) {
    return;
  }

  const error = {
    message: event.message,
    lineno: event.lineno,
    colno: event.colno,
    stack: event.error ? event.error.stack : ''
  };

  logClientError(error, 'global-error-handler');
});

window.addEventListener('unhandledrejection', (event) => {
  const error = {
    message: event.reason instanceof Error ? event.reason.message : String(event.reason),
    stack: event.reason instanceof Error ? event.reason.stack : ''
  };

  logClientError(error, 'unhandled-promise-rejection');
});

// ============================================================
// NAVIGATION - data-navigate buttons
// ============================================================
function setupNavigation() {
  document.querySelectorAll('[data-navigate]').forEach(button => {
    button.addEventListener('click', (e) => {
      e.preventDefault();
      const url = button.getAttribute('data-navigate');
      if (url) {
        logger.log('[Sync] Navigating to:', url);
        window.location.href = url;
      }
    });
  });
  logger.log('Navigation setup complete');
}

// ============================================================
// DASHBOARD STATISTICS
// ============================================================
async function loadDashboard() {
  try {
    // Kontrola existence elementů - pokud statistiky neexistují, nic nedělat
    const statClaims = document.getElementById('stat-claims');
    const statUsers = document.getElementById('stat-users');
    const statOnline = document.getElementById('stat-online');
    const statKeys = document.getElementById('stat-keys');

    if (!statClaims || !statUsers || !statOnline || !statKeys) {
      // Dashboard statistiky neexistují, skip
      return;
    }

    const response = await fetch('api/admin_stats_api.php', {
      credentials: 'same-origin'
    });
    if (!response.ok) {
      if (isUnauthorizedStatus(response.status)) {
        ['stat-claims', 'stat-users', 'stat-online', 'stat-keys'].forEach((id) => {
          const el = document.getElementById(id);
          if (el) {
            el.textContent = '—';
          }
        });
        setTimeout(() => redirectToLogin('admin.php'), 800);
        return;
      }

      throw new Error(`HTTP ${response.status}`);
    }

    const data = await response.json();

    if (data.status === 'success' || data.success === true) {
      if (statClaims) statClaims.textContent = data.stats.claims || 0;
      if (statUsers) statUsers.textContent = data.stats.users || 0;
      if (statOnline) statOnline.textContent = data.stats.online || 0;
      if (statKeys) statKeys.textContent = data.stats.keys || 0;
    }
  } catch (error) {
    logger.error('Dashboard load error:', error);
    logClientError(error, 'loadDashboard');
  }
}

// ============================================================
// USERS MANAGEMENT
// ============================================================
async function loadUsers() {
  const tbody = document.getElementById('users-table');
  if (!tbody) return;

  try {
    tbody.innerHTML = '<tr><td colspan="7" class="loading">Načítání...</td></tr>';

    const response = await fetch('/api/admin_users_api.php?action=list', {
      credentials: 'same-origin'
    });
    if (!response.ok) {
      if (isUnauthorizedStatus(response.status)) {
        tbody.innerHTML = `<tr><td colspan="7" class="error-message">${SESSION_EXPIRED_MESSAGE}</td></tr>`;
        setTimeout(() => redirectToLogin('admin.php?tab=users'), 800);
        return;
      }

      throw new Error(`HTTP ${response.status}`);
    }

    const data = await response.json();

    if (data.status === 'success' || data.success === true) {
      // API returns data.data (not data.users) - support both formats
      const users = data.data || data.users || [];

      if (users.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#999;">Žádní uživatelé</td></tr>';
        return;
      }

      let html = '';
      users.forEach(user => {
        const statusClass = user.status === 'active' ? 'badge-active' : 'badge-inactive';
        const statusText = user.status === 'active' ? 'Aktivní' : 'Neaktivní';
        const createdDate = new Date(user.created_at).toLocaleDateString('cs-CZ');

        // data-action handler pro zobrazení detailu
        html += '<tr style="cursor: pointer;" data-action="zobrazDetailUzivatele" data-id="' + user.id + '" title="Klikněte pro zobrazení detailu">';
        html += '<td>#' + user.id + '</td>';
        html += '<td>' + escapeHtml(user.name || user.full_name) + '</td>'; // API returns 'name' not 'full_name'
        html += '<td>' + escapeHtml(user.email) + '</td>';
        html += '<td>' + escapeHtml(user.role.toUpperCase()) + '</td>';
        html += '<td><span class="badge ' + statusClass + '">' + statusText.toUpperCase() + '</span></td>';
        html += '<td>' + createdDate + '</td>';
        html += '<td data-action="stopPropagation">';
        html += '<button class="btn btn-sm btn-danger" data-action="deleteUser" data-id="' + user.id + '">Smazat</button>';
        html += '</td>';
        html += '</tr>';
      });
      tbody.innerHTML = html;
    } else {
      tbody.innerHTML = `<tr><td colspan="7" class="error-message">${data.message || 'Nepodařilo se načíst uživatele.'}</td></tr>`;
    }
  } catch (error) {
    tbody.innerHTML = '<tr><td colspan="7" class="error-message">Chyba načítání</td></tr>';
    logger.error('Users load error:', error);
    logClientError(error, 'loadUsers');
  }
}

// ============================================================
// LOAD ZÁKAZNÍCI - Seznam zákazníků
// ============================================================
async function loadZakaznici() {
  const tbody = document.getElementById('zakaznici-table');
  if (!tbody) return;

  try {
    tbody.innerHTML = '<tr><td colspan="6" class="loading">Načítání...</td></tr>';

    // Vyhledávací parametr (pokud existuje)
    const searchInput = document.getElementById('search-zakaznici');
    const searchQuery = searchInput ? searchInput.value.trim() : '';
    const url = searchQuery
      ? `/api/zakaznici_api.php?action=list_zakaznici&search=${encodeURIComponent(searchQuery)}`
      : '/api/zakaznici_api.php?action=list_zakaznici';

    const response = await fetch(url, {
      credentials: 'same-origin'
    });

    if (!response.ok) {
      if (isUnauthorizedStatus(response.status)) {
        tbody.innerHTML = `<tr><td colspan="6" class="error-message">${SESSION_EXPIRED_MESSAGE()}</td></tr>`;
        setTimeout(() => redirectToLogin('admin.php?tab=zakaznici'), 800);
        return;
      }
      throw new Error(`HTTP ${response.status}`);
    }

    const data = await response.json();

    if (data.status === 'success') {
      const zakaznici = data.zakaznici || [];

      if (zakaznici.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#999;">Žádní zákazníci</td></tr>';
        return;
      }

      let html = '';
      zakaznici.forEach(zakaznik => {
        const jmeno = zakaznik.jmeno || '—';
        const adresa = zakaznik.adresa || '—';
        const telefon = zakaznik.telefon || '—';
        const email = zakaznik.email || '—';
        const pocetZakazek = zakaznik.pocet_zakazek || 0;

        // data-action handler - přesměrování na seznam.php s vyhledáváním
        html += `<tr style="cursor: pointer;" data-action="otevritZakazkyZakaznika" data-jmeno="${escapeHtml(jmeno)}" data-email="${escapeHtml(email)}" title="Klikněte pro zobrazení zakázek">`;
        html += `<td><strong>${escapeHtml(jmeno)}</strong></td>`;
        html += `<td>${escapeHtml(adresa)}</td>`;
        html += `<td>${escapeHtml(telefon)}</td>`;
        html += `<td>${escapeHtml(email)}</td>`;
        html += `<td><span class="badge badge-active">${pocetZakazek}</span></td>`;
        html += `<td><button class="btn btn-sm" data-action="otevritZakazkyZakaznika" data-jmeno="${escapeHtml(jmeno)}" data-email="${escapeHtml(email)}">Zobrazit zakázky</button></td>`;
        html += '</tr>';
      });

      tbody.innerHTML = html;
    } else {
      tbody.innerHTML = `<tr><td colspan="6" class="error-message">${data.message || 'Nepodařilo se načíst zákazníky.'}</td></tr>`;
    }
  } catch (error) {
    tbody.innerHTML = '<tr><td colspan="6" class="error-message">Chyba načítání</td></tr>';
    logger.error('Zakaznici load error:', error);
    logClientError(error, 'loadZakaznici');
  }
}

/**
 * Otevře seznam.php s filtrováním podle jména zákazníka
 */
function otevritZakazkyZakaznika(jmeno, email) {
  // Použít jméno nebo email jako vyhledávací dotaz
  const searchQuery = jmeno && jmeno !== '—' ? jmeno : email;
  window.location.href = `seznam.php?search=${encodeURIComponent(searchQuery)}`;
}

async function addUser() {
  const modal = document.getElementById('addUserModal');
  const errorDiv = document.getElementById('modal-error');
  errorDiv.classList.add('hidden');

  const name = document.getElementById('add-name').value.trim();
  const email = document.getElementById('add-email').value.trim();
  const phone = document.getElementById('add-phone').value.trim();
  const address = document.getElementById('add-address').value.trim();
  const role = document.getElementById('add-role').value;
  const password = document.getElementById('add-password').value;

  if (!name || !email || !password) {
    errorDiv.textContent = 'Jméno, email a heslo jsou povinné';
    errorDiv.classList.remove('hidden');
    return;
  }

  if (password.length < 8) {
    errorDiv.textContent = 'Heslo musí mít alespoň 8 znaků';
    errorDiv.classList.remove('hidden');
    return;
  }

  try {
    const csrfToken = await getCSRFToken();
    if (!csrfToken) {
      throw new Error('CSRF token not available');
    }

    const response = await fetch('/api/admin_users_api.php?action=add', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ name, email, phone, address, role, password, csrf_token: csrfToken })
    });

    if (!response.ok) {
      if (isUnauthorizedStatus(response.status)) {
        errorDiv.textContent = SESSION_EXPIRED_MESSAGE;
        errorDiv.classList.remove('hidden');
        setTimeout(() => redirectToLogin('admin.php?tab=users'), 800);
        return;
      }

      const message = await response.text();
      throw new Error(message || 'Nepodařilo se vytvořit uživatele');
    }

    const data = await response.json();

    if (data.status === 'success' || data.success === true) {
      modal.style.display = 'none';

      // Reset formuláře
      document.getElementById('add-name').value = '';
      document.getElementById('add-email').value = '';
      document.getElementById('add-phone').value = '';
      document.getElementById('add-address').value = '';
      document.getElementById('add-password').value = '';

      loadUsers();
    } else {
      errorDiv.textContent = data.message || 'Chyba při vytváření uživatele';
      errorDiv.classList.remove('hidden');
    }
  } catch (error) {
    errorDiv.textContent = 'Chyba při vytváření uživatele';
    errorDiv.classList.remove('hidden');
    logger.error('Add user error:', error);
    logClientError(error, 'addUser');
  }
}

async function deleteUser(userId) {
  if (!confirm('Opravdu smazat tohoto uživatele?')) return;

  try {
    const csrfToken = await getCSRFToken();
    if (!csrfToken) {
      throw new Error('CSRF token not available');
    }

    const response = await fetch('/api/admin_users_api.php?action=delete', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ user_id: userId, csrf_token: csrfToken })
    });

    if (!response.ok) {
      if (isUnauthorizedStatus(response.status)) {
        alert(SESSION_EXPIRED_MESSAGE());
        redirectToLogin('admin.php?tab=users');
        return;
      }

      const message = await response.text();
      throw new Error(message || 'Chyba při mazání uživatele');
    }

    const data = await response.json();

    if (data.status === 'success' || data.success === true) {
      loadUsers();
    } else {
      alert(data.message || t('delete_error'));
    }
  } catch (error) {
    alert(t('delete_user_error'));
    logger.error('Delete user error:', error);
    logClientError(error, 'deleteUser');
  }
}

// ============================================================
// ONLINE USERS
// ============================================================
async function loadOnline() {
  const tbody = document.getElementById('online-table');
  if (!tbody) return;

  try {
    tbody.innerHTML = '<tr><td colspan="5" class="loading">Načítání...</td></tr>';

    const response = await fetch('/api/admin_users_api.php?action=online', {
      credentials: 'same-origin'
    });
    if (!response.ok) {
      if (isUnauthorizedStatus(response.status)) {
        tbody.innerHTML = `<tr><td colspan="5" class="error-message">${SESSION_EXPIRED_MESSAGE}</td></tr>`;
        setTimeout(() => redirectToLogin('admin.php?tab=online'), 800);
        return;
      }

      throw new Error(`HTTP ${response.status}`);
    }

    const data = await response.json();

    if (data.status === 'success' || data.success === true) {
      if (data.users.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#999;">Nikdo online</td></tr>';
        return;
      }

      let html = '';
      data.users.forEach(user => {
        const lastActivity = new Date(user.last_activity);
        const minutesAgo = Math.floor((Date.now() - lastActivity.getTime()) / 60000);
        const timeText = minutesAgo === 0 ? 'Nyní' : minutesAgo + ' min';

        html += '<tr>';
        html += '<td><span class="badge badge-active">Online</span></td>';
        html += '<td>' + escapeHtml(user.name) + '</td>';
        html += '<td>' + escapeHtml(user.role) + '</td>';
        html += '<td>' + escapeHtml(user.email) + '</td>';
        html += '<td>' + timeText + '</td>';
        html += '</tr>';
      });
      tbody.innerHTML = html;
    } else {
      tbody.innerHTML = `<tr><td colspan="5" class="error-message">${data.message || 'Nepodařilo se načíst online uživatele.'}</td></tr>`;
    }
  } catch (error) {
    tbody.innerHTML = '<tr><td colspan="5" class="error-message">Chyba načítání</td></tr>';
    logger.error('Online load error:', error);
    logClientError(error, 'loadOnlineUsers');
  }
}

// ============================================================
// HELPER FUNCTIONS
// ============================================================
// escapeHtml přesunuto do utils.js (Step 107)
// Funkce je dostupná jako window.escapeHtml() nebo Utils.escapeHtml()

// ============================================================
// INIT USER MANAGEMENT
// ============================================================
function initUserManagement() {
  const addUserBtn = document.getElementById('addUserBtn');
  const refreshUsersBtn = document.getElementById('refreshUsersBtn');
  const submitUserBtn = document.getElementById('submitUserBtn');
  const closeModalBtn = document.getElementById('closeModalBtn');
  const cancelModalBtn = document.getElementById('cancelModalBtn');
  const refreshOnlineBtn = document.getElementById('refreshOnlineBtn');
  const refreshZakazniciBtn = document.getElementById('refreshZakazniciBtn');
  const searchZakazniciInput = document.getElementById('search-zakaznici');

  if (addUserBtn) {
    addUserBtn.addEventListener('click', () => {
      document.getElementById('addUserModal').style.display = 'flex';
    });
  }

  if (closeModalBtn) {
    closeModalBtn.addEventListener('click', () => {
      document.getElementById('addUserModal').style.display = 'none';
    });
  }

  if (cancelModalBtn) {
    cancelModalBtn.addEventListener('click', () => {
      document.getElementById('addUserModal').style.display = 'none';
    });
  }

  if (submitUserBtn) {
    submitUserBtn.addEventListener('click', addUser);
  }

  if (refreshUsersBtn) {
    refreshUsersBtn.addEventListener('click', loadUsers);
  }

  if (refreshOnlineBtn) {
    refreshOnlineBtn.addEventListener('click', loadOnline);
  }

  // Event listenery pro zákazníky
  if (refreshZakazniciBtn) {
    refreshZakazniciBtn.addEventListener('click', loadZakaznici);
  }

  if (searchZakazniciInput) {
    // Vyhledávání při psaní (debounce 500ms)
    let searchTimeout;
    searchZakazniciInput.addEventListener('input', () => {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => {
        loadZakaznici();
      }, 500);
    });
  }

  // Auto-load based on active tab
  const urlParams = new URLSearchParams(window.location.search);
  const tab = urlParams.get('tab');

  const hasDashboard = document.getElementById('tab-dashboard');
  const hasUsers = document.getElementById('tab-users');
  const hasOnline = document.getElementById('tab-online');
  const hasZakaznici = document.getElementById('tab-zakaznici');

  if ((!tab || tab === 'dashboard') && hasDashboard) {
    loadDashboard();
  } else if (tab === 'users' && hasUsers) {
    loadUsers();
  } else if (tab === 'online' && hasOnline) {
    loadOnline();
  } else if (tab === 'zakaznici' && hasZakaznici) {
    loadZakaznici();
  }
}

// Helper function to check if API response is successful
/**
 * IsSuccess
 */
function isSuccess(data) {
    return (data && (data.success === true || data.status === 'success'));
}

// Helper function to get CSRF token from meta tag
/**
 * GetCSRFToken
 */
function getCSRFToken() {
    // Zkusit nejprve aktuální dokument
    let metaTag = document.querySelector('meta[name="csrf-token"]');

    // Pokud jsme v iframe, zkusit parent window
    if (!metaTag && window.parent && window.parent !== window) {
        try {
            metaTag = window.parent.document.querySelector('meta[name="csrf-token"]');
        } catch (e) {
            // Cross-origin iframe - nemůžeme přistoupit k parent
            console.error('Cannot access parent CSRF token:', e);
        }
    }

    if (!metaTag) {
        console.error('CSRF token meta tag not found in document or parent');
        return null;
    }

    const token = metaTag.getAttribute('content');

    // Ujistit se že token je string
    const tokenStr = token ? String(token).trim() : null;

    if (!tokenStr) {
        console.error('CSRF token is empty');
    }

    return tokenStr;
}

/**
 * logClientError - Loguje chyby z klientského JavaScriptu na server
 * @param {Error|string} error - Chyba nebo chybová zpráva
 * @param {string} context - Kontext chyby (volitelný)
 */
async function logClientError(error, context = '') {
    try {
        const errorData = {
            message: error instanceof Error ? error.message : String(error),
            stack: error instanceof Error ? error.stack : '',
            url: window.location.href,
            line: error.lineno || 0,
            column: error.colno || 0,
            context: context,
            timestamp: new Date().toISOString()
        };

        // Získat CSRF token
        const csrfToken = getCSRFToken();
        if (!csrfToken) {
            console.error('Cannot log error: CSRF token not found');
            return;
        }

        // Odeslat na server
        const response = await fetch('/api/admin.php?action=log_client_error', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                ...errorData,
                csrf_token: csrfToken
            }),
            credentials: 'same-origin'
        });

        if (!response.ok) {
            console.error('Failed to log client error:', response.status);
        }
    } catch (logError) {
        // Pokud selže logování, nezpůsobovat další chyby
        console.error('Error logging failed:', logError);
    }
}

// Open modal with specific section
/**
 * OpenCCModal
 * Step 45: Migrace na Alpine.js - open/close/ESC/overlay click nyní řeší adminModal komponenta
 */
function openCCModal(section) {
    const modalBody = document.getElementById('adminModalBody');

    // Kontrola existence elementů
    if (!modalBody) {
        console.error('Modal body nenalezen');
        return;
    }

    // Show loading first
    modalBody.innerHTML = '<div class="cc-modal-loading"><div class="cc-modal-spinner"></div><div style="margin-top: 1rem;">Načítání...</div></div>';

    // Step 45: Zobrazit modal přes Alpine.js API
    if (window.adminModal && window.adminModal.open) {
        window.adminModal.open();
    } else {
        // Fallback pro zpětnou kompatibilitu
        const overlay = document.getElementById('adminOverlay');
        const modal = document.getElementById('adminModal');
        if (overlay) overlay.classList.add('active');
        if (modal) modal.classList.add('active');
        if (window.scrollLock) {
            window.scrollLock.enable('admin-modal');
        }
    }

    // Load section content
    switch(section) {
        case 'statistics':
            loadStatisticsModal();
            break;
        case 'analytics':
            loadAnalyticsModal();
            break;
        case 'keys':
            loadKeysModal();
            break;
        case 'users':
            loadUsersModal();
            break;
        case 'notifications':
            loadNotificationsModal();
            break;
        case 'claims':
            loadClaimsModal();
            break;
        case 'actions':
            loadActionsModal();
            break;
        case 'diagnostics':
            loadDiagnosticsModal();
            break;
        case 'console':
            loadConsoleModal();
            break;
        case 'testing':
            loadTestingModal();
            break;
        case 'appearance':
            loadAppearanceModal();
            break;
        case 'content':
            loadContentModal();
            break;
        case 'config':
            loadConfigModal();
            break;
    }
}

// Close modal
/**
 * CloseCCModal
 * Step 45: Migrace na Alpine.js
 */
function closeCCModal() {
    // Step 45: Zavřít modal přes Alpine.js API
    if (window.adminModal && window.adminModal.close) {
        window.adminModal.close();
    } else {
        // Fallback pro zpětnou kompatibilitu
        const overlay = document.getElementById('adminOverlay');
        const modal = document.getElementById('adminModal');
        if (overlay) overlay.classList.remove('active');
        if (modal) modal.classList.remove('active');
        if (window.scrollLock) {
            window.scrollLock.disable('admin-modal');
        }
    }
}

// Open SQL page in new tab (spolehlivé řešení bez blokování)
/**
 * OpenSQLPage
 */
function openSQLPage() {
    // Použít window.open() s okamžitým voláním z user action
    const newWindow = window.open('vsechny_tabulky.php', '_blank');

    // Fallback pokud byl pop-up blokován
    if (!newWindow || newWindow.closed || typeof newWindow.closed === 'undefined') {
        console.warn('Pop-up blokován, použiji location.href');
        window.location.href = 'vsechny_tabulky.php';
    }
}

// === MODAL LOADERS ===

/**
 * Helper pro přidání CSRF tokenu k embed URL
 */
async function resolveCSRFToken() {
    const tryReadMeta = (doc) => {
        if (!doc) return null;
        const meta = doc.querySelector('meta[name="csrf-token"]');
        return meta?.content?.trim() || null;
    };

    const directMetaToken = tryReadMeta(document);
    if (directMetaToken) {
        return directMetaToken;
    }

    if (window.parent && window.parent !== window) {
        try {
            const parentToken = tryReadMeta(window.parent.document);
            if (parentToken) {
                return parentToken;
            }
        } catch (err) {
            console.warn('[getEmbedUrlWithCSRF] Cannot access parent document for CSRF token:', err);
        }
    }

    if (typeof getCSRFToken === 'function') {
        try {
            const asyncToken = await getCSRFToken();
            if (asyncToken) {
                return asyncToken;
            }
        } catch (err) {
            console.warn('[getEmbedUrlWithCSRF] Async CSRF fetch failed:', err);
        }
    }

    return null;
}

async function getEmbedUrlWithCSRF(baseUrl) {
    const csrf = await resolveCSRFToken();
    if (!csrf) {
        console.warn('[getEmbedUrlWithCSRF] CSRF token unavailable, falling back to base URL');
        return baseUrl;
    }
    const separator = baseUrl.includes('?') ? '&' : '?';
    return `${baseUrl}${separator}csrf=${encodeURIComponent(csrf)}`;
}

/**
 * LoadStatisticsModal
 */
async function loadStatisticsModal() {
    const modalBody = document.getElementById('adminModalBody');
    if (!modalBody) {
        console.error('[loadStatisticsModal] adminModalBody element nenalezen');
        return;
    }
    try {
        const url = await getEmbedUrlWithCSRF('statistiky.php?embed=1');
        modalBody.innerHTML = `<div class="cc-iframe-container"><iframe src="${url}" sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-modals" title="Statistiky reklamací"></iframe></div>`;
    } catch (error) {
        console.error('[loadStatisticsModal] Failed to load iframe URL:', error);
        modalBody.innerHTML = '<div class="cc-modal-loading">Nepodařilo se načíst statistiky.</div>';
    }
}

/**
 * LoadAnalyticsModal
 */
async function loadAnalyticsModal() {
    const modalBody = document.getElementById('adminModalBody');
    if (!modalBody) {
        console.error('adminModalBody element nenalezen');
        return;
    }
    try {
        const url = await getEmbedUrlWithCSRF("analytics.php?embed=1");
        modalBody.innerHTML = `<div class="cc-iframe-container"><iframe src="${url}" sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-modals" title="Web Analytics"></iframe></div>`;
    } catch (error) {
        console.error('[loadAnalyticsModal] Failed to load iframe URL:', error);
        modalBody.innerHTML = '<div class="cc-modal-loading">Nepodařilo se načíst analytics.</div>';
    }
}

/**
 * LoadKeysModal
 */
function loadKeysModal() {
    const modalBody = document.getElementById('adminModalBody');
    if (!modalBody) {
        console.error('adminModalBody element nenalezen');
        return;
    }

    // Načíst kompletní Security centrum přes iframe
    modalBody.innerHTML = `
        <iframe
            src="/includes/admin_security.php?embed=1"
            style="width: 100%; height: 80vh; border: none; border-radius: 4px;"
            onload="console.log('Security centrum načteno')"
        ></iframe>
    `;
}

/**
 * LoadUsersModal
 */
function loadUsersModal() {
    const modalBody = document.getElementById('adminModalBody');
    if (!modalBody) {
        console.error('adminModalBody element nenalezen');
        return;
    }

    modalBody.innerHTML = `
        <div class="cc-actions">
            <input type="text" class="search-box" id="adminSearchUsers" placeholder="Hledat uživatele..." style="flex: 1; max-width: 300px;">
            <button class="btn btn-sm btn-success" data-action="navigateToAddUser">+ Přidat uživatele</button>
            <button class="btn btn-sm" data-action="loadUsersModal">Obnovit</button>
        </div>
        <div id="usersTableContainer">Načítání uživatelů...</div>
    `;

    // Load users
    fetch('api/admin_api.php?action=list_users')
        .then(r => {
            if (!r.ok) throw new Error(`HTTP ${r.status}`);
            return r.json();
        })
        .then(data => {
            const container = document.getElementById('usersTableContainer');
            const users = data.data || data.users || [];

            if (isSuccess(data) && users.length > 0) {
                let html = '<table class="cc-table"><thead><tr>';
                html += '<th>ID</th><th>Jméno</th><th>Email</th><th>Role</th><th>Status</th><th>Vytvořen</th></tr></thead><tbody>';

                users.forEach(user => {
                    // Escapování pro XSS ochranu
                    const safeName = typeof escapeHTML === 'function' ? escapeHTML(user.name || user.full_name || '') : (user.name || user.full_name || '');
                    const safeEmail = typeof escapeHTML === 'function' ? escapeHTML(user.email || '') : (user.email || '');
                    const safeRole = typeof escapeHTML === 'function' ? escapeHTML(user.role || '') : (user.role || '');

                    html += '<tr>';
                    html += `<td>#${parseInt(user.id) || 0}</td>`;
                    html += `<td>${safeName}</td>`;
                    html += `<td>${safeEmail}</td>`;
                    html += `<td><span class="badge badge-${safeRole}">${safeRole}</span></td>`;
                    html += `<td><span class="badge badge-${user.is_active ? 'active' : 'inactive'}">${user.is_active ? 'Aktivní' : 'Neaktivní'}</span></td>`;
                    html += `<td>${user.created_at ? new Date(user.created_at).toLocaleDateString('cs-CZ') : '—'}</td>`;
                    html += '</tr>';
                });

                html += '</tbody></table>';
                container.innerHTML = html;
            } else if (isSuccess(data)) {
                container.innerHTML = '<p style="color: var(--c-grey); text-align: center; padding: 2rem;">Žádní uživatelé</p>';
            } else {
                container.innerHTML = '<p style="color: var(--c-error); text-align: center; padding: 2rem;">Chyba načítání</p>';
            }
        })
        .catch(err => {
            console.error('[Control Center] Users load error:', err);
            document.getElementById('usersTableContainer').innerHTML = '<p style="color: var(--c-error); text-align: center; padding: 2rem;">Chyba načítání</p>';
        });
}

/**
 * LoadNotificationsModal
 */
async function loadNotificationsModal() {
    const modalBody = document.getElementById('adminModalBody');
    if (!modalBody) {
        console.error('adminModalBody element nenalezen');
        return;
    }
    const url = await getEmbedUrlWithCSRF("admin.php?tab=notifications&embed=1");
    modalBody.innerHTML = `<div class="cc-iframe-container"><iframe src="${url}" sandbox="allow-scripts allow-same-origin allow-forms" title="Email & SMS notifikace"></iframe></div>`;
}

/**
 * LoadClaimsModal
 */
function loadClaimsModal() {
    const modalBody = document.getElementById('adminModalBody');
    if (!modalBody) {
        console.error('adminModalBody element nenalezen');
        return;
    }

    // Načíst kompletní správu reklamací přes iframe
    // Cache-busting timestamp aby se vyhli cache problémům
    const timestamp = new Date().getTime();

    modalBody.innerHTML = `
        <iframe
            src="/includes/admin_reklamace_management.php?embed=1&filter=all&_t=${timestamp}"
            style="width: 100%; height: 80vh; border: none; border-radius: 4px;"
            onload="console.log('Správa reklamací načtena');"
            id="claimsIframe"
        ></iframe>
    `;

    // Původní starý kód pro statistiky (záloha - smazat pokud nový funguje)
    /*
    modalBody.innerHTML = \`
        <div class="cc-mini-stats">
            <div class="cc-mini-stat">
                <div class="cc-mini-stat-value" id="adminClaimsWait">-</div>
                <div class="cc-mini-stat-label">Čekající</div>
            </div>
            <div class="cc-mini-stat">
                <div class="cc-mini-stat-value" id="adminClaimsOpen">-</div>
                <div class="cc-mini-stat-label">Otevřené</div>
            </div>
            <div class="cc-mini-stat">
                <div class="cc-mini-stat-value" id="adminClaimsDone">-</div>
                <div class="cc-mini-stat-label">Dokončené</div>
            </div>
            <div class="cc-mini-stat">
                <div class="cc-mini-stat-value" id="adminClaimsTotal"><?= $totalClaims ?></div>
                <div class="cc-mini-stat-label">Celkem</div>
            </div>
        </div>
        <div class="cc-actions">
            <a href="seznam.php" class="btn btn-sm">Otevřít seznam reklamací</a>
            <a href="novareklamace.php" class="btn btn-sm btn-success">+ Nová reklamace</a>
        </div>
    \`;

    // Load claims stats
    fetch('api/admin_api.php?action=list_reklamace')
        .then(r => {
            if (!r.ok) throw new Error(\`HTTP \${r.status}\`);
            return r.json();
        })
        .then(data => {
            if (isSuccess(data) && data.reklamace) {
                const claims = data.reklamace;
                const wait = claims.filter(c => c.stav === 'ČEKÁ').length;
                const open = claims.filter(c => c.stav === 'DOMLUVENÁ').length;
                const done = claims.filter(c => c.stav === 'HOTOVO').length;

                document.getElementById('adminClaimsWait').textContent = wait;
                document.getElementById('adminClaimsOpen').textContent = open;
                document.getElementById('adminClaimsDone').textContent = done;
            }
        })
        .catch(err => {
            console.error('[Control Center] Claims stats load error:', err);
        });
    */
}

/**
 * LoadActionsModal
 */
async function loadActionsModal() {
    const modalBody = document.getElementById('adminModalBody');
    if (!modalBody) {
        console.error('adminModalBody element nenalezen');
        return;
    }
    const url = await getEmbedUrlWithCSRF("admin.php?tab=admin_actions&embed=1");
    modalBody.innerHTML = `<div class="cc-iframe-container"><iframe src="${url}" sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-modals" title="Akce & Úkoly"></iframe></div>`;
}

/**
 * LoadDiagnosticsModal
 */
async function loadDiagnosticsModal() {
    const modalBody = document.getElementById('adminModalBody');
    if (!modalBody) {
        console.error('adminModalBody element nenalezen');
        return;
    }
    try {
        const url = await getEmbedUrlWithCSRF("admin.php?tab=tools&embed=1");
        modalBody.innerHTML = `<div class="cc-iframe-container"><iframe src="${url}" sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-modals" title="Diagnostika systému"></iframe></div>`;
    } catch (error) {
        console.error('[loadDiagnosticsModal] Failed to load iframe URL:', error);
        modalBody.innerHTML = '<div class="cc-modal-loading">Nepodařilo se načíst diagnostiku.</div>';
    }
}

/**
 * LoadConsoleModal
 */
async function loadConsoleModal() {
    const modalBody = document.getElementById('adminModalBody');
    if (!modalBody) {
        console.error('adminModalBody element nenalezen');
        return;
    }
    const url = await getEmbedUrlWithCSRF("admin.php?tab=admin_console&embed=1");
    modalBody.innerHTML = `<div class="cc-iframe-container"><iframe src="${url}" sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-modals" title="Konzole - Developer Tools"></iframe></div>`;
}

/**
 * LoadTestingModal
 */
async function loadTestingModal() {
    const modalBody = document.getElementById('adminModalBody');
    if (!modalBody) {
        console.error('adminModalBody element nenalezen');
        return;
    }
    const url = await getEmbedUrlWithCSRF("admin.php?tab=admin_testing_interactive&embed=1");
    modalBody.innerHTML = `<div class="cc-iframe-container"><iframe src="${url}" sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-modals" title="Testovací prostředí"></iframe></div>`;
}

/**
 * LoadAppearanceModal
 */
async function loadAppearanceModal() {
    const modalBody = document.getElementById('adminModalBody');
    if (!modalBody) {
        console.error('adminModalBody element nenalezen');
        return;
    }
    const url = await getEmbedUrlWithCSRF("admin.php?tab=admin_appearance&embed=1");
    modalBody.innerHTML = `<div class="cc-iframe-container"><iframe src="${url}" sandbox="allow-scripts allow-same-origin allow-forms" title="Vzhled & Design"></iframe></div>`;
}

/**
 * LoadContentModal
 */
async function loadContentModal() {
    const modalBody = document.getElementById('adminModalBody');
    if (!modalBody) {
        console.error('adminModalBody element nenalezen');
        return;
    }
    const url = await getEmbedUrlWithCSRF("admin.php?tab=admin_content&embed=1");
    modalBody.innerHTML = `<div class="cc-iframe-container"><iframe src="${url}" sandbox="allow-scripts allow-same-origin allow-forms" title="Obsah & Texty"></iframe></div>`;
}

/**
 * LoadConfigModal
 */
async function loadConfigModal() {
    const modalBody = document.getElementById('adminModalBody');
    if (!modalBody) {
        console.error('adminModalBody element nenalezen');
        return;
    }
    const url = await getEmbedUrlWithCSRF("admin.php?tab=admin_configuration&embed=1");
    modalBody.innerHTML = `<div class="cc-iframe-container"><iframe src="${url}" sandbox="allow-scripts allow-same-origin allow-forms" title="Konfigurace systému"></iframe></div>`;
}

// === ACTION HANDLERS ===

/**
 * DeleteKey
 */
function deleteKey(keyCode) {
    if (!confirm('Opravdu chcete smazat tento klíč?')) return;

    const csrfToken = getCSRFToken();
    if (!csrfToken) {
        alert(t('csrf_token_not_found'));
        return;
    }

    fetch('api/admin_api.php?action=delete_key', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            key_code: keyCode,
            csrf_token: csrfToken
        })
    })
    .then(r => r.json())
    .then(data => {
        if (isSuccess(data)) {
            loadKeysModal(); // Reload
        } else {
            alert(t('error') + ': ' + (data.error || data.message || t('unknown_error')));
        }
    })
    .catch(err => {
        alert('Chyba: ' + err.message);
    });
}

/**
 * CreateKey - zobrazí modal pro výběr typu klíče
 */
function createKey() {
    // Vytvořit modal pro výběr typu klíče
    const existujiciModal = document.getElementById('createKeyModal');
    if (existujiciModal) existujiciModal.remove();

    const modal = document.createElement('div');
    modal.id = 'createKeyModal';
    modal.style.cssText = `
        position: fixed; top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.6); display: flex;
        align-items: center; justify-content: center; z-index: 10000;
    `;

    modal.innerHTML = `
        <div style="background: #1a1a1a; padding: 30px; border-radius: 12px;
                    max-width: 400px; width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.5);
                    border: 1px solid #333;">
            <h3 style="margin: 0 0 20px 0; color: #fff; font-size: 1.3rem;">
                Vytvořit nový registrační klíč
            </h3>

            <div style="margin-bottom: 20px;">
                <label style="display: flex; align-items: center; padding: 15px;
                              background: #252525; border-radius: 8px; cursor: pointer;
                              margin-bottom: 10px; border: 2px solid transparent;
                              transition: all 0.2s;"
                       onmouseover="this.style.borderColor='#555'"
                       onmouseout="this.style.borderColor=this.querySelector('input').checked ? '#fff' : 'transparent'">
                    <input type="radio" name="keyType" value="technik"
                           style="width: 20px; height: 20px; margin-right: 15px; accent-color: #fff;">
                    <div>
                        <div style="color: #fff; font-weight: 600; font-size: 1.1rem;">TECHNIK</div>
                        <div style="color: #888; font-size: 0.85rem;">Pro servisní techniky</div>
                    </div>
                </label>

                <label style="display: flex; align-items: center; padding: 15px;
                              background: #252525; border-radius: 8px; cursor: pointer;
                              border: 2px solid transparent; transition: all 0.2s;"
                       onmouseover="this.style.borderColor='#555'"
                       onmouseout="this.style.borderColor=this.querySelector('input').checked ? '#fff' : 'transparent'">
                    <input type="radio" name="keyType" value="prodejce"
                           style="width: 20px; height: 20px; margin-right: 15px; accent-color: #fff;">
                    <div>
                        <div style="color: #fff; font-weight: 600; font-size: 1.1rem;">PRODEJCE</div>
                        <div style="color: #888; font-size: 0.85rem;">Pro prodejce a obchodníky</div>
                    </div>
                </label>
            </div>

            <div style="display: flex; gap: 10px;">
                <button data-action="vytvorKlicZModalu" style="flex: 1; padding: 12px;
                        background: #fff; color: #000; border: none; border-radius: 6px;
                        font-weight: 600; cursor: pointer; font-size: 1rem;">
                    Vytvořit klíč
                </button>
                <button data-action="zavritCreateKeyModal"
                        style="flex: 1; padding: 12px; background: #333; color: #fff;
                        border: none; border-radius: 6px; cursor: pointer; font-size: 1rem;">
                    Zrušit
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    // Zavřít při kliknutí na pozadí
    modal.addEventListener('click', (e) => {
        if (e.target === modal) modal.remove();
    });
}

/**
 * VytvorKlicZModalu - odešle požadavek na vytvoření klíče
 */
function vytvorKlicZModalu() {
    const vybranyTyp = document.querySelector('input[name="keyType"]:checked');

    if (!vybranyTyp) {
        alert('Vyberte typ klíče');
        return;
    }

    const keyType = vybranyTyp.value;
    const csrfToken = getCSRFToken();

    if (!csrfToken) {
        alert(t('csrf_token_not_found'));
        return;
    }

    fetch('api/admin_api.php?action=create_key', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            key_type: keyType,
            csrf_token: csrfToken
        })
    })
    .then(r => r.json())
    .then(data => {
        document.getElementById('createKeyModal')?.remove();

        if (isSuccess(data)) {
            alert(t('key_created').replace('{key}', data.key_code));
            loadKeysModal(); // Reload
        } else {
            alert(t('error') + ': ' + (data.error || data.message || t('unknown_error')));
        }
    })
    .catch(err => {
        alert('Chyba: ' + err.message);
    });
}

/**
 * ExecuteAction
 */
async function executeAction(actionId) {
    // Zachytit tlačítko PŘED jakýmkoliv await
    const btn = event.target;
    const originalText = btn.textContent;

    const csrfToken = await getCSRFToken();

    if (!csrfToken || typeof csrfToken !== 'string' || csrfToken.length === 0) {
        alert('Chyba: CSRF token nebyl nalezen nebo je neplatný. Obnovte stránku.');
        return;
    }

    if (!confirm('Spustit tuto akci? Bude provedena automaticky.')) {
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Provádění...';

    const payload = {
        action_id: actionId,
        csrf_token: csrfToken
    };

    fetch('api/admin.php?action=execute_action', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(async r => {
        let responseData;
        try {
            responseData = await r.json();
        } catch (e) {
            responseData = null;
        }

        if (!r.ok) {
            let errorMsg = `HTTP ${r.status}`;
            if (responseData) {
                errorMsg = responseData.message || 'Neznámá chyba';
                if (responseData.debug) {
                    errorMsg += '\n\n' + Object.entries(responseData.debug)
                        .map(([k, v]) => `${k}: ${typeof v === 'object' ? JSON.stringify(v, null, 2) : v}`)
                        .join('\n');
                }
            }
            throw new Error(errorMsg);
        }

        return responseData;
    })
    .then(data => {
        if (isSuccess(data)) {
            const execTime = data.execution_time || 'neznámý čas';
            alert(`Akce dokončena!\n\n${data.message}\n\nČas provedení: ${execTime}`);
            loadActionsModal();
        } else {
            alert('Chyba: ' + (data.error || data.message || 'Neznámá chyba'));
            btn.disabled = false;
            btn.textContent = originalText;
        }
    })
    .catch(err => {
        alert('Chyba při provádění akce: ' + err.message);
        btn.disabled = false;
        btn.textContent = originalText;
    });
}

/**
 * CompleteAction
 */
function completeAction(actionId) {
    const csrfToken = getCSRFToken();
    if (!csrfToken) {
        alert(t('csrf_token_not_found'));
        return;
    }

    fetch('api/admin.php?action=complete_action', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action_id: actionId,
            csrf_token: csrfToken
        })
    })
    .then(r => r.json())
    .then(data => {
        if (isSuccess(data)) {
            loadActionsModal();
            location.reload();
        } else {
            alert(t('error') + ': ' + (data.error || data.message || t('unknown_error')));
        }
    })
    .catch(err => {
        alert('Chyba: ' + err.message);
    });
}

/**
 * DismissAction
 */
function dismissAction(actionId) {
    const csrfToken = getCSRFToken();
    if (!csrfToken) {
        alert(t('csrf_token_not_found'));
        return;
    }

    fetch('api/admin.php?action=dismiss_action', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action_id: actionId,
            csrf_token: csrfToken
        })
    })
    .then(r => r.json())
    .then(data => {
        if (isSuccess(data)) {
            loadActionsModal();
            location.reload();
        } else {
            alert(t('error') + ': ' + (data.error || data.message || t('unknown_error')));
        }
    })
    .catch(err => {
        alert('Chyba: ' + err.message);
    });
}

// Clear cache and reload
/**
 * ClearCacheAndReload
 */
async function clearCacheAndReload() {
    if (!confirm('Vymazat lokální cache a načíst nejnovější verzi? Stránka se znovu načte.')) {
        return;
    }

    try {
        // Vymazat localStorage
        if (window.localStorage) {
            const itemsToKeep = ['theme', 'user_preferences']; // Ponechat důležité věci
            const storage = {};
            itemsToKeep.forEach(key => {
                const val = localStorage.getItem(key);
                if (val !== null) storage[key] = val;
            });

            localStorage.clear();

            // Vrátit důležité položky
            Object.keys(storage).forEach(key => {
                localStorage.setItem(key, storage[key]);
            });

            console.log('localStorage vymazán');
        }

        // Vymazat sessionStorage
        if (window.sessionStorage) {
            sessionStorage.clear();
            console.log('sessionStorage vymazán');
        }

        // Vymazat Service Worker cache (pokud existuje)
        if ('caches' in window) {
            const names = await caches.keys();
            await Promise.all(names.map(name => caches.delete(name)));
            console.log('Service Worker cache vymazán (' + names.length + ' cache(s))');
        }

        console.log('[Sync] Reloaduji stránku s force refresh...');

        // Force reload s timestamp pro cache busting
        const timestamp = new Date().getTime();
        const url = new URL(window.location.href);
        url.searchParams.set('_cachebust', timestamp);

        // Hard reload
        window.location.href = url.toString();

        // Fallback: pokud výše nefunguje
        setTimeout(() => {
            window.location.reload(true);
        }, 100);

    } catch (err) {
        console.error('Chyba při mazání cache:', err);
        alert('Chyba při mazání cache. Zkuste manuální refresh (Ctrl+Shift+R).');
    }
}

// Close modal on ESC key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeCCModal();
    }
});

// ===========================================================================
// LOADING STATES - Card loading indicators
// Přidáno: FÁZE 5
// ===========================================================================

/**
 * Inicializace loading indikátorů na všechny cc-card elementy
 */
function initCardLoadingStates() {
    const cards = document.querySelectorAll('.admin-card');

    cards.forEach(card => {
        // Přidat loading div pokud ještě neexistuje
        if (!card.querySelector('.admin-card-loader')) {
            const loader = document.createElement('div');
            loader.className = 'cc-card-loader';
            loader.innerHTML = '<div class="cc-card-loader-spinner"></div>';
            card.appendChild(loader);
        }

        // Přidat event listener pro aktivaci loading stavu
        const originalOnclick = card.onclick;
        card.onclick = function(event) {
            activateCardLoading(card);

            // Spustit původní onclick funkci
            if (originalOnclick) {
                originalOnclick.call(this, event);
            }

            // Deaktivovat loading po 500ms (fallback pokud modal loading selže)
            setTimeout(() => deactivateCardLoading(card), 500);
        };
    });
}

/**
 * Aktivovat loading stav na kartě
 */
function activateCardLoading(card) {
    card.classList.add('loading');
    const loader = card.querySelector('.admin-card-loader');
    if (loader) {
        loader.classList.add('active');
    }
}

/**
 * Deaktivovat loading stav na kartě
 */
function deactivateCardLoading(card) {
    card.classList.remove('loading');
    const loader = card.querySelector('.admin-card-loader');
    if (loader) {
        loader.classList.remove('active');
    }
}

/**
 * Deaktivovat loading na všech kartách
 */
function deactivateAllCardLoading() {
    document.querySelectorAll('.admin-card.loading').forEach(card => {
        deactivateCardLoading(card);
    });
}

// Inicializovat loading stavy při načtení stránky
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCardLoadingStates);
} else {
    initCardLoadingStates();
}

// Deaktivovat loading když se modal otevře
const originalOpenCCModal = window.openCCModal;
window.openCCModal = function(...args) {
    deactivateAllCardLoading();
    if (originalOpenCCModal) {
        return originalOpenCCModal.apply(this, args);
    }
};

// ============================================================
// SPRÁVA UŽIVATELŮ - DETAIL
// ============================================================

/**
 * Zobrazení detailu uživatele s možností úprav
 */
async function zobrazDetailUzivatele(userId) {
  try {
    logger.log('Načítání detailu uživatele:', userId);

    const response = await fetch(`/api/admin_users_api.php?action=get&user_id=${userId}`, {
      credentials: 'same-origin'
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    const data = await response.json();

    if (data.status !== 'success' || !data.user) {
      throw new Error(data.message || 'Nepodařilo se načíst detail uživatele');
    }

    const user = data.user;

    // Vytvoření modalu s detailem
    const modalHTML = `
      <div class="user-detail-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10000; display: flex; align-items: center; justify-content: center;">
        <div style="background: white; border-radius: 12px; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
          <!-- Header -->
          <div style="background: #333333; color: white; padding: 1.5rem; border-radius: 12px 12px 0 0; position: relative;">
            <h2 style="margin: 0; font-size: 1.3rem; font-weight: 600;">Detail uživatele #${user.id}</h2>
            <button data-action="zavritDetailUzivatele" style="position: absolute; top: 1rem; right: 1rem; background: none; border: none; color: white; font-size: 2rem; cursor: pointer; line-height: 1; padding: 0; width: 32px; height: 32px;">&times;</button>
          </div>

          <!-- Body -->
          <div style="padding: 2rem;">
            <!-- Základní informace -->
            <div style="margin-bottom: 2rem;">
              <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 1rem; color: #333333; border-bottom: 2px solid #333333; padding-bottom: 0.5rem;">Základní údaje</h3>

              <div style="margin-bottom: 1rem;">
                <label style="display: block; font-weight: 500; margin-bottom: 0.3rem; font-size: 0.9rem;">Jméno a příjmení</label>
                <input type="text" id="edit-user-name" value="${escapeHtml(user.name)}" style="width: 100%; padding: 0.6rem; border: 1px solid #ddd; border-radius: 6px; font-size: 1rem;">
              </div>

              <div style="margin-bottom: 1rem;">
                <label style="display: block; font-weight: 500; margin-bottom: 0.3rem; font-size: 0.9rem;">Email</label>
                <input type="email" id="edit-user-email" value="${escapeHtml(user.email)}" style="width: 100%; padding: 0.6rem; border: 1px solid #ddd; border-radius: 6px; font-size: 1rem;">
              </div>

              <div style="margin-bottom: 1rem;">
                <label style="display: block; font-weight: 500; margin-bottom: 0.3rem; font-size: 0.9rem;">Telefon</label>
                <input type="tel" id="edit-user-phone" value="${escapeHtml(user.phone || '')}" placeholder="+420123456789" style="width: 100%; padding: 0.6rem; border: 1px solid #ddd; border-radius: 6px; font-size: 1rem;">
              </div>

              <div style="margin-bottom: 1rem;">
                <label style="display: block; font-weight: 500; margin-bottom: 0.3rem; font-size: 0.9rem;">Adresa</label>
                <input type="text" id="edit-user-address" value="${escapeHtml(user.address || '')}" placeholder="Ulice 123, Město" style="width: 100%; padding: 0.6rem; border: 1px solid #ddd; border-radius: 6px; font-size: 1rem;">
              </div>

              <div style="margin-bottom: 1rem;">
                <label style="display: block; font-weight: 500; margin-bottom: 0.3rem; font-size: 0.9rem;">Role</label>
                <select id="edit-user-role" style="width: 100%; padding: 0.6rem; border: 1px solid #ddd; border-radius: 6px; font-size: 1rem;">
                  <option value="prodejce" ${user.role === 'prodejce' ? 'selected' : ''}>Prodejce</option>
                  <option value="technik" ${user.role === 'technik' ? 'selected' : ''}>Technik</option>
                  <option value="admin" ${user.role === 'admin' ? 'selected' : ''}>Administrátor</option>
                </select>
              </div>

              <button data-action="ulozitZmenyUzivatele" data-id="${user.id}" style="width: 100%; padding: 0.8rem; background: #333333; color: white; border: none; border-radius: 6px; font-weight: 600; font-size: 1rem; cursor: pointer; transition: background 0.2s;">
                [Save] Uložit změny
              </button>
            </div>

            <!-- Změna hesla -->
            <div style="margin-bottom: 2rem; padding: 1rem; background: #f9f9f9; border-radius: 8px;">
              <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 1rem; color: #d97706;">[Lock] Změna hesla</h3>

              <div style="margin-bottom: 1rem;">
                <label style="display: block; font-weight: 500; margin-bottom: 0.3rem; font-size: 0.9rem;">Nové heslo (min. 8 znaků)</label>
                <input type="password" id="edit-user-password" placeholder="••••••••" style="width: 100%; padding: 0.6rem; border: 1px solid #ddd; border-radius: 6px; font-size: 1rem;">
              </div>

              <button data-action="zmenitHesloUzivatele" data-id="${user.id}" style="width: 100%; padding: 0.8rem; background: #d97706; color: white; border: none; border-radius: 6px; font-weight: 600; font-size: 1rem; cursor: pointer; transition: background 0.2s;">
                Změnit heslo
              </button>
            </div>

            <!-- Status a akce -->
            <div style="padding: 1rem; background: ${user.status === 'active' ? '#d1fae5' : '#fee2e2'}; border-radius: 8px;">
              <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 1rem;">Stav účtu</h3>

              <div style="margin-bottom: 1rem;">
                <strong>Aktuální stav:</strong>
                <span style="font-weight: bold; color: ${user.status === 'active' ? '#059669' : '#dc2626'};">
                  ${user.status === 'active' ? 'AKTIVNÍ' : 'NEAKTIVNÍ'}
                </span>
              </div>

              ${user.created_at ? `
                <div style="margin-bottom: 1rem; font-size: 0.9rem; color: #666;">
                  <strong>Vytvořen:</strong> ${new Date(user.created_at).toLocaleString('cs-CZ')}
                </div>
              ` : ''}

              ${user.last_login ? `
                <div style="margin-bottom: 1rem; font-size: 0.9rem; color: #666;">
                  <strong>Poslední přihlášení:</strong> ${new Date(user.last_login).toLocaleString('cs-CZ')}
                </div>
              ` : ''}

              <button data-action="prepnoutStatusUzivatele" data-id="${user.id}" data-status="${user.status === 'active' ? 'inactive' : 'active'}" style="width: 100%; padding: 0.8rem; background: ${user.status === 'active' ? '#dc2626' : '#059669'}; color: white; border: none; border-radius: 6px; font-weight: 600; font-size: 1rem; cursor: pointer;">
                ${user.status === 'active' ? 'Deaktivovat uživatele' : 'Aktivovat uživatele'}
              </button>
            </div>
          </div>
        </div>
      </div>
    `;

    // Přidat modal do DOM
    const modalContainer = document.createElement('div');
    modalContainer.id = 'userDetailModal';
    modalContainer.innerHTML = modalHTML;
    document.body.appendChild(modalContainer);

  } catch (error) {
    logger.error('Chyba při načítání detailu uživatele:', error);
    alert('Chyba při načítání detailu: ' + error.message);
  }
}

/**
 * Zavření detailu uživatele
 */
function zavritDetailUzivatele() {
  const modal = document.getElementById('userDetailModal');
  if (modal) {
    modal.remove();
  }
}

/**
 * Uložení změn uživatele
 */
async function ulozitZmenyUzivatele(userId) {
  try {
    const name = document.getElementById('edit-user-name').value.trim();
    const email = document.getElementById('edit-user-email').value.trim();
    const phone = document.getElementById('edit-user-phone').value.trim();
    const address = document.getElementById('edit-user-address').value.trim();
    const role = document.getElementById('edit-user-role').value;

    if (!name || !email) {
      alert('Jméno a email jsou povinné');
      return;
    }

    const csrfToken = await getCSRFToken();
    if (!csrfToken) {
      throw new Error('CSRF token není k dispozici');
    }

    const response = await fetch('/api/admin_users_api.php?action=update', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        user_id: userId,
        name,
        email,
        phone,
        address,
        role,
        csrf_token: csrfToken
      })
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    const data = await response.json();

    if (data.status === 'success') {
      alert('Změny byly uloženy!');
      zavritDetailUzivatele();
      loadUsers(); // Obnovit tabulku
    } else {
      alert('Chyba: ' + (data.message || 'Nepodařilo se uložit změny'));
    }
  } catch (error) {
    logger.error('Chyba při ukládání změn:', error);
    alert('Chyba při ukládání: ' + error.message);
  }
}

/**
 * Změna hesla uživatele
 */
async function zmenitHesloUzivatele(userId) {
  try {
    const newPassword = document.getElementById('edit-user-password').value;

    if (!newPassword) {
      alert('Zadejte nové heslo');
      return;
    }

    if (newPassword.length < 8) {
      alert('Heslo musí mít alespoň 8 znaků');
      return;
    }

    const confirmed = confirm('Opravdu chcete změnit heslo tohoto uživatele?');
    if (!confirmed) return;

    const csrfToken = await getCSRFToken();
    if (!csrfToken) {
      throw new Error('CSRF token není k dispozici');
    }

    const response = await fetch('/api/admin_users_api.php?action=update_password', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        user_id: userId,
        new_password: newPassword,
        csrf_token: csrfToken
      })
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    const data = await response.json();

    if (data.status === 'success') {
      alert('Heslo bylo změněno!');
      document.getElementById('edit-user-password').value = ''; // Vymazat pole
    } else {
      alert('Chyba: ' + (data.message || 'Nepodařilo se změnit heslo'));
    }
  } catch (error) {
    logger.error('Chyba při změně hesla:', error);
    alert('Chyba při změně hesla: ' + error.message);
  }
}

/**
 * Přepnutí statusu uživatele (aktivní/neaktivní)
 */
async function prepnoutStatusUzivatele(userId, newStatus) {
  try {
    const statusText = newStatus === 'active' ? 'aktivovat' : 'deaktivovat';
    const confirmed = confirm(`Opravdu chcete ${statusText} tohoto uživatele?`);
    if (!confirmed) return;

    const csrfToken = await getCSRFToken();
    if (!csrfToken) {
      throw new Error('CSRF token není k dispozici');
    }

    const response = await fetch('/api/admin_users_api.php?action=update_status', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        user_id: userId,
        status: newStatus,
        csrf_token: csrfToken
      })
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    const data = await response.json();

    if (data.status === 'success') {
      alert(`Uživatel byl ${newStatus === 'active' ? 'aktivován' : 'deaktivován'}!`);
      zavritDetailUzivatele();
      loadUsers(); // Obnovit tabulku
    } else {
      alert('Chyba: ' + (data.message || 'Nepodařilo se změnit status'));
    }
  } catch (error) {
    logger.error('Chyba při změně statusu:', error);
    alert('Chyba při změně statusu: ' + error.message);
  }
}

// Zpřístupnit funkce globálně
window.zobrazDetailUzivatele = zobrazDetailUzivatele;
window.zavritDetailUzivatele = zavritDetailUzivatele;
window.ulozitZmenyUzivatele = ulozitZmenyUzivatele;
window.zmenitHesloUzivatele = zmenitHesloUzivatele;
window.prepnoutStatusUzivatele = prepnoutStatusUzivatele;

// Posluchač postMessage pro přepínání tabů z iframe
window.addEventListener('message', function(event) {
  // Bezpečnostní kontrola - pouze zprávy z naší domény
  if (event.origin !== window.location.origin) {
    return;
  }

  const message = event.data;

  if (message.action === 'switchTab' && message.tab) {
    // Přepnout na požadovaný tab
    let url = '/admin.php?tab=' + message.tab;
    if (message.highlightId) {
      url += '#' + message.highlightId;
    }
    window.location.href = url;
  }
});

// === ACTION REGISTRY - Registrace akcí pro event delegation (Step 110-111) ===
if (typeof Utils !== 'undefined' && Utils.registerAction) {
  // Navigace na SQL stránku
  Utils.registerAction('openSQLPage', () => openSQLPage());

  // Otevření notifikačního modalu
  Utils.registerAction('openNotifModal', (el, data) => {
    if (data.modal && typeof openNotifModal === 'function') {
      openNotifModal(data.modal);
    }
  });

  // Cache clear a reload
  Utils.registerAction('clearCacheAndReload', () => {
    if (typeof clearCacheAndReload === 'function') {
      clearCacheAndReload();
    }
  });

  // Modal close akce
  Utils.registerAction('closeCCModal', () => {
    if (typeof closeCCModal === 'function') {
      closeCCModal();
    }
  });

  Utils.registerAction('closeEditNotificationModal', () => {
    if (typeof closeEditNotificationModal === 'function') {
      closeEditNotificationModal();
    }
  });

  // Email management akce
  Utils.registerAction('addCCEmail', () => {
    if (typeof addCCEmail === 'function') {
      addCCEmail();
    }
  });

  Utils.registerAction('addBCCEmail', () => {
    if (typeof addBCCEmail === 'function') {
      addBCCEmail();
    }
  });

  Utils.registerAction('saveNotificationTemplate', () => {
    if (typeof saveNotificationTemplate === 'function') {
      saveNotificationTemplate();
    }
  });

  // Step 113 - Admin main cards
  Utils.registerAction('openSection', (el, data) => {
    if (data.section && typeof openSection === 'function') {
      openSection(data.section);
    }
  });

  Utils.registerAction('openNewWindow', (el, data) => {
    if (data.url) {
      window.open(data.url, '_blank');
    }
  });

  // Step 114 - Nové akce z migrace onclick
  Utils.registerAction('zobrazDetailUzivatele', (el, data) => {
    if (data.id && typeof zobrazDetailUzivatele === 'function') {
      zobrazDetailUzivatele(data.id);
    }
  });

  Utils.registerAction('deleteUser', (el, data) => {
    if (data.id && typeof deleteUser === 'function') {
      el.closest('[data-action="stopPropagation"]')?.dispatchEvent(new Event('click', { bubbles: false }));
      deleteUser(data.id);
    }
  });

  Utils.registerAction('otevritZakazkyZakaznika', (el, data) => {
    if (data.jmeno && data.email && typeof otevritZakazkyZakaznika === 'function') {
      otevritZakazkyZakaznika(data.jmeno, data.email);
    }
  });

  Utils.registerAction('navigateToAddUser', () => {
    window.location.href = 'admin.php?tab=users';
  });

  Utils.registerAction('loadUsersModal', () => {
    if (typeof loadUsersModal === 'function') {
      loadUsersModal();
    }
  });

  Utils.registerAction('vytvorKlicZModalu', () => {
    if (typeof vytvorKlicZModalu === 'function') {
      vytvorKlicZModalu();
    }
  });

  Utils.registerAction('zavritCreateKeyModal', () => {
    const modal = document.getElementById('createKeyModal');
    if (modal) modal.remove();
  });

  Utils.registerAction('zavritDetailUzivatele', () => {
    if (typeof zavritDetailUzivatele === 'function') {
      zavritDetailUzivatele();
    }
  });

  Utils.registerAction('ulozitZmenyUzivatele', (el, data) => {
    if (data.id && typeof ulozitZmenyUzivatele === 'function') {
      ulozitZmenyUzivatele(data.id);
    }
  });

  Utils.registerAction('zmenitHesloUzivatele', (el, data) => {
    if (data.id && typeof zmenitHesloUzivatele === 'function') {
      zmenitHesloUzivatele(data.id);
    }
  });

  Utils.registerAction('prepnoutStatusUzivatele', (el, data) => {
    if (data.id && data.status && typeof prepnoutStatusUzivatele === 'function') {
      prepnoutStatusUzivatele(data.id, data.status);
    }
  });
}
