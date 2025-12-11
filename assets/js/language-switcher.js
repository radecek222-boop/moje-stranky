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
  window.prepniJazyk = async function(jazyk) {
    if (!['cs', 'en', 'it'].includes(jazyk)) {
      console.warn('Nepodporovaný jazyk:', jazyk);
      return;
    }

    // ADMIN: Na stránce aktualit - automatický překlad při přepnutí na EN/IT
    const jeNaAktualitach = window.location.pathname.includes('aktuality');
    const jeAdmin = document.body.classList.contains('admin-mode') ||
                    document.querySelector('.hamburger-nav.admin-nav-active') !== null;

    if (jeNaAktualitach && jeAdmin && (jazyk === 'en' || jazyk === 'it')) {
      await spustitPrekladAktualit(jazyk);
    }

    aktualniJazyk = jazyk;
    localStorage.setItem('wgs-lang', jazyk);
    document.documentElement.lang = jazyk;

    // Na stránce aktualit přesměrovat s parametrem ?lang=
    if (jeNaAktualitach) {
      const url = new URL(window.location.href);
      url.searchParams.set('lang', jazyk === 'cs' ? 'cz' : jazyk);
      window.location.href = url.toString();
      return;
    }

    // Aktualizovat všechny elementy s data-lang atributy
    aktualizujTexty(jazyk);

    // Aktualizovat placeholdery
    aktualizujPlaceholdery(jazyk);

    // Aktualizovat title atributy (tooltips)
    aktualizujTitleAtributy(jazyk);

    // Aktualizovat aktivní vlajku
    aktualizujVlajky(jazyk);

    // Aktualizovat title stránky (pokud existuje)
    aktualizujTitle(jazyk);
  };

  /**
   * Spustí automatický překlad všech aktualit do cílového jazyka (pouze admin)
   * @param {string} cilovyJazyk - 'en' nebo 'it'
   */
  async function spustitPrekladAktualit(cilovyJazyk) {
    try {
      // Zobrazit indikátor načítání
      const loadingDiv = document.createElement('div');
      loadingDiv.id = 'preklad-loading';
      loadingDiv.style.cssText = `
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: rgba(0,0,0,0.9);
        color: #39ff14;
        padding: 30px 50px;
        border-radius: 10px;
        z-index: 99999;
        font-size: 1.2em;
        text-align: center;
        border: 2px solid #39ff14;
        box-shadow: 0 0 30px rgba(57, 255, 20, 0.3);
      `;
      loadingDiv.innerHTML = `
        <div style="margin-bottom: 15px;">Překládám aktuality do ${cilovyJazyk.toUpperCase()}...</div>
        <div style="font-size: 0.8em; opacity: 0.7;">Max 30 sekund</div>
      `;
      document.body.appendChild(loadingDiv);

      // Zavolat API pro překlad s timeoutem 30s
      const controller = new AbortController();
      const timeoutId = setTimeout(() => controller.abort(), 30000);

      try {
        const response = await fetch(`/api/preloz_aktualitu.php?jazyk=${cilovyJazyk}`, {
          signal: controller.signal
        });
        clearTimeout(timeoutId);
        const data = await response.json();

        if (data.status === 'success') {
          console.log('Překlad dokončen:', data);
        } else {
          console.error('Chyba překladu:', data.message);
        }
      } catch (fetchError) {
        if (fetchError.name === 'AbortError') {
          console.log('Překlad timeout - pokračuji bez čekání');
        } else {
          throw fetchError;
        }
      }

      // Odstranit indikátor
      loadingDiv.remove();

    } catch (error) {
      console.error('Chyba při překladu aktualit:', error);
      // Odstranit loading pokud existuje
      const loading = document.getElementById('preklad-loading');
      if (loading) loading.remove();
    }
  }

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
   * Aktualizuje title atributy elementů podle data-lang-X-title atributů
   */
  function aktualizujTitleAtributy(jazyk) {
    document.querySelectorAll('[data-lang-cs-title]').forEach(element => {
      const title = element.getAttribute(`data-lang-${jazyk}-title`);
      if (title) {
        element.title = title;
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
      },
      'cenik': {
        cs: 'Ceník služeb - White Glove Service | Servis Natuzzi | Praha, Brno',
        en: 'Price List - White Glove Service | Natuzzi Service | Prague, Brno',
        it: 'Listino Prezzi - White Glove Service | Servizio Natuzzi | Praga, Brno'
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
   * Získá překlad pro daný klíč a aktuální jazyk
   * @param {string} klic - Klíč překladu
   * @returns {string} - Přeložený text nebo původní klíč pokud překlad neexistuje
   */
  window.t = function(klic) {
    const preklady = window.WGS_TRANSLATIONS || {};
    return preklady[klic]?.[aktualniJazyk] || klic;
  };

  /**
   * Získá překlad pro daný klíč a konkrétní jazyk
   * @param {string} klic - Klíč překladu
   * @param {string} jazyk - Kód jazyka
   * @returns {string} - Přeložený text
   */
  window.tLang = function(klic, jazyk) {
    const preklady = window.WGS_TRANSLATIONS || {};
    return preklady[klic]?.[jazyk] || klic;
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

    // Přidat event listenery na vlajky - kliknutí i klávesnice
    document.querySelectorAll('.lang-flag').forEach(vlajka => {
      vlajka.addEventListener('click', () => {
        prepniJazyk(vlajka.dataset.lang);
      });
      // Podpora klávesnice (Enter/Space) pro přístupnost
      vlajka.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          prepniJazyk(vlajka.dataset.lang);
        }
      });
    });

    // Nastavit aktivní vlajku
    aktualizujVlajky(aktualniJazyk);
  }

  // Spustit inicializaci při načtení DOMu
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', inicializujJazyk);
  } else {
    inicializujJazyk();
  }

})();
