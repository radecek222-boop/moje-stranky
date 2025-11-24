/**
 * WGS - Welcome Modal s vtipem
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
    // Získej vtip z API
    const jokeResponse = await fetch('app/controllers/get_joke.php?t=' + Date.now());

    // ✅ FIX: Kontrola HTTP status code
    if (!jokeResponse.ok) {
      throw new Error(`HTTP ${jokeResponse.status}`);
    }

    const jokeData = await jokeResponse.json();

    // ✅ DEBUG: Výpis co API vrátilo
    console.log('=== WELCOME MODAL DEBUG ===');
    console.log('Response data:', jokeData);
    console.log('Source:', jokeData.source);
    console.log('Joke:', jokeData.joke);
    if (jokeData.debug) {
      console.log('Debug info:', jokeData.debug);
    }
    console.log('=========================');

    const joke = jokeData.joke || 'Přeji ti krásný den! 😊';

    // BEZPEČNOST: Escape HTML v userName a joke pro XSS protection
    const safeUserName = escapeHtml(userName);
    const safeJoke = escapeHtml(joke);

    // Vytvoř modal HTML (bez inline onclick)
    const modalHTML = `
      <div class="welcome-modal-overlay" id="welcomeModal">
        <div class="welcome-modal">
          <h1 class="welcome-title">Vítej!</h1>
          <div class="welcome-name">${safeUserName}</div>
          <p class="welcome-message">
            Přeji ti hezký den a posílám ti něco pro zasmání,
            protože úsměv dělá den hezčím! 😊
          </p>
          <div class="welcome-joke">
            ${safeJoke}
          </div>
          <button class="welcome-close-btn" id="welcomeCloseBtn">
            Začít pracovat
          </button>
        </div>
      </div>
    `;

    // Přidej do stránky
    document.body.insertAdjacentHTML('beforeend', modalHTML);

    // ✅ FIX: Přidat event listener místo inline onclick
    document.getElementById('welcomeCloseBtn').addEventListener('click', () => {
      closeWelcomeModal(userRole || 'user');
    });

    // Zobraz modal
    setTimeout(() => {
      document.getElementById('welcomeModal').classList.add('active');
    }, 100);

  } catch (error) {
    console.error('Chyba při načítání vtipu:', error);
    // Zobraz modal i bez vtipu
    showFallbackModal(userName, userRole);
  }
}

function showFallbackModal(userName, userRole) {
  // BEZPEČNOST: Escape HTML v userName pro XSS protection
  const safeUserName = escapeHtml(userName);

  const modalHTML = `
    <div class="welcome-modal-overlay active" id="welcomeModal">
      <div class="welcome-modal">
        <h1 class="welcome-title">Vítej!</h1>
        <div class="welcome-name">${safeUserName}</div>
        <p class="welcome-message">
          Přeji ti hezký den plný úspěchů! 💪
        </p>
        <button class="welcome-close-btn" id="welcomeCloseBtnFallback">
          Začít pracovat
        </button>
      </div>
    </div>
  `;
  document.body.insertAdjacentHTML('beforeend', modalHTML);

  // ✅ FIX: Přidat event listener místo inline onclick
  document.getElementById('welcomeCloseBtnFallback').addEventListener('click', () => {
    closeWelcomeModal(userRole || 'user');
  });
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
