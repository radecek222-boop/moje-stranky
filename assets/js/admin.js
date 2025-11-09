/**
 * ADMIN PANEL - Tab management & core functionality
 */

// ============================================================
// CSRF TOKEN HELPER
// ============================================================
let csrfTokenCache = null;

async function getCSRFToken() {
  if (csrfTokenCache) return csrfTokenCache;

  try {
    const response = await fetch("app/controllers/get_csrf_token.php");
    const data = await response.json();
    csrfTokenCache = data.token;
    return data.token;
  } catch (err) {
    logger.error("Chyba získání CSRF tokenu:", err);
    return null;
  }
}

// ============================================================
// TAB MANAGEMENT
// ============================================================
document.addEventListener('DOMContentLoaded', () => {
  setupTabs();
  logger.log('✅ Admin panel initialized');
  setupNavigation();
  initKeyManagement();
  initUserManagement();
});

function setupTabs() {
  const tabs = Array.from(document.querySelectorAll('.tab'));
  if (!tabs.length) {
    logger.warn('⚠️ Nenalezeny žádné taby v admin panelu');
    return;
  }

  const activateTab = (tab) => {
    const tabName = tab.dataset.tab;
    if (!tabName) {
      return;
    }

    logger.log('🔓 Switching to tab:', tabName);

    tabs.forEach((t) => {
      const isCurrent = t === tab;
      t.classList.toggle('active', isCurrent);
      t.setAttribute('aria-selected', isCurrent ? 'true' : 'false');
      t.setAttribute('tabindex', isCurrent ? '0' : '-1');
    });

    document.querySelectorAll('.tab-content').forEach((content) => {
      const isTarget = content.id === `tab-${tabName}`;
      content.classList.toggle('hidden', !isTarget);
      content.setAttribute('aria-hidden', isTarget ? 'false' : 'true');
    });
  };

  tabs.forEach((tab) => {
    tab.addEventListener('click', (event) => {
      event.preventDefault();
      activateTab(tab);
    });
  });

  const defaultTab = document.querySelector('.tab.active') || tabs[0];
  if (defaultTab) {
    activateTab(defaultTab);
  }

  logger.log('✅ Tabs setup complete');
}

logger.log('✅ admin.js loaded');

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
  csrfTokenCache = null;
}


async function loadKeys() {
  const container = document.getElementById('keys-container');
  if (!container) return;
  
  try {
    container.innerHTML = '<div class="loading">Načítání klíčů...</div>';
    const response = await fetch('api/admin_api.php?action=list_keys', {
      credentials: 'same-origin'
    });
    const data = await response.json();
    
    if (data.status === 'success') {
      if (data.keys.length === 0) {
        container.innerHTML = '<p style="text-align:center;color:#999;padding:2rem;">Žádné klíče</p>';
        return;
      }
      
      let html = '';
      data.keys.forEach(key => {
        html += '<div class="key-display" style="margin-bottom:1.5rem;">';
        html += '<div class="key-label">' + key.key_type.toUpperCase() + '</div>';
        html += '<div style="display:flex;align-items:center;gap:1rem;margin:1rem 0;">';
        html += '<code style="flex:1;font-size:1.2rem;padding:1rem;background:#f5f5f5;border:2px dashed #ddd;">' + key.key_code + '</code>';
        html += '</div>';
        html += '<div style="font-size:0.85rem;color:#666;margin-bottom:1rem;">';
        html += 'Použití: ' + key.usage_count + '/' + (key.max_usage || '∞') + ' | ';
        html += 'Aktivní: ' + (key.is_active ? 'Ano' : 'Ne') + ' | ';
        html += 'Vytvořen: ' + new Date(key.created_at).toLocaleDateString('cs-CZ');
        html += '</div>';
        html += '<div style="display:flex;gap:0.5rem;">';
        html += '<button class="btn btn-sm" onclick="copyToClipboard(\'' + key.key_code + '\')">Kopírovat</button>';
        html += '<button class="btn btn-sm btn-danger" onclick="deleteKey(\'' + key.key_code + '\')">Smazat</button>';
        html += '</div></div>';
      });
      container.innerHTML = html;
    }
  } catch (error) {
    container.innerHTML = '<div class="error-message">Chyba</div>';
    console.error('Error:', error);
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
    const data = await response.json();
    if (data.status === 'success') {
      alert('Vytvořeno: ' + data.key_code);
      invalidateCsrfToken();
      loadKeys();
    } else {
      alert(data.message || 'Nepodařilo se vytvořit klíč');
    }
  } catch (error) {
    console.error('Error:', error);
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
    const data = await response.json();
    if (data.status === 'success') {
      invalidateCsrfToken();
      loadKeys();
    } else {
      alert(data.message || 'Klíč se nepodařilo smazat');
    }
  } catch (error) {
    console.error('Error:', error);
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

  const tabs = document.querySelectorAll('.tab');
  if (tabs.length) {
    tabs.forEach(tab => {
      tab.addEventListener('click', () => {
        setTimeout(() => {
          const keysTab = document.getElementById('tab-keys');
          if (keysTab && !keysTab.classList.contains('hidden')) {
            loadKeys();
          }
        }, 200);
      });
    });
  }

  const keysTab = document.getElementById('tab-keys');
  if (keysTab && !keysTab.classList.contains('hidden')) {
    loadKeys();
    return;
  }

  if (window.location.search.includes('tab=keys') || window.location.hash === '#keys') {
    setTimeout(loadKeys, 300);
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
    const data = await response.json();

    if (data.status === 'success') {
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

    const response = await fetch('api/admin_users_api.php?action=list', {
      credentials: 'same-origin'
    });
    const data = await response.json();

    if (data.status === 'success') {
      if (data.users.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#999;">Žádní uživatelé</td></tr>';
        return;
      }

      let html = '';
      data.users.forEach(user => {
        const statusClass = user.status === 'active' ? 'badge-active' : 'badge-inactive';
        const statusText = user.status === 'active' ? 'Aktivní' : 'Neaktivní';
        const createdDate = new Date(user.created_at).toLocaleDateString('cs-CZ');

        html += '<tr>';
        html += '<td>' + user.id + '</td>';
        html += '<td>' + escapeHtml(user.name) + '</td>';
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
    const response = await fetch('api/admin_users_api.php?action=add', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ name, email, phone, address, role, password })
    });
    const data = await response.json();

    if (data.status === 'success') {
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
    const response = await fetch('api/admin_users_api.php?action=delete', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ user_id: userId })
    });
    const data = await response.json();

    if (data.status === 'success') {
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

    const response = await fetch('api/admin_users_api.php?action=online', {
      credentials: 'same-origin'
    });
    const data = await response.json();

    if (data.status === 'success') {
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

  if (!tab || tab === 'dashboard') {
    loadDashboard();
  } else if (tab === 'users') {
    loadUsers();
  } else if (tab === 'online') {
    loadOnline();
  }

  // Tab switching with auto-load
  document.querySelectorAll('.tab').forEach(tabBtn => {
    tabBtn.addEventListener('click', () => {
      const tabName = tabBtn.dataset.tab;

      setTimeout(() => {
        if (tabName === 'dashboard') {
          loadDashboard();
        } else if (tabName === 'users') {
          loadUsers();
        } else if (tabName === 'online') {
          loadOnline();
        }
      }, 100);
    });
  });
}
