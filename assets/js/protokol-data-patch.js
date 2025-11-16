/**
 * Protokol Data Loading Patch
 *
 * PROBLÉM: loadReklamace() v protokol.min.js má špatnou prioritu:
 * 1. Kontroluje localStorage NEJDŘÍV
 * 2. Pokud najde data, IGNORUJE ID z URL parametru
 * 3. Načte staré/nesprávné data z minulé session
 *
 * ŘEŠENÍ: Přepsat loadReklamace() a po načtení patche zavolat reload
 */

logger.log('🔧 PATCH: protokol-data-patch.js se načítá...');

// Přepsat funkci loadReklamace() s opravenou logikou
window.loadReklamace = async function(id) {
  showLoading(true);
  try {
    logger.log('🔍 PATCH: Načítám data zákazníka...');
    logger.log('📋 PATCH: ID z parametru:', id);

    // ✅ OPRAVENO: Pokud je ID v parametru, PRIORITNĚ načti z API
    if (id) {
      logger.log('✅ PATCH: ID zadáno - načítám z API');

      const response = await fetch(`api/protokol_api.php?action=load_reklamace&id=${id}`);
      const result = await response.json();

      if (result.status === 'success') {
        logger.log('✅ PATCH: Data načtena z API');
        window.currentReklamace = result.reklamace;

        // Vyplnit formulář
        fillFormWithData(result.reklamace);

        // ✅ OPRAVENO: Použít reklamace_id (ne database ID!)
        window.currentReklamaceId = result.reklamace.reklamace_id || result.reklamace.cislo || result.reklamace.id;
        logger.log('📋 PATCH: currentReklamaceId nastaveno na:', window.currentReklamaceId);

        showNotif("success", "✓ Data načtena");
        showLoading(false);
        return;
      } else {
        logger.warn('⚠️ PATCH: API nenalezlo reklamaci, zkouším localStorage...');
        // Pokračuj do localStorage fallback
      }
    }

    // Fallback: zkusit localStorage (pouze když API selhalo nebo není ID)
    const localData = localStorage.getItem('currentCustomer');
    if (localData) {
      logger.log('✅ PATCH: Data nalezena v localStorage (fallback)');
      const customer = JSON.parse(localData);
      logger.log('📦 PATCH: Data zákazníka:', customer);

      // KONTROLA OPRÁVNĚNÍ
      const currentUser = JSON.parse(localStorage.getItem('currentUser') || '{}');
      logger.log('👤 PATCH: Aktuální uživatel:', currentUser.name, '| Role:', currentUser.role);

      if (currentUser.role === 'prodejce') {
        if (customer.zpracoval_id && customer.zpracoval_id !== currentUser.id) {
          showNotif('error', 'Nemáte oprávnění k této zakázce');
          setTimeout(() => window.location.href = 'seznam.php', 2000);
          showLoading(false);
          return;
        }
      }

      logger.log('✅ PATCH: Oprávnění potvrzeno');

      // Vyplnit formulář
      fillFormWithData(customer);

      window.currentReklamace = customer;
      // ✅ OPRAVENO: Prioritně reklamace_id!
      window.currentReklamaceId = customer.reklamace_id || customer.cislo || customer.id;
      logger.log('📋 PATCH: currentReklamaceId z localStorage:', window.currentReklamaceId);

      showNotif("success", "✓ Data načtena");
      showLoading(false);
      return;
    }

    // Žádná data nenalezena
    logger.error('❌ PATCH: ID nenalezeno v URL ani localStorage');
    showNotif("error", "Chybí ID reklamace");
    showLoading(false);

  } catch (error) {
    logger.error('❌ PATCH: Chyba načítání:', error);
    showNotif("error", "Chyba načítání");
  } finally {
    showLoading(false);
  }
};

// Helper funkce pro vyplnění formuláře
function fillFormWithData(data) {
  const customerName = data.jmeno || data.zakaznik || '';

  // Adresa
  let ulice = '', mesto = '', psc = '';
  if (data.adresa) {
    const parts = data.adresa.split(',').map(s => s.trim());
    ulice = parts[0] || '';
    mesto = parts[1] || '';
    psc = parts[2] || '';
    logger.log('📍 PATCH: Adresa (formát s čárkami):', {ulice, mesto, psc});
  } else {
    ulice = data.ulice || '';
    mesto = data.mesto || '';
    psc = data.psc || '';
    logger.log('📍 PATCH: Adresa (separátní pole):', {ulice, mesto, psc});
  }

  logger.log('📝 PATCH: Vyplňuji formulář...');

  // ✅ OPRAVENO: claim-number používá reklamace_id (ne database ID)
  const orderField = document.getElementById("order-number");
  const claimField = document.getElementById("claim-number");
  const customerField = document.getElementById("customer");
  const addressField = document.getElementById("address");
  const phoneField = document.getElementById("phone");
  const emailField = document.getElementById("email");
  const brandField = document.getElementById("brand");
  const modelField = document.getElementById("model");
  const descField = document.getElementById("description-cz");

  if (orderField) orderField.value = data.objednavka || data.cislo || "";
  if (claimField) claimField.value = data.reklamace_id || data.cislo || "";
  if (customerField) customerField.value = customerName;
  if (addressField) addressField.value = data.adresa || [ulice, mesto, psc].filter(x => x).join(', ');
  if (phoneField) phoneField.value = data.telefon || "";
  if (emailField) emailField.value = data.email || "";
  if (brandField) brandField.value = data.znacka || data.model || "";
  if (modelField) modelField.value = data.model || "";
  if (descField) descField.value = data.popis_problemu || "";

  logger.log('✅ PATCH: Formulář vyplněn');
}

// ✅ OKAMŽITĚ PO NAČTENÍ: Reload data s správným ID
(async function() {
  // Počkat chvilku až se načte protokol.min.js (defer znamená paralelní načítání)
  await new Promise(resolve => setTimeout(resolve, 100));

  const urlParams = new URLSearchParams(window.location.search);
  const reklamaceId = urlParams.get('id');

  if (reklamaceId) {
    logger.log('🔄 PATCH: Automatický reload dat s ID z URL:', reklamaceId);
    await window.loadReklamace(reklamaceId);

    // Reload fotek se správným ID
    if (window.currentReklamaceId && typeof window.loadPhotosFromDatabase === 'function') {
      logger.log('📸 PATCH: Reload fotek s ID:', window.currentReklamaceId);
      window.loadPhotosFromDatabase(window.currentReklamaceId);
    }
  } else {
    logger.warn('⚠️ PATCH: Žádné ID v URL, používám původní data');
  }
})();

logger.log('🔧 PATCH: protokol-data-patch.js načten - loadReklamace() přepsána a data reloadována');
