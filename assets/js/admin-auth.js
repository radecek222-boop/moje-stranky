
async function checkAdminAuth() {
  // BEZPEČNOST: POUZE serverová session je důvěryhodná
  // localStorage fallbacky odstraněny - umožňovaly bypass autentizace
  try {
    const response = await fetch('app/admin_session_check.php');
    if (response.ok) {
      const result = await response.json();
      if (result.authenticated) {
        CURRENT_USER = {
          name: result.username || result.email || 'Administrator',
          email: result.email || 'admin@wgs-service.cz',
          role: result.role || 'admin',
          id: result.user_id || 'ADMIN_SESSION'
        };
        return true;
      }
    }
  } catch (err) {
    console.error('Admin auth check failed:', err);
  }

  // Pokud server session check selhal, uživatel NENÍ přihlášen
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

async function handleLogout(event) {
  if (!confirm('Opravdu se chcete odhlásit?')) return;

  // ✅ KRITICKÁ OPRAVA: Použít CSRF-protected logout z logout-handler.js
  // Důvod: Přímý GET redirect neposílá CSRF token → zobrazuje se potvrzovací formulář
  if (typeof window.secureLogout === 'function') {
    // Použít globální secure logout handler (POST s CSRF tokenem)
    window.secureLogout(event);
  } else {
    // Fallback: přímý redirect (zobrazí potvrzovací formulář)
    console.warn('secureLogout() není dostupná, používám fallback');
    window.location.href = '/logout.php';
  }
}
