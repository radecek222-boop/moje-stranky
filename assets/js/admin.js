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

const SESSION_EXPIRED_MESSAGE = 'Vaše administrátorská relace vypršela. Přihlaste se prosím znovu.';

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
  safeLogger.log('✅ Admin panel initialized');
  setupNavigation();
  initUserManagement();
}

// Inicializace - podpora pro defer i normální načítání
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initAdminPanel);
} else {
  initAdminPanel();
}

safeLogger.log('✅ admin.js loaded');

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
        logger.log('🔄 Navigating to:', url);
        window.location.href = url;
      }
    });
  });
  logger.log('✅ Navigation setup complete');
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

        html += '<tr>';
        html += '<td>' + user.id + '</td>';
        html += '<td>' + escapeHtml(user.name || user.full_name) + '</td>'; // API returns 'name' not 'full_name'
        html += '<td>' + escapeHtml(user.email) + '</td>';
        html += '<td>' + escapeHtml(user.role) + '</td>';
        html += '<td><span class="badge ' + statusClass + '">' + statusText + '</span></td>';
        html += '<td>' + createdDate + '</td>';
        html += '<td>';
        html += '<button class="btn btn-sm btn-danger" onclick="deleteUser(' + user.id + ')">Smazat</button>';
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
        alert(SESSION_EXPIRED_MESSAGE);
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
      alert(data.message || 'Chyba při mazání');
    }
  } catch (error) {
    alert('Chyba při mazání uživatele');
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
function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

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

  // Auto-load based on active tab
  const urlParams = new URLSearchParams(window.location.search);
  const tab = urlParams.get('tab');

  const hasDashboard = document.getElementById('tab-dashboard');
  const hasUsers = document.getElementById('tab-users');
  const hasOnline = document.getElementById('tab-online');

  if ((!tab || tab === 'dashboard') && hasDashboard) {
    loadDashboard();
  } else if (tab === 'users' && hasUsers) {
    loadUsers();
  } else if (tab === 'online' && hasOnline) {
    loadOnline();
  }
}

