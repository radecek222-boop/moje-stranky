/**
 * UNIFIED LOGIN SYSTEM WITH RECOVERY
 */

const isAdminCheckbox = document.getElementById('isAdmin');
const userLoginFields = document.getElementById('userLoginFields');
const adminLoginFields = document.getElementById('adminLoginFields');
const loginForm = document.getElementById('loginForm');

async function getCsrfTokenFromForm(form) {
  if (!form) return null;
  const tokenInput = form.querySelector('input[name="csrf_token"]');
  if (tokenInput && tokenInput.value) {
    return tokenInput.value;
  }

  try {
    const response = await fetch('app/controllers/get_csrf_token.php', {
      credentials: 'same-origin'
    });
    const data = await response.json();
    if (data.status === 'success' && data.token) {
      if (tokenInput) {
        tokenInput.value = data.token;
      }
      return data.token;
    }
  } catch (error) {
    logger.error('CSRF fetch failed:', error);
  }
  return null;
}

// ============================================================
// FORM SWITCHER
// ============================================================
if (isAdminCheckbox) {
  isAdminCheckbox.addEventListener('change', (e) => {
    if (e.target.checked) {
      userLoginFields.style.display = 'none';
      adminLoginFields.style.display = 'block';
    } else {
      userLoginFields.style.display = 'block';
      adminLoginFields.style.display = 'none';
    }
  });
}

// ============================================================
// LOGIN FORM SUBMIT
// ============================================================
if (loginForm) {
  loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const isAdmin = isAdminCheckbox.checked;
    
    if (isAdmin) {
      await handleAdminLogin();
    } else {
      await handleUserLogin();
    }
  });
}

// ============================================================
// ADMIN LOGIN
// ============================================================
async function handleAdminLogin() {
  const adminKey = document.getElementById('adminKey').value.trim();

  if (!adminKey) {
    showNotification('Vyplňte admin klíč', 'error');
    return;
  }

  const csrfToken = await getCsrfTokenFromForm(loginForm);
  if (!csrfToken) {
    showNotification('Nepodařilo se získat bezpečnostní token. Obnovte stránku.', 'error');
    return;
  }
  
  let attempts = parseInt(localStorage.getItem('admin_login_attempts') || 0);
  attempts++;
  localStorage.setItem('admin_login_attempts', attempts);
  
  logger.log('🔑 Admin login attempt ' + attempts);
  
  try {
    const response = await fetch('app/controllers/login_controller.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      credentials: 'include',
      body: `admin_key=${encodeURIComponent(adminKey)}&csrf_token=${encodeURIComponent(csrfToken)}`
    });
    
    const data = await response.json();
    logger.log('Response:', data);
    
    if (data.status === 'success') {
      localStorage.removeItem('admin_login_attempts');
      showNotification('✅ Admin přihlášení úspěšné!', 'success');
      setTimeout(() => {
        window.location.href = 'admin.php';
      }, 1500);
    } else {
      let msg = data.message || 'Přihlášení selhalo';
      msg += ` (pokus ${attempts}/3)`;
      showNotification(msg, 'error');
      
      if (attempts >= 3) {
        logger.log('🔓 Recovery mode activated!');
        setTimeout(() => {
          showNotification('⚠️ Recovery mód aktivován!', 'warning');
          showRecoveryModal();
          localStorage.removeItem('admin_login_attempts');
        }, 1000);
      }
    }
  } catch (error) {
    logger.error('Admin login error:', error);
    showNotification('Chyba při přihlašování', 'error');
  }
}

// ============================================================
// USER LOGIN
// ============================================================
async function handleUserLogin() {
  const email = document.getElementById('userEmail').value.trim();
  const password = document.getElementById('userPassword').value.trim();
  
  if (!email || !password) {
    showNotification('Vyplňte email a heslo', 'error');
    return;
  }
  
  const csrfToken = await getCsrfTokenFromForm(loginForm);
  if (!csrfToken) {
    showNotification('Nepodařilo se získat bezpečnostní token. Obnovte stránku.', 'error');
    return;
  }

  try {
    const response = await fetch('app/controllers/login_controller.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      credentials: 'include',
      body: `email=${encodeURIComponent(email)}&password=${encodeURIComponent(password)}&csrf_token=${encodeURIComponent(csrfToken)}`
    });
    
    const data = await response.json();
    
    if (data.status === 'success') {
      // Zobraz welcome modal s vtipem
      showWelcomeModal(data.user.name);
    } else {
      showNotification(data.message || 'Přihlášení selhalo', 'error');
    }
  } catch (error) {
    logger.error('User login error:', error);
    showNotification('Chyba při přihlašování', 'error');
  }
}

