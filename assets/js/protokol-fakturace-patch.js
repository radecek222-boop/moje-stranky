/**
 * PATCH: Přidání zobrazení CZ/SK fakturace do protokolu
 * Načítá se po protokol.min.js a doplňuje fakturace_firma functionality
 */

(function() {
  logger.log('Protokol fakturace patch loaded');

  // Přepsat původní loadReklamace funkci s podporou fakturace_firma
  const originalLoadReklamace = window.loadReklamace;

  window.loadReklamace = async function(id) {
    // Zavolat původní funkci
    await originalLoadReklamace(id);

    // Doplnit fakturaci po načtení dat
    setTimeout(() => {
      const customer = currentReklamace || JSON.parse(localStorage.getItem('currentCustomer') || '{}');
      const fakturaceFirma = (customer.fakturace_firma || 'cz').toUpperCase(); // DB používá lowercase
      const fakturaceField = document.getElementById('fakturace-firma');

      if (fakturaceField) {
        if (fakturaceFirma === 'CZ') {
          fakturaceField.value = '🇨🇿 Česká republika (CZ)';
        } else if (fakturaceFirma === 'SK') {
          fakturaceField.value = '🇸🇰 Slovensko (SK)';
        }
        logger.log(`Fakturace nastavena: ${fakturaceFirma}`);
      }
    }, 100);
  };

  logger.log('Protokol fakturace patch ready');
})();
