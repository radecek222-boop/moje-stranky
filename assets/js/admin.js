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
  initKeyManagement();
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
// REGISTRAČNÍ KLÍČE - CLEAN VERSION
// ============================================================
function invalidateCsrfToken() {
  window.csrfTokenCache = null;
}


async function loadKeys() {
  const container = document.getElementById('keys-container');
  if (!container) return;

  try {
    container.innerHTML = '<div class="loading">Načítání klíčů...</div>';
    const response = await fetch('api/admin_api.php?action=list_keys', {
      credentials: 'same-origin'
    });

    if (!response.ok) {
      if (isUnauthorizedStatus(response.status)) {
        container.innerHTML = `<div class="error-message">${SESSION_EXPIRED_MESSAGE}</div>`;
        setTimeout(() => redirectToLogin('admin.php?tab=keys'), 800);
        return;
      }

      throw new Error(`HTTP ${response.status}`);
    }

    const text = await response.text();
    let data;
    try {
      data = JSON.parse(text);
    } catch (e) {
      console.error('API returned invalid JSON:', text);
      throw new Error('Server vrátil neplatnou odpověď (očekáván JSON)');
    }

    if (data.status === 'success' || data.success === true) {
      if (data.keys.length === 0) {
        container.innerHTML = '<p style="text-align:center;color:#999;padding:2rem;">Žádné klíče</p>';
        return;
      }

      let html = '';
      data.keys.forEach(key => {
        html += '<div class="key-display" style="margin-bottom:1.5rem;">';
        html += '<div class="key-label">' + escapeHtml(key.key_type.toUpperCase()) + '</div>';
        html += '<div style="display:flex;align-items:center;gap:1rem;margin:1rem 0;">';
        html += '<code style="flex:1;font-size:1.2rem;padding:1rem;background:#f5f5f5;border:2px dashed#ddd;">' + escapeHtml(key.key_code) + '</code>';
        html += '</div>';
        html += '<div style="font-size:0.85rem;color:#666;margin-bottom:1rem;">';
        html += 'Použití: ' + key.usage_count + '/' + (key.max_usage || '∞') + ' | ';
        html += 'Aktivní: ' + (key.is_active ? 'Ano' : 'Ne') + ' | ';
        html += 'Vytvořen: ' + new Date(key.created_at).toLocaleDateString('cs-CZ');
        html += '</div>';
        html += '<div style="display:flex;gap:0.5rem;">';
        // BEZPEČNOST: Escape single quotes pro onclick handler (XSS protection)
        const escapedKeyCode = key.key_code.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
        html += '<button class="btn btn-sm" onclick="copyToClipboard(\'' + escapedKeyCode + '\')">Kopírovat</button>';
        html += '<button class="btn btn-sm btn-danger" onclick="deleteKey(\'' + escapedKeyCode + '\')">Smazat</button>';
        html += '</div></div>';
      });
      container.innerHTML = html;
      return;
    }

    container.innerHTML = `<div class="error-message">${data.message || 'Nepodařilo se načíst klíče.'}</div>`;
  } catch (error) {
    container.innerHTML = `<div style="background: #f5f5f5; border: 1px solid #000; border-left: 3px solid #000; color: #000; padding: 0.75rem 1rem; margin: 1rem 0; font-size: 0.85rem; font-family: 'Poppins', sans-serif;"><strong>Chyba při načítání klíčů:</strong> ${escapeHtml(error.message || 'Neznámá chyba')}</div>`;
    logger.error('[Control Center] Keys load error:', error);
  }
}

async function createKey() {
  const keyType = prompt('Typ (admin/technik/prodejce/partner):');
  if (!keyType) return;

  try {
    const csrfToken = await getCSRFToken();
    if (!csrfToken) throw new Error('CSRF token not available');

    const response = await fetch('api/admin_api.php?action=create_key', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({key_type: keyType, csrf_token: csrfToken})
    });
    let data = null;

    try {
      data = await response.json();
    } catch (err) {
      data = null;
    }

    if (!response.ok) {
      if (isUnauthorizedStatus(response.status)) {
        alert(SESSION_EXPIRED_MESSAGE);
        redirectToLogin('admin.php?tab=keys');
        return;
      }

      const message = data?.message || 'Nepodařilo se vytvořit klíč.';
      throw new Error(message);
    }

    if (data?.status === 'success') {
      alert('Vytvořeno: ' + data.key_code);
      invalidateCsrfToken();
      loadKeys();
    } else {
      alert(data?.message || 'Nepodařilo se vytvořit klíč');
    }
  } catch (error) {
    logger.error('Error creating key:', error);
    alert('Chyba při vytváření klíče. Zkuste to prosím znovu.');
  }
}

async function deleteKey(keyCode) {
  if (!confirm('Smazat?')) return;

  try {
    const csrfToken = await getCSRFToken();
    if (!csrfToken) throw new Error('CSRF token not available');

    const response = await fetch('api/admin_api.php?action=delete_key', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({key_code: keyCode, csrf_token: csrfToken})
    });
    let data = null;

    try {
      data = await response.json();
    } catch (err) {
      data = null;
    }

    if (!response.ok) {
      if (isUnauthorizedStatus(response.status)) {
        alert(SESSION_EXPIRED_MESSAGE);
        redirectToLogin('admin.php?tab=keys');
        return;
      }

      const message = data?.message || 'Klíč se nepodařilo smazat';
      throw new Error(message);
    }

    if (data?.status === 'success') {
      invalidateCsrfToken();
      loadKeys();
    } else {
      alert(data?.message || 'Klíč se nepodařilo smazat');
    }
  } catch (error) {
    logger.error('Error deleting key:', error);
    alert('Chyba při mazání klíče.');
  }
}

function copyToClipboard(text) {
  navigator.clipboard.writeText(text).then(() => alert('Zkopírováno!'));
}

function initKeyManagement() {
  const createBtn = document.getElementById('createKeyBtn');
  const refreshBtn = document.getElementById('refreshKeysBtn');

  if (createBtn) {
    createBtn.addEventListener('click', createKey);
  }

  if (refreshBtn) {
    refreshBtn.addEventListener('click', loadKeys);
  }

  const keysTab = document.getElementById('tab-keys');
  if (keysTab) {
    loadKeys();
  }
}

// ============================================================
// DASHBOARD STATISTICS
// ============================================================
async function loadDashboard() {
  try {
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
      document.getElementById('stat-claims').textContent = data.stats.claims || 0;
      document.getElementById('stat-users').textContent = data.stats.users || 0;
      document.getElementById('stat-online').textContent = data.stats.online || 0;
      document.getElementById('stat-keys').textContent = data.stats.keys || 0;
    }
  } catch (error) {
    logger.error('Dashboard load error:', error);
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
