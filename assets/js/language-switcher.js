/**
 * WGS Language Switcher - Centralizovaný jazykový přepínač
 * Podporuje: Čeština (cs), Angličtina (en), Italština (it)
 */

(function() {
  'use strict';

  // Aktuální jazyk - načíst z localStorage nebo defaultně čeština
  let aktualniJazyk = localStorage.getItem('wgs-lang') || 'cs';

  /**
   * Přepne jazyk na celé stránce
   * @param {string} jazyk - Kód jazyka: 'cs', 'en', 'it'
   */
  window.prepniJazyk = function(jazyk) {
    if (!['cs', 'en', 'it'].includes(jazyk)) {
      console.warn('Nepodporovaný jazyk:', jazyk);
      return;
    }

    aktualniJazyk = jazyk;
    localStorage.setItem('wgs-lang', jazyk);
    document.documentElement.lang = jazyk;

    // Aktualizovat všechny elementy s data-lang atributy
    aktualizujTexty(jazyk);

    // Aktualizovat placeholdery
    aktualizujPlaceholdery(jazyk);

    // Aktualizovat aktivní vlajku
    aktualizujVlajky(jazyk);

    // Aktualizovat title stránky (pokud existuje)
    aktualizujTitle(jazyk);

    console.log('Jazyk přepnut na:', jazyk);
  };

  /**
   * Aktualizuje textový obsah elementů podle data-lang atributů
   */
  function aktualizujTexty(jazyk) {
    document.querySelectorAll('[data-lang-cs]').forEach(element => {
      const preklad = element.getAttribute(`data-lang-${jazyk}`);
      if (!preklad) return;

      // Skip BR tagy
      if (element.tagName === 'BR') return;

      // BEZPEČNOST: Sanitizace XSS - povolit POUZE <br> tagy
      if (preklad.includes('<br>')) {
        const sanitizovany = preklad
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#039;')
          // Povolit zpět pouze <br> a <br/> tagy
          .replace(/&lt;br\s*\/?&gt;/gi, '<br>');
        element.innerHTML = sanitizovany;
      } else if (element.tagName === 'A' || element.tagName === 'BUTTON') {
        // Pro odkazy a tlačítka - zachovat pouze text, ne HTML strukturu
        if (element.querySelector('span, img, svg')) {
          // Element má vnořené prvky - nechat beze změny
          return;
        }
        element.textContent = preklad;
      } else {
        // Běžné elementy - použít textContent
        element.textContent = preklad;
      }
    });
  }

  /**
   * Aktualizuje placeholdery inputů podle data-lang-X-placeholder atributů
   */
  function aktualizujPlaceholdery(jazyk) {
    document.querySelectorAll('[data-lang-cs-placeholder]').forEach(element => {
      const placeholder = element.getAttribute(`data-lang-${jazyk}-placeholder`);
      if (placeholder && (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA')) {
        element.placeholder = placeholder;
      }
    });
  }

  /**
   * Aktualizuje aktivní vlajku (přidá/odebere třídu 'active')
   */
  function aktualizujVlajky(jazyk) {
    document.querySelectorAll('.lang-flag').forEach(vlajka => {
      vlajka.classList.remove('active');
      if (vlajka.dataset.lang === jazyk) {
        vlajka.classList.add('active');
      }
    });
  }

  /**
   * Aktualizuje title stránky podle jazyka
   */
  function aktualizujTitle(jazyk) {
    // Každá stránka může mít vlastní titulky
    const strankaSlug = document.body.dataset.page || getPageSlug();
    const titulky = getTitulkyProStranku(strankaSlug);

    if (titulky && titulky[jazyk]) {
      document.title = titulky[jazyk];
    }
  }

  /**
   * Získá slug aktuální stránky z URL
   */
  function getPageSlug() {
    const path = window.location.pathname;
    const filename = path.split('/').pop();
    return filename.replace('.php', '');
  }

  /**
   * Vrátí titulky pro jednotlivé stránky
   */
  function getTitulkyProStranku(slug) {
    const titulky = {
      'index': {
        cs: 'White Glove Service – Domů',
        en: 'White Glove Service – Home',
        it: 'White Glove Service – Home'
      },
      'novareklamace': {
        cs: 'Nová reklamace | White Glove Service',
        en: 'New Claim | White Glove Service',
        it: 'Nuovo Reclamo | White Glove Service'
      },
      'seznam': {
        cs: 'Přehled reklamací | White Glove Service',
        en: 'Claims Overview | White Glove Service',
        it: 'Panoramica Reclami | White Glove Service'
      },
      'statistiky': {
        cs: 'Statistiky | White Glove Service',
        en: 'Statistics | White Glove Service',
        it: 'Statistiche | White Glove Service'
      },
      'protokol': {
        cs: 'Servisní protokol | White Glove Service',
        en: 'Service Protocol | White Glove Service',
        it: 'Protocollo di Servizio | White Glove Service'
      },
      'admin': {
        cs: 'Admin panel | White Glove Service',
        en: 'Admin Panel | White Glove Service',
        it: 'Pannello Admin | White Glove Service'
      },
      'login': {
        cs: 'Přihlášení | White Glove Service',
        en: 'Login | White Glove Service',
        it: 'Accesso | White Glove Service'
      },
      'onas': {
        cs: 'O nás | White Glove Service',
        en: 'About Us | White Glove Service',
        it: 'Chi Siamo | White Glove Service'
      },
      'nasesluzby': {
        cs: 'Naše služby | White Glove Service',
        en: 'Our Services | White Glove Service',
        it: 'I Nostri Servizi | White Glove Service'
      }
    };

    return titulky[slug] || null;
  }

  /**
   * Získá aktuální jazyk
   */
  window.ziskejAktualniJazyk = function() {
    return aktualniJazyk;
  };

  /**
   * Inicializace při načtení stránky
   */
  function inicializujJazyk() {
    // Nastavit lang atribut na HTML elementu
    document.documentElement.lang = aktualniJazyk;

    // Pokud není čeština, aplikovat uložený jazyk
    if (aktualniJazyk !== 'cs') {
      prepniJazyk(aktualniJazyk);
    }

    // Přidat event listenery na vlajky
    document.querySelectorAll('.lang-flag').forEach(vlajka => {
      vlajka.addEventListener('click', () => {
        prepniJazyk(vlajka.dataset.lang);
      });
    });

    // Nastavit aktivní vlajku
    aktualizujVlajky(aktualniJazyk);

    console.log('WGS Language Switcher inicializován. Aktuální jazyk:', aktualniJazyk);
  }

  // Spustit inicializaci při načtení DOMu
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', inicializujJazyk);
  } else {
    inicializujJazyk();
  }

})();