// ============================================================
// RECOVERY MODAL - S PROFESIONÁLNÍM STYLEM
// ============================================================
function showRecoveryModal() {
  const styles = `
    .recovery-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 9999; }
    .recovery-modal { background: linear-gradient(135deg, #f5f5f5 0%, #ffffff 100%); padding: 2.5rem; border-radius: 12px; max-width: 450px; width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.2); border: 1px solid #e0e0e0; }
    .recovery-modal h2 { margin: 0 0 0.5rem 0; color: #1a1a1a; font-size: 1.8rem; font-weight: 600; }
    .recovery-modal p { color: #666; margin: 0.5rem 0 1.5rem 0; font-size: 0.95rem; }
    .recovery-modal input { width: 100%; padding: 0.875rem 1rem; border: 1px solid #ddd; border-radius: 6px; font-size: 1rem; margin-bottom: 1.5rem; transition: all 0.3s; box-sizing: border-box; }
    .recovery-modal input:focus { outline: none; border-color: #007bff; box-shadow: 0 0 0 3px rgba(0,123,255,0.1); }
    .recovery-buttons { display: flex; gap: 1rem; }
    .recovery-btn { flex: 1; padding: 0.875rem 1.5rem; border: none; border-radius: 6px; font-size: 1rem; font-weight: 500; cursor: pointer; transition: all 0.3s; }
    .recovery-btn-primary { background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white; }
    .recovery-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,123,255,0.3); }
    .recovery-btn-secondary { background: #f0f0f0; color: #333; border: 1px solid #ddd; }
    .recovery-btn-secondary:hover { background: #e8e8e8; }
  `;
  
  const html = `
    <style>${styles}</style>
    <div class="recovery-overlay">
      <div class="recovery-modal">
        <h2>🔐 Recovery Mód</h2>
        <p>Zadej high key pro obnovení admin klíče</p>
        
        <input type="password" id="recoveryHighKey" placeholder="High Key" autocomplete="off">
        
        <div class="recovery-buttons">
          <button class="recovery-btn recovery-btn-primary" onclick="verifyHighKey()">Ověřit</button>
          <button class="recovery-btn recovery-btn-secondary" onclick="closeRecoveryModal()">Zrušit</button>
        </div>
      </div>
    </div>
  `;
  
  document.body.insertAdjacentHTML('beforeend', html);
  document.getElementById('recoveryHighKey').focus();
}

function closeRecoveryModal() {
  const overlay = document.querySelector('.recovery-overlay');
  if (overlay) overlay.remove();
}

async function verifyHighKey() {
  const highKey = document.getElementById('recoveryHighKey').value.trim();
  
  if (!highKey) {
    showNotification('Vyplňte high key', 'error');
    return;
  }
  
  try {
    const csrfToken = await getCsrfTokenFromForm(loginForm);
    if (!csrfToken) {
      showNotification('Nepodařilo se získat bezpečnostní token. Obnovte stránku.', 'error');
      return;
    }

    const response = await fetch('app/controllers/login_controller.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      credentials: 'include',
      body: `high_key=${encodeURIComponent(highKey)}&csrf_token=${encodeURIComponent(csrfToken)}`
    });
    
    const data = await response.json();
    
    if (data.status === 'success') {
      showNotification('✅ High key ověřen!', 'success');
      closeRecoveryModal();
      setTimeout(() => showCreateNewAdminKeyModal(), 500);
    } else {
      showNotification('❌ Neplatný high key', 'error');
    }
  } catch (error) {
    logger.error('High key error:', error);
  }
}

