
async function checkAdminAuth() {
  try {
    const response = await fetch('app/admin_session_check.php');
    if (response.ok) {
      const result = await response.json();
      if (result.authenticated) {
        CURRENT_USER = {
          name: result.username || result.email || 'Administrator',
          email: result.email || 'admin@wgs-service.cz',
          role: result.role || 'admin',
          id: 'ADMIN_SESSION'
        };
        return true;
      }
    }
  } catch (err) {}
  
  if (localStorage.getItem('wgsAdmin') === '1') {
    CURRENT_USER = { name: 'ADMIN', email: 'admin@wgs-service.cz', role: 'admin', id: 'ADMIN_LOCAL' };
    return true;
  }
  
  const userId = localStorage.getItem('wgsCurrent');
  if (userId) {
    const users = JSON.parse(localStorage.getItem('wgsUsers') || '[]');
    CURRENT_USER = users.find(u => u.id === userId);
    if (CURRENT_USER) return true;
  }
  
  return false;
}

async function initAuth() {
  if (!await checkAdminAuth()) {
    alert('Musíte být přihlášeni');
    window.location.href = 'login.php';
    return false;
  }
  const el = document.getElementById('userName');
  if (el && CURRENT_USER) el.textContent = CURRENT_USER.name;
  return true;
}

async function handleLogout() {
  if (!confirm('Opravdu se chcete odhlásit?')) return;
  try {
    await fetch('api/admin_api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'logout' })
    });
  } catch (err) {}
  localStorage.removeItem('wgsAdmin');
  localStorage.removeItem('wgsCurrent');
  window.location.href = 'login.php';
}
