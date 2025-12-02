/**
 * Rozbalovací sekce zákaznických dat pro photocustomer.php
 * POUZE MOBIL (≤768px)
 * Umožňuje technikům schovat statická data a vidět jen jméno zákazníka
 */

(function() {
  // Zkontrolovat zda jsme na mobilu (≤768px)
  function jeMobil() {
    return window.innerWidth <= 768;
  }

  // Inicializace collapsible sekce
  function initCustomerInfoCollapse() {
    const toggle = document.getElementById('customerInfoToggle');
    const collapsible = document.querySelector('.customer-info-collapsible');
    const content = document.getElementById('customerInfoContent');

    if (!toggle || !collapsible || !content) {
      return; // Elementy neexistují
    }

    // Kontrola localStorage pro uložený stav
    const savedState = localStorage.getItem('photocustomer-info-expanded');
    const isExpanded = savedState === 'true';

    // Funkce pro aktualizaci aria-expanded
    function aktualizovatAriaExpanded(rozbalenoStav) {
      toggle.setAttribute('aria-expanded', rozbalenoStav ? 'true' : 'false');
      toggle.setAttribute('aria-label', rozbalenoStav ? 'Sbalit informace o zákazníkovi' : 'Rozbalit informace o zákazníkovi');
    }

    // Nastavit počáteční stav
    if (jeMobil()) {
      if (isExpanded) {
        collapsible.classList.add('expanded');
      }
      aktualizovatAriaExpanded(isExpanded);
    } else {
      // Na desktopu vždy rozbaleno
      collapsible.classList.add('expanded');
      aktualizovatAriaExpanded(true);
    }

    // Funkce pro přepnutí stavu
    function prepnoutStav() {
      // Funguje pouze na mobilu
      if (!jeMobil()) {
        return;
      }

      const isCurrentlyExpanded = collapsible.classList.contains('expanded');

      if (isCurrentlyExpanded) {
        // Sbalit
        collapsible.classList.remove('expanded');
        localStorage.setItem('photocustomer-info-expanded', 'false');
        aktualizovatAriaExpanded(false);
        logger.log('Zakaznicke udaje sbaleny');
      } else {
        // Rozbalit
        collapsible.classList.add('expanded');
        localStorage.setItem('photocustomer-info-expanded', 'true');
        aktualizovatAriaExpanded(true);
        logger.log('Zakaznicke udaje rozbaleny');
      }
    }

    // Event listener pro toggle - kliknutí
    toggle.addEventListener('click', prepnoutStav);

    // Event listener pro toggle - klávesnice (Enter/Space)
    toggle.addEventListener('keydown', function(e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        prepnoutStav();
      }
    });

    // Re-check při změně velikosti okna
    window.addEventListener('resize', function() {
      if (!jeMobil()) {
        // Na desktopu vždy rozbaleno
        collapsible.classList.add('expanded');
        aktualizovatAriaExpanded(true);
      }
    });

    logger.log('Customer info collapse inicializován (photocustomer)');
  }

  // Inicializovat po načtení DOMu
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCustomerInfoCollapse);
  } else {
    initCustomerInfoCollapse();
  }
})();
