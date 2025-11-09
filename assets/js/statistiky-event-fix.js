// === FIX: Event delegation nastavení hned po načtení ===
// Tento kód se spustí OKAMŽITĚ po načtení scriptu, ne až po DOMContentLoaded

(function() {
  console.log('=== STATISTIKY EVENT FIX ===');

  function setupEventListeners() {
    console.log('Nastavuji event listenery...');

    // Handle data-multiselect buttons
    document.addEventListener('click', (e) => {
      const multiselect = e.target.closest('[data-multiselect]')?.getAttribute('data-multiselect');
      if (multiselect) {
        console.log('Multi-select clicked:', multiselect);
        if (typeof openMultiSelect === 'function') {
          openMultiSelect(multiselect);
        } else {
          console.error('openMultiSelect function not found!');
        }
      }
    });

    console.log('Event listenery nastaveny!');
  }

  // Pokud je DOM ready, nastav hned, jinak počkej
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupEventListeners);
  } else {
    setupEventListeners();
  }
})();
