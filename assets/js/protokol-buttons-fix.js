/**
 * FIX: Oprava tlačítek v protokolu
 * BEZPEČNÝ fallback - kontroluje, jestli už handler existuje
 */
(function() {
  console.log('🔧 Protokol Buttons Fix - Kontrola...');

  // Flag pro detekci, jestli už byly handlery nastavené
  let handlersInitialized = false;

  // Počkat na DOMContentLoaded
  document.addEventListener('DOMContentLoaded', () => {
    // Počkat 500ms, aby se protokol.min.js stačil načíst
    setTimeout(() => {
      // Test: Zkusit kliknout na testovací element
      const testDiv = document.createElement('div');
      testDiv.setAttribute('data-action', 'testAction');
      testDiv.style.display = 'none';
      document.body.appendChild(testDiv);

      // Testovací funkce
      let testActionCalled = false;
      window.testAction = function() {
        testActionCalled = true;
      };

      // Simulovat klik
      testDiv.click();

      // Odstranit test element
      document.body.removeChild(testDiv);
      delete window.testAction;

      // Pokud testAction byl zavolán, handlers už existují
      if (testActionCalled) {
        console.log('✅ Event handlers už fungují (protokol.min.js je aktivní)');
        handlersInitialized = true;
        return;
      }

      // Pokud ne, přidat fallback handlers
      console.warn('⚠️ Event handlers NEFUNGUJÍ - přidávám fallback');

      // Fallback handler pro data-action
      document.addEventListener('click', (event) => {
        if (handlersInitialized) return; // Zabránit duplicitě

        const button = event.target.closest('[data-action]');
        if (!button) return;

        const action = button.getAttribute('data-action');
        console.log(`🔘 [FALLBACK] Kliknuto na: ${action}`);

        if (typeof window[action] === 'function') {
          try {
            window[action]();
          } catch (err) {
            console.error(`❌ Chyba při volání ${action}():`, err);
          }
        } else {
          console.error(`❌ Funkce ${action}() NEEXISTUJE`);
        }
      });

      // Fallback handler pro data-navigate
      document.addEventListener('click', (event) => {
        if (handlersInitialized) return; // Zabránit duplicitě

        const button = event.target.closest('[data-navigate]');
        if (!button) return;

        const url = button.getAttribute('data-navigate');
        console.log(`🔘 [FALLBACK] Navigace na: ${url}`);
        window.location.href = url;
      });

      console.log('✅ Fallback event listeners nastaveny');
    }, 500); // Počkat 500ms na načtení protokol.min.js
  });
})();
