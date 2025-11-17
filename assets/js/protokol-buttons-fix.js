/**
 * FIX: Oprava tlačítek v protokolu
 * Zajišťuje, že všechna tlačítka s data-action fungují
 */
(function() {
  console.log('🔧 Protokol Buttons Fix - Inicializace...');

  // Ujistit se, že event delegation je nastaven
  document.addEventListener('DOMContentLoaded', () => {
    console.log('📋 Protokol Buttons - Nastavuji event listeners...');

    // Globální handler pro všechna tlačítka s data-action
    document.addEventListener('click', (event) => {
      const button = event.target.closest('[data-action]');
      if (!button) return;

      const action = button.getAttribute('data-action');
      console.log(`🔘 Kliknuto na tlačítko s akcí: ${action}`);

      // Zkontrolovat, jestli funkce existuje
      if (typeof window[action] === 'function') {
        console.log(`✅ Funkce ${action}() nalezena, volám...`);
        try {
          window[action]();
        } catch (err) {
          console.error(`❌ Chyba při volání ${action}():`, err);
        }
      } else {
        console.error(`❌ Funkce ${action}() NEEXISTUJE v globálním scope`);
        console.log('📦 Dostupné funkce:', Object.keys(window).filter(k => typeof window[k] === 'function').slice(0, 20));
      }
    });

    // Globální handler pro data-navigate
    document.addEventListener('click', (event) => {
      const button = event.target.closest('[data-navigate]');
      if (!button) return;

      const url = button.getAttribute('data-navigate');
      console.log(`🔘 Navigace na: ${url}`);
      window.location.href = url;
    });

    console.log('✅ Event listeners nastaveny');
  });
})();
