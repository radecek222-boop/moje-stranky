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
});

function setupTabs() {
  const tabs = document.querySelectorAll('.tab');
  
  tabs.forEach(tab => {
    tab.addEventListener('click', (e) => {
      e.preventDefault();
      
      const tabName = tab.dataset.tab;
      if (!tabName) return;
      
      logger.log('🔓 Switching to tab:', tabName);
      
      // Deaktivuj všechny taxy
      document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
      
      // Skrej všechny tab-content
      document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
      });
      
      // Aktivuj kliknutou tab
      tab.classList.add('active');
      
      // Zobraz obsah
      const tabContent = document.getElementById(`tab-${tabName}`);
      if (tabContent) {
        tabContent.classList.remove('hidden');
        logger.log('✅ Tab activated:', tabName);
      }
    });
  });
  
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


// ============================================================
// REGISTRAČNÍ KLÍČE - CLEAN VERSION
// ============================================================
async function loadKeys() {
  const container = document.getElementById('keys-container');
  if (!container) return;
  
  try {
    container.innerHTML = '<div class="loading">Načítání klíčů...</div>';
    const response = await fetch('api/admin_api.php?action=list_keys');
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
    const response = await fetch('api/admin_api.php?action=create_key', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({key_type: keyType, csrf_token: csrfToken})
    });
    const data = await response.json();
    if (data.status === 'success') {
      alert('Vytvořeno: ' + data.key.key_code);
      loadKeys();
    } else {
      alert('Chyba: ' + (data.message || 'Neznámá chyba'));
    }
  } catch (error) {
    console.error('Error:', error);
    alert('Chyba při vytváření klíče');
  }
}

async function deleteKey(keyCode) {
  if (!confirm('Smazat?')) return;

  try {
    const csrfToken = await getCSRFToken();
    const response = await fetch('api/admin_api.php?action=delete_key', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({key_code: keyCode, csrf_token: csrfToken})
    });
    const data = await response.json();
    if (data.status === 'success') {
      loadKeys();
    } else {
      alert('Chyba: ' + (data.message || 'Neznámá chyba'));
    }
  } catch (error) {
    console.error('Error:', error);
    alert('Chyba při mazání klíče');
  }
}

function copyToClipboard(text) {
  navigator.clipboard.writeText(text).then(() => alert('Zkopírováno!'));
}

document.addEventListener('DOMContentLoaded', () => {
  const createBtn = document.getElementById('createKeyBtn');
  const refreshBtn = document.getElementById('refreshKeysBtn');
  if (createBtn) createBtn.onclick = createKey;
  if (refreshBtn) refreshBtn.onclick = loadKeys;
  
  document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', (e) => {
      if (e.target.textContent.includes('REGISTRA')) {
        setTimeout(loadKeys, 100);
      }
    });
  });
});

console.log('✅ Keys loaded');

// Tab click listener
document.querySelectorAll('.tab').forEach(tab => {
  tab.addEventListener('click', function() {
    setTimeout(() => {
      const keysTab = document.getElementById('tab-keys');
      if (keysTab && !keysTab.classList.contains('hidden')) {
        console.log('Tab visible, loading keys...');
        loadKeys();
      }
    }, 200);
  });
});

// Automatické načtení klíčů pokud jsme na správném tabu
if (window.location.search.includes('tab=keys') || window.location.hash === '#keys') {
  setTimeout(() => {
    console.log('Auto-loading keys...');
    loadKeys();
  }, 500);
}