// Control Center Unified - Version Check
// Debug mode - set to false in production
const DEBUG_MODE = false;
if (DEBUG_MODE) {
    console.log('%c🔧 Control Center v2025.11.12-1430 loaded', 'background: #667eea; color: white; padding: 4px 8px; border-radius: 4px;');
    console.log('✅ executeAction is ASYNC + event.target captured BEFORE await');
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

    if (tokenStr) {
        if (DEBUG_MODE) console.log('CSRF token loaded:', tokenStr.substring(0, 10) + '... (length: ' + tokenStr.length + ')');
    } else {
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
 */
function openCCModal(section) {
    console.log('[openCCModal] Opening modal for section:', section);

    const overlay = document.getElementById('adminOverlay');
    const modal = document.getElementById('adminModal');
    const modalBody = document.getElementById('adminModalBody');

    // Kontrola existence elementů
    if (!overlay || !modal || !modalBody) {
        console.error('Modal elementy nenalezeny:', { overlay, modal, modalBody });
        return;
    }

    console.log('[openCCModal] Elements found:', { overlay: !!overlay, modal: !!modal, modalBody: !!modalBody });

    // Show overlay and modal
    overlay.classList.add('active');
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';

    console.log('[openCCModal] Modal activated, classes added');

    // Show loading
    modalBody.innerHTML = '<div class="cc-modal-loading"><div class="cc-modal-spinner"></div><div style="margin-top: 1rem;">Načítání...</div></div>';

    console.log('[openCCModal] Loading spinner shown');

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
 */
function closeCCModal() {
    const overlay = document.getElementById('adminOverlay');
    const modal = document.getElementById('adminModal');

    overlay.classList.remove('active');
    modal.classList.remove('active');
    document.body.style.overflow = '';
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

    modalBody.innerHTML = `
        <div class="cc-actions">
            <button class="btn btn-sm btn-success" onclick="createKey()">+ Vytvořit nový klíč</button>
            <button class="btn btn-sm" onclick="loadKeysModal()">Obnovit</button>
        </div>
        <div id="keysTableContainer">Načítání klíčů...</div>
    `;

    // Load keys
    fetch('api/admin_api.php?action=list_keys')
        .then(async r => {
            if (!r.ok) throw new Error(`HTTP ${r.status}`);
            return r.json();
        })
        .then(data => {
            const container = document.getElementById('keysTableContainer');

            if (isSuccess(data) && data.keys && data.keys.length > 0) {
                let html = '<table class="cc-table"><thead><tr>';
                html += '<th>Klíč</th><th>Typ</th><th>Použití</th><th>Status</th><th>Vytvořen</th><th>Akce</th>';
                html += '</tr></thead><tbody>';

                data.keys.forEach(key => {
                    // Escapování pro XSS ochranu
                    const safeKeyCode = typeof escapeHTML === 'function' ? escapeHTML(key.key_code) : key.key_code;
                    const safeKeyType = typeof escapeHTML === 'function' ? escapeHTML(key.key_type) : key.key_type;

                    html += '<tr>';
                    html += `<td><code>${safeKeyCode}</code></td>`;
                    html += `<td><span class="badge badge-${safeKeyType}">${safeKeyType}</span></td>`;
                    html += `<td>${parseInt(key.usage_count) || 0} / ${parseInt(key.max_usage) || '∞'}</td>`;
                    html += `<td><span class="badge badge-${key.is_active ? 'active' : 'inactive'}">${key.is_active ? 'Aktivní' : 'Neaktivní'}</span></td>`;
                    html += `<td>${new Date(key.created_at).toLocaleDateString('cs-CZ')}</td>`;
                    html += `<td><button class="btn btn-sm btn-danger" onclick="deleteKey('${safeKeyCode}')">Smazat</button></td>`;
                    html += '</tr>';
                });

                html += '</tbody></table>';
                container.innerHTML = html;
            } else if (isSuccess(data) && data.keys && data.keys.length === 0) {
                container.innerHTML = '<p style="color: var(--c-grey); text-align: center; padding: 2rem;">Žádné registrační klíče<br><small>Vytvořte nový klíč pomocí tlačítka výše</small></p>';
            } else {
                container.innerHTML = '<p style="color: var(--c-error); text-align: center; padding: 2rem;">Chyba načítání</p>';
            }
        })
        .catch(err => {
            console.error('[Control Center] Keys load error:', err);
            document.getElementById('keysTableContainer').innerHTML = '<p style="color: var(--c-error); text-align: center; padding: 2rem;">Chyba načítání</p>';
        });
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
            <button class="btn btn-sm btn-success" onclick="window.location.href='admin.php?tab=users'">+ Přidat uživatele</button>
            <button class="btn btn-sm" onclick="loadUsersModal()">Obnovit</button>
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
function loadNotificationsModal() {
    const modalBody = document.getElementById('adminModalBody');
    if (!modalBody) {
        console.error('adminModalBody element nenalezen');
        return;
    }
    const url = getEmbedUrlWithCSRF("admin.php?tab=notifications&embed=1");
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

    modalBody.innerHTML = `
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
    `;

    // Load claims stats
    fetch('api/admin_api.php?action=list_reklamace')
        .then(r => {
            if (!r.ok) throw new Error(`HTTP ${r.status}`);
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
    try {
        const url = await getEmbedUrlWithCSRF("admin.php?tab=admin_actions&embed=1");
        modalBody.innerHTML = `<div class="cc-iframe-container"><iframe src="${url}" sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-modals" title="Akce & Úkoly"></iframe></div>`;
    } catch (error) {
        console.error('[loadActionsModal] Failed to load iframe URL:', error);
        modalBody.innerHTML = '<div class="cc-modal-loading">Nepodařilo se načíst akce & úkoly.</div>';
    }
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
    try {
        const url = await getEmbedUrlWithCSRF("admin.php?tab=admin_console&embed=1");
        modalBody.innerHTML = `<div class="cc-iframe-container"><iframe src="${url}" sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-modals" title="Konzole - Developer Tools"></iframe></div>`;
    } catch (error) {
        console.error('[loadConsoleModal] Failed to load iframe URL:', error);
        modalBody.innerHTML = '<div class="cc-modal-loading">Nepodařilo se načíst konzoli.</div>';
    }
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
    try {
        const url = await getEmbedUrlWithCSRF("admin.php?tab=admin_testing_interactive&embed=1");
        modalBody.innerHTML = `<div class="cc-iframe-container"><iframe src="${url}" sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-modals" title="Testovací prostředí"></iframe></div>`;
    } catch (error) {
        console.error('[loadTestingModal] Failed to load iframe URL:', error);
        modalBody.innerHTML = '<div class="cc-modal-loading">Nepodařilo se načíst testovací prostředí.</div>';
    }
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
    try {
        const url = await getEmbedUrlWithCSRF("admin.php?tab=admin_appearance&embed=1");
        modalBody.innerHTML = `<div class="cc-iframe-container"><iframe src="${url}" sandbox="allow-scripts allow-same-origin allow-forms" title="Vzhled & Design"></iframe></div>`;
    } catch (error) {
        console.error('[loadAppearanceModal] Failed to load iframe URL:', error);
        modalBody.innerHTML = '<div class="cc-modal-loading">Nepodařilo se načíst vzhled & design.</div>';
    }
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
    try {
        const url = await getEmbedUrlWithCSRF("admin.php?tab=admin_content&embed=1");
        modalBody.innerHTML = `<div class="cc-iframe-container"><iframe src="${url}" sandbox="allow-scripts allow-same-origin allow-forms" title="Obsah & Texty"></iframe></div>`;
    } catch (error) {
        console.error('[loadContentModal] Failed to load iframe URL:', error);
        modalBody.innerHTML = '<div class="cc-modal-loading">Nepodařilo se načíst obsah & texty.</div>';
    }
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
    try {
        const url = await getEmbedUrlWithCSRF("admin.php?tab=admin_configuration&embed=1");
        modalBody.innerHTML = `<div class="cc-iframe-container"><iframe src="${url}" sandbox="allow-scripts allow-same-origin allow-forms" title="Konfigurace systému"></iframe></div>`;
    } catch (error) {
        console.error('[loadConfigModal] Failed to load iframe URL:', error);
        modalBody.innerHTML = '<div class="cc-modal-loading">Nepodařilo se načíst konfiguraci systému.</div>';
    }
}

// === ACTION HANDLERS ===

/**
 * DeleteKey
 */
function deleteKey(keyCode) {
    if (!confirm('Opravdu chcete smazat tento klíč?')) return;

    const csrfToken = getCSRFToken();
    if (!csrfToken) {
        alert('Chyba: CSRF token nebyl nalezen. Obnovte stránku.');
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
            alert('Chyba: ' + (data.error || data.message || 'Neznámá chyba'));
        }
    })
    .catch(err => {
        alert('Chyba: ' + err.message);
    });
}

/**
 * CreateKey
 */
function createKey() {
    const keyType = prompt('Typ klíče (admin/technik/prodejce/partner):');
    if (!keyType) return;

    const csrfToken = getCSRFToken();
    if (!csrfToken) {
        alert('Chyba: CSRF token nebyl nalezen. Obnovte stránku.');
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
        if (isSuccess(data)) {
            alert('Vytvořeno: ' + data.key_code);
            loadKeysModal(); // Reload
        } else {
            alert('Chyba: ' + (data.error || data.message || 'Neznámá chyba'));
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
    if (DEBUG_MODE) console.log('[executeAction] Starting with actionId:', actionId);

    // Capture button reference BEFORE any await (event becomes undefined after await in async functions)
    const btn = event.target;
    const originalText = btn.textContent;

    // Await the CSRF token (handles both sync and async getCSRFToken)
    const csrfToken = await getCSRFToken();
    if (DEBUG_MODE) console.log('[executeAction] CSRF token retrieved:', {
        type: typeof csrfToken,
        value: csrfToken && typeof csrfToken === 'string' ? csrfToken.substring(0, 10) + '...' : csrfToken,
        length: csrfToken ? csrfToken.length : 0
    });

    if (!csrfToken || typeof csrfToken !== 'string' || csrfToken.length === 0) {
        alert('Chyba: CSRF token nebyl nalezen nebo je neplatný. Obnovte stránku.');
        console.error('[executeAction] CSRF token is invalid:', {type: typeof csrfToken, value: csrfToken});
        return;
    }

    if (!confirm('Spustit tuto akci? Bude provedena automaticky.')) {
        if (DEBUG_MODE) console.log('[executeAction] User cancelled');
        return;
    }

    // Disable button during execution
    btn.disabled = true;
    btn.textContent = 'Provádění...';

    const payload = {
        action_id: actionId,
        csrf_token: csrfToken
    };

    if (DEBUG_MODE) console.log('[executeAction] Sending request with payload:', payload);

    fetch('api/admin.php?action=execute_action', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(async r => {
        if (DEBUG_MODE) console.log('[executeAction] Response status:', r.status);

        // Zkusit načíst JSON i při chybě
        let responseData;
        try {
            responseData = await r.json();
            if (DEBUG_MODE) console.log('[executeAction] Response data:', responseData);
        } catch (e) {
            console.error('[executeAction] Failed to parse JSON:', e);
            responseData = null;
        }

        if (!r.ok) {
            let errorMsg = `HTTP ${r.status}`;
            if (responseData) {
                errorMsg = responseData.message || 'Unknown error';
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
        if (DEBUG_MODE) console.log('[executeAction] Success data:', data);

        if (isSuccess(data)) {
            const execTime = data.execution_time || 'neznámý čas';
            alert(`✓ Akce dokončena!\n\n${data.message}\n\nČas provedení: ${execTime}`);
            loadActionsModal();
        } else {
            console.error('[executeAction] Action failed:', data);
            alert('✗ Chyba: ' + (data.error || data.message || 'Neznámá chyba'));
            btn.disabled = false;
            btn.textContent = originalText;
        }
    })
    .catch(err => {
        console.error('[executeAction] Error:', err);
        alert('✗ Chyba při provádění akce: ' + err.message);
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
        alert('Chyba: CSRF token nebyl nalezen. Obnovte stránku.');
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
            alert('Chyba: ' + (data.error || data.message || 'Neznámá chyba'));
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
        alert('Chyba: CSRF token nebyl nalezen. Obnovte stránku.');
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
            alert('Chyba: ' + (data.error || data.message || 'Neznámá chyba'));
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

            console.log('✓ localStorage vymazán');
        }

        // Vymazat sessionStorage
        if (window.sessionStorage) {
            sessionStorage.clear();
            console.log('✓ sessionStorage vymazán');
        }

        // Vymazat Service Worker cache (pokud existuje)
        if ('caches' in window) {
            const names = await caches.keys();
            await Promise.all(names.map(name => caches.delete(name)));
            console.log('✓ Service Worker cache vymazán (' + names.length + ' cache(s))');
        }

        console.log('🔄 Reloaduji stránku s force refresh...');

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

