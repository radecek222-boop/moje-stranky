/**
 * AJAX Router - efekt listovani bez bila stranky
 * Interceptuje nav kliknutí, fetche novou stranku,
 * sliduje <main> dovnitř/ven bez full page reloadu.
 */
(function () {
  'use strict';

  // Poradi v navigaci (leva = nizsi index)
  var NAV_PORADI = [
    '/', '/index.php',
    '/novareklamace.php',
    '/cenik.php',
    '/nasesluzby.php',
    '/onas.php',
    '/aktuality.php',
    '/login.php',
    '/registration.php'
  ];

  var nacitam = false;
  var DOBA_ANIMACE = 380; // ms

  // --- Pomocne funkce ---

  function normPath(path) {
    return path.split('?')[0].replace(/\/$/, '') || '/';
  }

  function ziskIndex(path) {
    var p = normPath(path);
    for (var i = 0; i < NAV_PORADI.length; i++) {
      if (normPath(NAV_PORADI[i]) === p) return i;
    }
    return -1;
  }

  function ziskSmer(odPath, naPath) {
    var od = ziskIndex(odPath);
    var na = ziskIndex(naPath);
    if (od === -1 || na === -1) return 'vpred';
    return na >= od ? 'vpred' : 'zpet';
  }

  function nastav(el, styly) {
    Object.keys(styly).forEach(function (k) { el.style[k] = styly[k]; });
  }

  function smaz(el, klice) {
    klice.forEach(function (k) { el.style[k] = ''; });
  }

  // Nacist CSS soubory ktere nova stranka potrebuje a jeste nejsou v DOM.
  // Zpracovava jak rel="stylesheet" tak rel="preload" as="style" (lazy-load pattern).
  function nactiNoveCss(novyDoc) {
    var existujici = new Set(
      Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
        .map(function (l) { return l.getAttribute('href'); })
    );
    novyDoc.querySelectorAll('link[rel="stylesheet"], link[rel="preload"][as="style"]')
      .forEach(function (l) {
        var href = l.getAttribute('href');
        if (href && !existujici.has(href)) {
          var link = document.createElement('link');
          link.rel = 'stylesheet';
          link.href = href;
          document.head.appendChild(link);
        }
      });
  }

  // Odstranit CSS ktera nova stranka nepotrebuje (volat az po animaci).
  // Povazuje za "potrebne" jak rel="stylesheet" tak rel="preload" as="style".
  function odstraNCss(novyDoc) {
    var noveCss = new Set(
      Array.from(novyDoc.querySelectorAll('link[rel="stylesheet"], link[rel="preload"][as="style"]'))
        .map(function (l) { return l.getAttribute('href'); })
        .filter(Boolean)
    );
    Array.from(document.querySelectorAll('link[rel="stylesheet"]')).forEach(function (l) {
      var href = l.getAttribute('href');
      if (href && !noveCss.has(href)) {
        l.remove();
      }
    });
  }

  // Synchronizovat inline <style> bloky z <head> nove stranky.
  // Volat PRED animaci aby nova stranka vypadala spravne hned pri vjezdu.
  function spravujInlineStyley(novyDoc, urlKlic) {
    // Odstranit inline styly pridane routerem pri predchozi navigaci
    document.querySelectorAll('style[data-ajax-stranka]').forEach(function (s) { s.remove(); });
    // Pridat inline styly z <head> nove stranky
    novyDoc.querySelectorAll('head > style').forEach(function (s) {
      var novy = document.createElement('style');
      novy.textContent = s.textContent;
      novy.setAttribute('data-ajax-stranka', urlKlic);
      document.head.appendChild(novy);
    });
  }

  // Spustit skripty nalezene v novem obsahu
  function spustiSkripty(kontejner) {
    var nacteneSrc = new Set(
      Array.from(document.querySelectorAll('script[src]')).map(function (s) { return s.src; })
    );
    Array.from(kontejner.querySelectorAll('script')).forEach(function (stary) {
      var novy = document.createElement('script');
      Array.from(stary.attributes).forEach(function (a) { novy.setAttribute(a.name, a.value); });
      if (stary.src) {
        if (nacteneSrc.has(stary.src)) { stary.remove(); return; }
        nacteneSrc.add(stary.src);
      } else {
        novy.textContent = stary.textContent;
      }
      document.body.appendChild(novy);
      stary.remove();
    });
  }

  // Aktualizovat aktivni polozku v navigaci
  function aktualizeNav(pathname) {
    var p = normPath(pathname);
    document.querySelectorAll('.hamburger-nav a').forEach(function (a) {
      var href = normPath(a.getAttribute('href') || '');
      var shoda = href === p;
      a.classList.toggle('active', shoda);
      if (shoda) a.setAttribute('aria-current', 'page');
      else a.removeAttribute('aria-current');
    });
  }

  // --- Jadro navigace ---

  function naviguj(url, smer, nahradHistorii) {
    if (nacitam) return;
    nacitam = true;

    var staraStranka = document.getElementById('main-content');
    if (!staraStranka) { window.location.href = url; return; }

    // Zmerit sirku scrollbaru PRED skrytim – kompenzujeme paddingRight
    // aby stranka pri zmizeni scrollbaru neposkocila doprava
    var sirinaSvislehoScrollbaru = window.innerWidth - document.documentElement.clientWidth;
    var sirka = window.innerWidth + 'px';

    // Zamknout vysku body pred tim nez stara stranka opusti flow
    document.body.style.minHeight = document.body.offsetHeight + 'px';
    document.body.style.overflow = 'hidden';
    if (sirinaSvislehoScrollbaru > 0) {
      document.body.style.paddingRight = sirinaSvislehoScrollbaru + 'px';
    }

    // Zarovnat dokument na scroll=0
    var scrollY = window.scrollY;

    // Zafixovat starou stranku na miste aby se mohla pohybovat
    nastav(staraStranka, {
      position: 'fixed',
      top: (-scrollY) + 'px',
      left: '0',
      width: sirka,
      zIndex: '10',
      pointerEvents: 'none',
      willChange: 'transform'
    });

    fetch(url, { headers: { 'X-Requested-With': 'ajax' } })
      .then(function (r) { return r.text(); })
      .then(function (html) {
        var parser = new DOMParser();
        var novyDoc = parser.parseFromString(html, 'text/html');
        var novyMain = novyDoc.getElementById('main-content');

        if (!novyMain) { window.location.href = url; return; }

        // Nacist nova CSS (vcetne preload as style)
        nactiNoveCss(novyDoc);

        // Synchronizovat inline <style> bloky pred animaci
        var urlKlic = normPath(new URL(url, location.origin).pathname);
        spravujInlineStyley(novyDoc, urlKlic);

        // Aktualizovat title + history
        document.title = novyDoc.title;
        if (nahradHistorii) {
          history.replaceState({ url: url, smer: smer }, novyDoc.title, url);
        } else {
          history.pushState({ url: url, smer: smer }, novyDoc.title, url);
        }

        // Sestavit novy main - kopirovat VSECHNY atributy (vcetne x-data, x-init, class)
        var novaStranka = document.createElement('main');
        novaStranka.id = 'main-content';
        Array.from(novyMain.attributes).forEach(function (attr) {
          if (attr.name !== 'id') novaStranka.setAttribute(attr.name, attr.value);
        });
        novaStranka.innerHTML = novyMain.innerHTML;

        // Nova stranka vychazi mimo obrazovku
        var startX = smer === 'vpred' ? '100%' : '-100%';
        nastav(novaStranka, {
          position: 'fixed',
          top: '0',
          left: '0',
          width: sirka,
          transform: 'translateX(' + startX + ')',
          zIndex: '11',
          willChange: 'transform'
        });
        // Vlozit novou stranku PRED starou (spravne poradi v DOM, pred footerem)
        staraStranka.before(novaStranka);

        // Jeden frame pauza, pak spustit animaci
        requestAnimationFrame(function () {
          requestAnimationFrame(function () {
            var venX = smer === 'vpred' ? '-100%' : '100%';
            var prechod = 'transform ' + DOBA_ANIMACE + 'ms cubic-bezier(0.4,0,0.2,1)';

            nastav(staraStranka, { transition: prechod, transform: 'translateX(' + venX + ')' });
            nastav(novaStranka,  { transition: prechod, transform: 'translateX(0)' });
          });
        });

        setTimeout(function () {
          // Uklid po animaci
          staraStranka.remove();
          // Scroll na 0 PRED tim nez nova stranka vstoupi do toku dokumentu –
          // jinak by se vykreslila posunutá a pak skocila (viditelny jolt)
          window.scrollTo(0, 0);
          smaz(novaStranka, ['position', 'top', 'left', 'width', 'transform', 'transition', 'zIndex', 'willChange']);
          // Obnovit overflow a paddingRight najednou – prohlizec to zpracuje v jednom vykreslovacim cyklu
          document.body.style.overflow = '';
          document.body.style.paddingRight = '';
          document.body.style.minHeight = '';

          // Odstranit CSS ktera nova stranka nepotrebuje
          odstraNCss(novyDoc);

          // Spustit skripty z noveho obsahu
          spustiSkripty(novaStranka);

          // Inicializovat Alpine.js na nove strance (pokud je k dispozici)
          if (window.Alpine) {
            window.Alpine.initTree(novaStranka);
          }

          // Aktualizovat nav
          aktualizeNav(new URL(url, location.origin).pathname);

          nacitam = false;
        }, DOBA_ANIMACE + 20);
      })
      .catch(function () {
        window.location.href = url;
        nacitam = false;
      });
  }

  // --- Event listenery ---

  // Zachytit kliknuti na interní odkaz
  document.addEventListener('click', function (e) {
    var odkaz = e.target.closest('a[href]');
    if (!odkaz) return;

    var href = odkaz.getAttribute('href');
    if (!href ||
        odkaz.target === '_blank' ||
        href.startsWith('#') ||
        href.startsWith('mailto:') ||
        href.startsWith('tel:') ||
        href.startsWith('javascript:')) return;

    var url;
    try { url = new URL(href, location.origin); } catch (err) { return; }
    if (url.origin !== location.origin) return;

    // Preskocit klik na aktualni stranku (zadna animace, zadny push do history)
    if (normPath(url.pathname) === normPath(location.pathname)) return;

    // Preskocit non-HTML soubory
    var ext = url.pathname.split('.').pop().toLowerCase();
    if (['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'zip', 'css', 'js', 'svg'].includes(ext)) return;

    // Preskocit stranky vyžadujici full reload (admin, API)
    var skipPaths = ['/admin', '/api/', '/app/', '/setup/', '/logout'];
    if (skipPaths.some(function (s) { return url.pathname.startsWith(s); })) return;

    e.preventDefault();
    naviguj(url.href, ziskSmer(location.pathname, url.pathname), false);
  });

  // Zpet/Vpred tlacitko prohlizece
  window.addEventListener('popstate', function (e) {
    var smer = (e.state && e.state.smer === 'vpred') ? 'zpet' : 'vpred';
    naviguj(location.href, smer, true);
  });

  // Ulozit pocatecni stav
  history.replaceState({ url: location.href, smer: null }, document.title, location.href);

})();
