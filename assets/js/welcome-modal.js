/**
 * WGS - Welcome Modal - Profesionální uvítání
 */

// BEZPEČNOST: HTML escaping pro prevenci XSS
function escapeHtml(text) {
  if (!text) return '';
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

function showWelcomeModal(userName, userRole) {
  // BEZPEČNOST: Escape HTML v userName pro XSS protection
  const safeUserName = escapeHtml(userName);

  // Vytvoř modal HTML s profesionální uvítací zprávou
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
