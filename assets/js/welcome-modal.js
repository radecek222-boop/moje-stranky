/**
 * WGS - Welcome Modal s vtipem
 */

async function showWelcomeModal(userName) {
  try {
    // Získej vtip z API
    const jokeResponse = await fetch('app/controllers/get_joke.php?t=' + Date.now());
    const jokeData = await jokeResponse.json();
    const joke = jokeData.joke || 'Přeji ti krásný den! 😊';

    // Vytvoř modal HTML
    const modalHTML = `
      <div class="welcome-modal-overlay" id="welcomeModal">
        <div class="welcome-modal">
          <h1 class="welcome-title">Vítej!</h1>
          <div class="welcome-name">${userName}</div>
          <p class="welcome-message">
            Přeji ti hezký den a posílám ti něco pro zasmání, 
            protože úsměv dělá den hezčím! 😊
          </p>
          <div class="welcome-joke">
            ${joke}
          </div>
          <button class="welcome-close-btn" onclick="closeWelcomeModal()">
            Začít pracovat
          </button>
        </div>
      </div>
    `;

    // Přidej do stránky
    document.body.insertAdjacentHTML('beforeend', modalHTML);

    // Zobraz modal
    setTimeout(() => {
      document.getElementById('welcomeModal').classList.add('active');
    }, 100);

  } catch (error) {
    console.error('Chyba při načítání vtipu:', error);
    // Zobraz modal i bez vtipu
    showFallbackModal(userName);
  }
}

function showFallbackModal(userName) {
  const modalHTML = `
    <div class="welcome-modal-overlay active" id="welcomeModal">
      <div class="welcome-modal">
        <h1 class="welcome-title">Vítej!</h1>
        <div class="welcome-name">${userName}</div>
        <p class="welcome-message">
          Přeji ti hezký den plný úspěchů! 💪
        </p>
        <button class="welcome-close-btn" onclick="closeWelcomeModal()">
          Začít pracovat
        </button>
      </div>
    </div>
  `;
  document.body.insertAdjacentHTML('beforeend', modalHTML);
}

function closeWelcomeModal() {
  const modal = document.getElementById("welcomeModal");
  if (modal) {
    modal.classList.remove("active");
    setTimeout(() => {
      modal.remove();
      window.location.href = "novareklamace.php";
    }, 300);
  }
}

// Export pro použití v login.js
window.showWelcomeModal = showWelcomeModal;
