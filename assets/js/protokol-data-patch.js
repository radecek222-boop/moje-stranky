/**
 * Protokol Data Loading Patch
 *
 * PROBLÉM: loadReklamace() v protokol.min.js má špatnou prioritu:
 * 1. Kontroluje localStorage NEJDŘÍV
 * 2. Pokud najde data, IGNORUJE ID z URL parametru
 * 3. Načte staré/nesprávné data z minulé session
 *
 * ŘEŠENÍ: Přepsat loadReklamace() s správnou prioritou:
 * 1. Pokud je ID v parametru, VŽDY načti z API
 * 2. localStorage pouze jako fallback
 */

// Přepsat funkci loadReklamace() s opravenou logikou
async function loadReklamace(id) {
  showLoading(true);
  try {
    logger.log('🔍 PATCH: Načítám data zákazníka...');
    logger.log('📋 PATCH: ID z URL:', id);

    // ✅ OPRAVENO: Pokud je ID v parametru, PRIORITNĚ načti z API
    if (id) {
      logger.log('✅ PATCH: ID zadáno - načítám z API');

      const response = await fetch(`api/protokol_api.php?action=load_reklamace&id=${id}`);
      const result = await response.json();

      if (result.status === 'success') {
        logger.log('✅ PATCH: Data načtena z API');
        currentReklamace = result.reklamace;

        // Vyplnit formulář
        fillFormWithData(currentReklamace);

        // ✅ OPRAVENO: Použít reklamace_id (ne database ID!)
        currentReklamaceId = currentReklamace.reklamace_id || currentReklamace.cislo || currentReklamace.id;
        logger.log('📋 PATCH: currentReklamaceId nastaveno na:', currentReklamaceId);

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

      currentReklamace = customer;
      // ✅ OPRAVENO: Prioritně reklamace_id!
      currentReklamaceId = customer.reklamace_id || customer.cislo || customer.id;
      logger.log('📋 PATCH: currentReklamaceId z localStorage:', currentReklamaceId);

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
}

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
  document.getElementById("order-number").value = data.objednavka || data.cislo || "";
  document.getElementById("claim-number").value = data.reklamace_id || data.cislo || "";
  document.getElementById("customer").value = customerName;
  document.getElementById("address").value = data.adresa || [ulice, mesto, psc].filter(x => x).join(', ');
  document.getElementById("phone").value = data.telefon || "";
  document.getElementById("email").value = data.email || "";
  document.getElementById("brand").value = data.znacka || data.model || "";
  document.getElementById("model").value = data.model || "";
  document.getElementById("description-cz").value = data.popis_problemu || "";

  logger.log('✅ PATCH: Formulář vyplněn');
}

// ✅ OPRAVENO: Přepsat DOMContentLoaded handler aby používal SPRÁVNÉ ID pro fotky
// Původní handler v protokol.min.js volá loadPhotosFromDatabase() s špatným ID
window.addEventListener("DOMContentLoaded", async () => {
  logger.log('🚀 PATCH: Inicializace protokolu (přepsaná verze)...');

  initSignaturePad();

  const urlParams = new URLSearchParams(window.location.search);
  currentReklamaceId = urlParams.get('id');

  logger.log('📋 PATCH: ID z URL:', currentReklamaceId);

  if (currentReklamaceId) {
    logger.log('✅ PATCH: ID nalezeno v URL');
    await loadReklamace(currentReklamaceId);

    // ✅ OPRAVENO: Použít currentReklamaceId z loadReklamace() (reklamace_id, ne database ID)
    if (currentReklamaceId) {
      logger.log('📸 PATCH: Načítám fotky s ID:', currentReklamaceId);
      loadPhotosFromDatabase(currentReklamaceId);
    }
  } else {
    logger.warn('⚠️ PATCH: Chybí ID v URL - zkusím načíst z localStorage');
    await loadReklamace(null);

    if (currentReklamace && (currentReklamace.reklamace_id || currentReklamace.cislo || currentReklamace.id)) {
      // ✅ OPRAVENO: Prioritně reklamace_id
      currentReklamaceId = currentReklamace.reklamace_id || currentReklamace.cislo || currentReklamace.id;
      logger.log('✅ PATCH: ID nalezeno v načtených datech:', currentReklamaceId);
      loadPhotosFromDatabase(currentReklamaceId);
    } else {
      logger.error('❌ PATCH: ID se nepodařilo najít!');
    }
  }

  const today = new Date().toISOString().split('T')[0];
  document.getElementById("sign-date").value = today;
  document.getElementById("visit-date").value = today;

  setupAutoTranslate();
}, { once: false }); // Přidáme handler navíc k originálu

logger.log('🔧 PATCH: protokol-data-patch.js načten - loadReklamace() a DOMContentLoaded přepsány');
