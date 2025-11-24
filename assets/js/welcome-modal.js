/**
 * WGS - Welcome Modal - Profesionální uvítání se statistikami
 */

// BEZPEČNOST: HTML escaping pro prevenci XSS
function escapeHtml(text) {
  if (!text) return '';
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

async function showWelcomeModal(userName, userRole) {
  try {
    // BEZPEČNOST: Escape HTML v userName pro XSS protection
    const safeUserName = escapeHtml(userName);

    // Načíst statistiky z API
    const statsResponse = await fetch('api/get_user_stats.php?t=' + Date.now());

    let statsHTML = '';
    if (statsResponse.ok) {
      const statsData = await statsResponse.json();

      if (statsData.status === 'success') {
        const stats = statsData.stats;

        statsHTML = `
          <div class="welcome-stats">
            <div class="stat-item">
              <div class="stat-number">${stats.nevyreseno}</div>
              <div class="stat-label">Nevyřešených reklamací</div>
            </div>
            <div class="stat-item">
              <div class="stat-number">${stats.hotovo}</div>
              <div class="stat-label">Dokončených reklamací</div>
            </div>
            <div class="stat-item">
              <div class="stat-number">${stats.total}</div>
              <div class="stat-label">Celkem reklamací</div>
            </div>
          </div>
        `;
      }
    }

    // Vytvoř modal HTML s profesionální uvítací zprávou a statistikami
    const modalHTML = `
      <div class="welcome-modal-overlay" id="welcomeModal">
        <div class="welcome-modal">
          <h1 class="welcome-title">Vítej!</h1>
          <div class="welcome-name">${safeUserName}</div>
          <p class="welcome-message">
            Přejeme ti produktivní a příjemný pracovní den.
          </p>
          ${statsHTML}
          <button class="welcome-close-btn" id="welcomeCloseBtn">
            Začít pracovat
          </button>
        </div>
      </div>
    `;

    // Přidej do stránky
    document.body.insertAdjacentHTML('beforeend', modalHTML);

    // Přidat event listener
    document.getElementById('welcomeCloseBtn').addEventListener('click', () => {
      closeWelcomeModal(userRole || 'user');
    });

    // Zobraz modal
    setTimeout(() => {
      document.getElementById('welcomeModal').classList.add('active');
    }, 100);

  } catch (error) {
    console.error('Chyba při načítání welcome modalu:', error);
    // Fallback - zobraz bez statistik
    zobrazZalohaModal(userName, userRole);
  }
}

// Fallback funkce pokud selže načtení statistik
function zobrazZalohaModal(userName, userRole) {
  const safeUserName = escapeHtml(userName);

  const modalHTML = `
    <div class="welcome-modal-overlay" id="welcomeModal">
      <div class="welcome-modal">
        <h1 class="welcome-title">Vítej!</h1>
        <div class="welcome-name">${safeUserName}</div>
        <p class="welcome-message">
          Přejeme ti produktivní a příjemný pracovní den.
        </p>
        <button class="welcome-close-btn" id="welcomeCloseBtn">
          Začít pracovat
        </button>
      </div>
    </div>
  `;

  document.body.insertAdjacentHTML('beforeend', modalHTML);

  document.getElementById('welcomeCloseBtn').addEventListener('click', () => {
    closeWelcomeModal(userRole || 'user');
  });

  setTimeout(() => {
    document.getElementById('welcomeModal').classList.add('active');
  }, 100);
}

function closeWelcomeModal(userRole) {
  const modal = document.getElementById("welcomeModal");
  if (modal) {
    modal.classList.remove("active");
    setTimeout(() => {
      modal.remove();

      // Přesměrování podle role
      // Technici => seznam.php (vidět zakázky)
      // Prodejci/ostatní => novareklamace.php (objednat servis)
      const normalizedRole = (userRole || '').toLowerCase().trim();

      if (normalizedRole === 'technik' || normalizedRole === 'technician') {
        window.location.href = "seznam.php";
      } else {
        window.location.href = "novareklamace.php";
      }
    }, 300);
  }
}

// Export pro použití v login.js
window.showWelcomeModal = showWelcomeModal;