function showCreateNewAdminKeyModal() {
  const styles = `
    .newkey-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 9999; }
    .newkey-modal { background: linear-gradient(135deg, #f5f5f5 0%, #ffffff 100%); padding: 2.5rem; border-radius: 12px; max-width: 450px; width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.2); border: 1px solid #e0e0e0; }
    .newkey-modal h2 { margin: 0 0 1.5rem 0; color: #1a1a1a; font-size: 1.8rem; font-weight: 600; }
    .newkey-modal input { width: 100%; padding: 0.875rem 1rem; border: 1px solid #ddd; border-radius: 6px; font-size: 1rem; margin-bottom: 1rem; transition: all 0.3s; box-sizing: border-box; }
    .newkey-modal input:focus { outline: none; border-color: #28a745; box-shadow: 0 0 0 3px rgba(40,167,69,0.1); }
    .newkey-buttons { display: flex; gap: 1rem; }
    .newkey-btn { flex: 1; padding: 0.875rem 1.5rem; border: none; border-radius: 6px; font-size: 1rem; font-weight: 500; cursor: pointer; transition: all 0.3s; }
    .newkey-btn-success { background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%); color: white; }
    .newkey-btn-success:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(40,167,69,0.3); }
    .newkey-btn-cancel { background: #f0f0f0; color: #333; border: 1px solid #ddd; }
    .newkey-btn-cancel:hover { background: #e8e8e8; }
    .newkey-hint { font-size: 0.85rem; color: #999; margin-top: 0.5rem; }
  `;
  
  const html = `
    <style>${styles}</style>
    <div class="newkey-overlay">
      <div class="newkey-modal">
        <h2>🔑 Nový Admin Klíč</h2>
        
        <input type="password" id="newAdminKey" placeholder="Nový klíč (min. 12 znaků)" minlength="12" autocomplete="off">
        <div class="newkey-hint">Minimálně 12 znaků</div>
        
        <input type="password" id="newAdminKeyConfirm" placeholder="Potvrzení klíče" minlength="12" autocomplete="off" style="margin-top: 0.5rem;">
        
        <div class="newkey-buttons">
          <button class="newkey-btn newkey-btn-success" onclick="createNewAdminKey()">Vytvořit Klíč</button>
          <button class="newkey-btn newkey-btn-cancel" onclick="closeNewAdminKeyModal()">Zrušit</button>
        </div>
      </div>
    </div>
  `;
  
  document.body.insertAdjacentHTML('beforeend', html);
  document.getElementById('newAdminKey').focus();
}

function closeNewAdminKeyModal() {
  const overlay = document.querySelector('.newkey-overlay');
  if (overlay) overlay.remove();
}

async function createNewAdminKey() {
  const key = document.getElementById('newAdminKey').value.trim();
  const keyConfirm = document.getElementById('newAdminKeyConfirm').value.trim();
  
  if (!key || !keyConfirm) {
    showNotification('Vyplňte oba klíče', 'error');
    return;
  }
  
  if (key !== keyConfirm) {
    showNotification('Klíče se neshodují', 'error');
    return;
  }
  
  if (key.length < 12) {
    showNotification('Klíč musí mít alespoň 12 znaků', 'error');
    return;
  }
  
  try {
    const csrfToken = await getCsrfTokenFromForm(loginForm);
    if (!csrfToken) {
      showNotification('Nepodařilo se získat bezpečnostní token. Obnovte stránku.', 'error');
      return;
    }

    const response = await fetch('app/controllers/login_controller.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      credentials: 'include',
      body: `action=create_new_admin_key&new_admin_key=${encodeURIComponent(key)}&new_admin_key_confirm=${encodeURIComponent(keyConfirm)}&csrf_token=${encodeURIComponent(csrfToken)}`
    });
    
    const data = await response.json();
    
    if (data.status === 'success') {
      showNotification('✅ Klíč vytvořen! Restartuju...', 'success');
      closeNewAdminKeyModal();
      setTimeout(() => location.reload(), 2000);
    } else {
      showNotification('❌ ' + (data.message || 'Chyba'), 'error');
    }
  } catch (error) {
    logger.error('Create key error:', error);
  }
}

// ============================================================
// NOTIFICATIONS
// ============================================================
function showNotification(message, type = 'info') {
  const notification = document.getElementById('notification');
  if (!notification) return;
  
  notification.textContent = message;
  notification.className = `notification ${type}`;
  notification.style.display = 'block';
  
  if (type !== 'error') {
    setTimeout(() => {
      notification.style.display = 'none';
    }, 3000);
  }
}

logger.log('✅ Login system loaded');
